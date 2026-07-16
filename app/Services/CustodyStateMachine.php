<?php

namespace App\Services;

use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Enums\SopViolationType;
use App\Enums\UserRole;
use App\Exceptions\IllegalTransitionException;
use App\Models\CustodyEvent;
use App\Models\SamplePart;
use App\Models\Setting;
use App\Models\SopViolation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for the chain-of-custody state machine.
 *
 * The transition map is keyed by PartRole then by the current PartStatus, giving
 * the allowed next statuses. Phase 3+ only needs to ADD entries here (e.g. states
 * beyond RECEIVED_REGISTRATION for the LAB part) — never restructure.
 *
 * `transition()` validates a move (map + SOP guards), then atomically writes an
 * immutable CustodyEvent and updates the part's denormalized status.
 */
class CustodyStateMachine
{
    /**
     * role => [ currentStatus => [allowedNextStatus, ...] ].
     *
     * @var array<string, array<string, array<int, PartStatus>>>
     */
    private const TRANSITIONS = [
        PartRole::LAB->value => [
            'COLLECTED' => [PartStatus::SEALED],
            'SEALED' => [PartStatus::IN_TRANSIT],
            'IN_TRANSIT' => [PartStatus::RECEIVED_REGISTRATION],
            // Phase 3: registration -> blind coding -> section -> testing -> result
            //          -> verification -> report. RESULT_ENTERED may also be sent
            //          back to TESTING by a verifying officer (maker-checker return).
            'RECEIVED_REGISTRATION' => [PartStatus::BLIND_CODED],
            'BLIND_CODED' => [PartStatus::ASSIGNED_TO_SECTION],
            'ASSIGNED_TO_SECTION' => [PartStatus::TESTING],
            'TESTING' => [PartStatus::RESULT_ENTERED],
            'RESULT_ENTERED' => [PartStatus::VERIFIED, PartStatus::TESTING],
            'VERIFIED' => [PartStatus::REPORT_ISSUED],
        ],
        PartRole::REFERENCE->value => [
            'COLLECTED' => [PartStatus::SEALED],
            'SEALED' => [PartStatus::IN_TRANSIT],
            'IN_TRANSIT' => [PartStatus::RECEIVED_REGISTRATION],
            'RECEIVED_REGISTRATION' => [PartStatus::IN_RETENTION],
            // Activation path (IN_RETENTION -> ACTIVATED_FOR_RETEST) added in Phase 3/5.
        ],
        PartRole::FBO_COPY->value => [
            'COLLECTED' => [PartStatus::SEALED],
            'SEALED' => [PartStatus::RELEASED_TO_FBO], // terminal
        ],
    ];

    /**
     * Statuses with no outgoing transitions in this phase. REJECTED is unreachable
     * from these (there is nothing left to reject).
     *
     * @var array<int, PartStatus>
     */
    private const TERMINAL = [
        PartStatus::RELEASED_TO_FBO,
        PartStatus::REPORT_ISSUED,
        PartStatus::REJECTED,
        PartStatus::DESTROYED,
    ];

    /**
     * Target statuses that require the actor to hold one of the listed roles.
     * A null actor (system-generated event) bypasses the check.
     *
     * @var array<string, array<int, UserRole>>
     */
    private const ROLE_REQUIREMENTS = [
        'IN_TRANSIT' => [UserRole::FSO, UserRole::TRANSPORT],
        'RECEIVED_REGISTRATION' => [UserRole::REGISTRATION_OFFICER],
        'BLIND_CODED' => [UserRole::REGISTRATION_OFFICER],
        'ASSIGNED_TO_SECTION' => [UserRole::REGISTRATION_OFFICER],
        'IN_RETENTION' => [UserRole::REGISTRATION_OFFICER],
        'TESTING' => [UserRole::LAB_ANALYST],
        'RESULT_ENTERED' => [UserRole::LAB_ANALYST],
        'VERIFIED' => [UserRole::VERIFYING_OFFICER],
        'REPORT_ISSUED' => [UserRole::VERIFYING_OFFICER],
    ];

    /**
     * Transition-specific role overrides, keyed "FROM->TO". These win over the
     * target-status requirements above. Needed where the same target status is
     * reached by different roles — e.g. an analyst STARTs testing
     * (ASSIGNED_TO_SECTION->TESTING) but a verifying officer RETURNS work
     * (RESULT_ENTERED->TESTING).
     *
     * @var array<string, array<int, UserRole>>
     */
    private const TRANSITION_ROLE_OVERRIDES = [
        'RESULT_ENTERED->TESTING' => [UserRole::VERIFYING_OFFICER],
    ];

    /**
     * Target statuses that require a cold-chain temperature reading when the parent
     * sampling event is perishable.
     *
     * @var array<int, PartStatus>
     */
    private const COLD_CHAIN_TARGETS = [
        PartStatus::IN_TRANSIT,
        PartStatus::RECEIVED_REGISTRATION,
    ];

    /**
     * Allowed next statuses for a part in its current state (including REJECTED
     * where applicable). Useful for the API and for tests.
     *
     * @return array<int, PartStatus>
     */
    public function allowedTransitions(SamplePart $part): array
    {
        $map = self::TRANSITIONS[$part->role->value] ?? [];
        $allowed = $map[$part->status->value] ?? [];

        if (! $this->isTerminal($part->status)) {
            $allowed = [...$allowed, PartStatus::REJECTED];
        }

        return $allowed;
    }

