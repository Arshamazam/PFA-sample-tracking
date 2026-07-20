<?php

namespace App\Services;

use App\Enums\DisputeStatus;
use App\Enums\LabSection;
use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Enums\Verdict;
use App\Events\DisputeDecided;
use App\Events\DisputeFiled;
use App\Models\Dispute;
use App\Models\LabResult;
use App\Models\SamplePart;
use App\Models\SamplingEvent;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * All dispute + resampling business rules live here (not in controllers) so the
 * internal officer-filing endpoint (Phase 4) and the public FBO self-service route
 * (Phase 6) share exactly one implementation.
 */
class DisputeService
{
    /**
     * Dispute statuses that count as "open" — while one exists, no second dispute
     * may be filed for the same event.
     *
     * @var array<int, DisputeStatus>
     */
    private const OPEN_STATUSES = [
        DisputeStatus::FILED,
        DisputeStatus::ACCEPTED,
        DisputeStatus::RETEST_IN_PROGRESS,
    ];

    public function __construct(
        private readonly CustodyStateMachine $custody,
        private readonly EventCodeGenerator $codes,
    ) {
    }

    /**
     * File a dispute against an event's UNFIT verdict. Enforces every §2 rule.
     *
     * @param  array<string, mixed>  $data  event_code, filed_by_name, filed_by_phone,
     *                                       filed_by_cnic, reason
     */
    public function file(array $data): Dispute
    {
        $event = SamplingEvent::where('event_code', $data['event_code'])->firstOrFail();

        $labPart = $event->parts()->where('role', PartRole::LAB->value)->first();
        $original = $labPart?->labResult;

        // (a) There must be an UNFIT report to dispute.
        if ($labPart === null
            || $labPart->status !== PartStatus::REPORT_ISSUED
            || $original === null
            || $original->verdict !== Verdict::UNFIT) {
            throw ValidationException::withMessages([
                'event_code' => ['There is no UNFIT report for this event, so there is no right to dispute.'],
            ]);
        }

        // (b) The dispute window must still be open.
        $expiresAt = $this->windowExpiry($original->verdict_at);
        if (Carbon::now()->greaterThan($expiresAt)) {
            throw ValidationException::withMessages([
                'event_code' => ["The dispute window closed on {$expiresAt->toDayDateTimeString()}."],
            ]);
        }

        // (c) Only one open dispute per event.
        $hasOpen = $event->disputes()
            ->whereIn('status', array_map(fn (DisputeStatus $s) => $s->value, self::OPEN_STATUSES))
            ->exists();
        if ($hasOpen) {
            throw ValidationException::withMessages([
                'event_code' => ['An open dispute already exists for this event.'],
            ]);
        }

        // (d) The reference part must be available for retest.
        $reference = $event->parts()->where('role', PartRole::REFERENCE->value)->first();
        if ($reference === null) {
            throw ValidationException::withMessages([
                'event_code' => ['No reference part exists for this event; it cannot be retested.'],
            ]);
        }
        if ($reference->status === PartStatus::DESTROYED) {
            // This should be impossible if the retention lifecycle is correct — a
            // destroyed reference during an open dispute window is an ops incident.
            Log::alert('Dispute filing blocked: reference part already destroyed within dispute window.', [
                'event_code' => $event->event_code,
                'reference_part_id' => $reference->id,
            ]);
            throw ValidationException::withMessages([
                'event_code' => ['The reference part has been destroyed and can no longer be retested.'],
            ]);
        }
        if ($reference->status !== PartStatus::IN_RETENTION) {
            throw ValidationException::withMessages([
                'event_code' => ["The reference part is not in retention (status {$reference->status->value})."],
            ]);
        }

        $dispute = Dispute::create([
            'sampling_event_id' => $event->id,
            'filed_by_name' => $data['filed_by_name'],
            'filed_by_phone' => $data['filed_by_phone'],
            'filed_by_cnic' => $data['filed_by_cnic'] ?? null,
            'reason' => $data['reason'] ?? null,
            'status' => DisputeStatus::FILED,
            'source' => $data['source'] ?? 'INTERNAL',
            'reference_no' => $this->codes->generateDisputeReference(),
            'filed_at' => Carbon::now(),
        ]);

        DisputeFiled::dispatch($dispute->id);

        return $dispute;
    }

    /**
     * Decide a filed dispute. On ACCEPTED, activates the reference part for retest.
     *
     * @throws ValidationException
     */
    public function decide(
        Dispute $dispute,
        \App\Models\User $decider,
        string $decision,
        string $notes,
        ?LabSection $section = null,
    ): Dispute {
        if ($dispute->status !== DisputeStatus::FILED) {
            throw ValidationException::withMessages([
                'dispute' => ["This dispute has already been decided (status {$dispute->status->value})."],
            ]);
        }

        $event = $dispute->samplingEvent;
        $original = $this->originalResult($event);

        // Maker-checker: whoever signed off the original verdict cannot also decide
        // the challenge to it.
        if ($original?->verified_by_id !== null && $original->verified_by_id === $decider->id) {
            throw ValidationException::withMessages([
                'decided_by' => ['You verified the original result; a different officer must decide this dispute (maker-checker).'],
            ]);
        }

        if ($decision === DisputeStatus::REJECTED->value) {
            $dispute->update([
                'status' => DisputeStatus::REJECTED,
                'decided_by_id' => $decider->id,
                'decided_at' => Carbon::now(),
                'decision_notes' => $notes,
            ]);

            DisputeDecided::dispatch($dispute->id, false);

            return $dispute->refresh();
        }

        // ACCEPTED — activate the reference part for a blind retest.
        return DB::transaction(function () use ($dispute, $event, $decider, $notes, $section, $original) {
            $reference = $event->parts()->where('role', PartRole::REFERENCE->value)->firstOrFail();

            // A FRESH blind code — never reuse the original LAB part's code.
            $reference->update(['blind_code' => $this->codes->generateBlindCode()]);

            $labSection = $section ?? $original?->lab_section ?? LabSection::GENERAL;
            LabResult::updateOrCreate(
                ['sample_part_id' => $reference->id],
                [
                    'lab_section' => $labSection,
                    'analyst_id' => null,
                    'verified_by_id' => null,
                    'parameters' => null,
                    'verdict' => null,
                    'verdict_at' => null,
                ],
            );

            $dispute->update([
                'status' => DisputeStatus::ACCEPTED,
                'decided_by_id' => $decider->id,
                'decided_at' => Carbon::now(),
                'decision_notes' => $notes,
            ]);

            $this->custody->transition($reference, PartStatus::ACTIVATED_FOR_RETEST, $decider, [
                'dispute_id' => $dispute->id,
                'notes' => 'Reference part activated for retest via accepted dispute.',
            ]);

            $dispute->update(['status' => DisputeStatus::RETEST_IN_PROGRESS]);

            DisputeDecided::dispatch($dispute->id, true);

            return $dispute->refresh();
        });
    }

    /**
     * When a retest result is verified, link it back to the open dispute and close
     * it. No-op for anything other than a reference part under an active retest.
     */
    public function closeRetestIfApplicable(SamplePart $part, LabResult $labResult): void
    {
        if ($part->role !== PartRole::REFERENCE) {
            return;
        }

        $dispute = $part->samplingEvent->disputes()
            ->where('status', DisputeStatus::RETEST_IN_PROGRESS->value)
            ->first();

        if ($dispute === null) {
            return;
        }

        $dispute->update([
            'retest_lab_result_id' => $labResult->id,
            'status' => DisputeStatus::CLOSED,
        ]);
    }

    /**
     * The original LAB part's result for an event.
     */
    public function originalResult(SamplingEvent $event): ?LabResult
    {
        return $event->parts()
            ->where('role', PartRole::LAB->value)
            ->first()
            ?->labResult;
    }

    /**
     * The end of the dispute window relative to a verdict timestamp.
     */
    public function windowExpiry(Carbon $verdictAt): Carbon
    {
        $windowDays = (int) Setting::get('dispute_window_days', '7');

        return $verdictAt->copy()->addDays($windowDays);
    }
}