    public function isTerminal(PartStatus $status): bool
    {
        return in_array($status, self::TERMINAL, true);
    }

    public function canTransition(SamplePart $part, PartStatus $to): bool
    {
        return in_array($to, $this->allowedTransitions($part), true);
    }

    /**
     * Record the very first custody event (COLLECTED) for a freshly created part.
     * The part must already be at COLLECTED; this writes the opening trail entry.
     *
     * @param  array<string, mixed>  $context
     */
    public function recordInitialCollection(SamplePart $part, ?User $actor, array $context = []): CustodyEvent
    {
        if ($part->status !== PartStatus::COLLECTED) {
            throw new IllegalTransitionException(
                'Initial custody event can only be recorded for a part in the COLLECTED state.'
            );
        }

        return $this->writeEvent($part, PartStatus::COLLECTED, $actor, $context);
    }

    /**
     * Validate and perform a transition, atomically writing the custody event and
     * updating the part's denormalized status.
     *
     * @param  array<string, mixed>  $context  latitude, longitude, location_note,
     *                                          temperature_c, photo_path, notes
     *
     * @throws IllegalTransitionException
     */
    public function transition(SamplePart $part, PartStatus $to, ?User $actor, array $context = []): CustodyEvent
    {
        if (! $this->canTransition($part, $to)) {
            throw new IllegalTransitionException(sprintf(
                'Cannot move a %s part from %s to %s.',
                $part->role->value,
                $part->status->value,
                $to->value,
            ));
        }

        $this->assertGuards($part, $to, $actor, $context);

        return $this->writeEvent($part, $to, $actor, $context);
    }

    /**
     * SOP guards enforced on every transition.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws IllegalTransitionException
     */
    private function assertGuards(SamplePart $part, PartStatus $to, ?User $actor, array $context): void
    {
        // (b) REJECTED requires non-empty notes.
        if ($to === PartStatus::REJECTED && trim((string) ($context['notes'] ?? '')) === '') {
            throw new IllegalTransitionException('Rejecting a part requires a non-empty reason in notes.');
        }

        // (a) Cold chain: perishable samples need a temperature reading when moving
        // into transit or arriving at registration.
        if (in_array($to, self::COLD_CHAIN_TARGETS, true) && $part->samplingEvent->is_perishable) {
            $temp = $context['temperature_c'] ?? null;
            if ($temp === null || $temp === '') {
                throw new IllegalTransitionException(sprintf(
                    'A temperature reading (temperature_c) is required to move a perishable sample into %s.',
                    $to->value,
                ));
            }
        }

        // (c) Actor role check (skipped for system-generated events with no actor).
        // A transition-specific override (FROM->TO) wins over the target-status rule.
        $overrideKey = $part->status->value.'->'.$to->value;
        $required = self::TRANSITION_ROLE_OVERRIDES[$overrideKey] ?? self::ROLE_REQUIREMENTS[$to->value] ?? null;
        if ($required !== null && $actor !== null && ! in_array($actor->role, $required, true)) {
            $names = implode(', ', array_map(fn (UserRole $r) => $r->value, $required));
            throw new IllegalTransitionException(sprintf(
                'Only %s may move a part into %s.',
                $names,
                $to->value,
            ));
        }
    }

    /**
     * Atomically append the custody event and update the part status.
     *
     * @param  array<string, mixed>  $context
     */
    private function writeEvent(SamplePart $part, PartStatus $to, ?User $actor, array $context): CustodyEvent
    {
        return DB::transaction(function () use ($part, $to, $actor, $context): CustodyEvent {
            $event = $part->custodyEvents()->create([
                'status' => $to,
                'actor_id' => $actor?->id,
                'latitude' => $context['latitude'] ?? null,
                'longitude' => $context['longitude'] ?? null,
                'location_note' => $context['location_note'] ?? null,
                'temperature_c' => $context['temperature_c'] ?? null,
                'photo_path' => $context['photo_path'] ?? null,
                'notes' => $context['notes'] ?? null,
            ]);

            $part->update(['status' => $to]);

            $this->recordColdChainBreachIfNeeded($part, $to, $context);

            return $event;
        });
    }

    /**
     * Record a COLD_CHAIN_BREACH SOP violation when a perishable sample moves into a
     * cold-chain checkpoint with a temperature outside the configured range. The
     * transition itself is still accepted — the breach is flagged, not blocked.
     *
     * @param  array<string, mixed>  $context
     */
    private function recordColdChainBreachIfNeeded(SamplePart $part, PartStatus $to, array $context): void
    {
        if (! in_array($to, self::COLD_CHAIN_TARGETS, true) || ! $part->samplingEvent->is_perishable) {
            return;
        }

        $temp = $context['temperature_c'] ?? null;
        if ($temp === null || $temp === '') {
            return; // handled by the guard (temperature is mandatory here)
        }

        $min = (float) Setting::get('cold_chain_min_c', '0');
        $max = (float) Setting::get('cold_chain_max_c', '8');
        $temp = (float) $temp;

        if ($temp < $min || $temp > $max) {
            SopViolation::create([
                'sample_part_id' => $part->id,
                'type' => SopViolationType::COLD_CHAIN_BREACH,
                'details' => [
                    'temperature_c' => $temp,
                    'min_c' => $min,
                    'max_c' => $max,
                    'at_status' => $to->value,
                ],
                'detected_at' => now(),
            ]);
        }
    }
}
