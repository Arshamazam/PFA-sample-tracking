<?php

namespace App\Services;

use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Models\RapidTest;
use App\Models\SamplePart;
use App\Models\SamplingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sampling-event lifecycle (create draft, add parts, finalize under the Rule of
 * Three), shared by the API and the FSO web fallback.
 */
class SamplingEventService
{
    public function __construct(
        private readonly PremisesResolver $premisesResolver,
        private readonly EventCodeGenerator $eventCodes,
        private readonly CustodyStateMachine $custody,
    ) {
    }

    private function district(): string
    {
        return config('pfa.district', 'LHR');
    }

    /**
     * Create a DRAFT sampling event (finalized_at null), resolving the premises.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $fso, array $data): SamplingEvent
    {
        $premises = $this->premisesResolver->resolveByLicense(
            $data['premises_license'],
            [
                'name' => $data['premises_name'] ?? null,
                'address' => $data['premises_address'] ?? null,
                'city' => $data['premises_city'] ?? null,
            ],
        );

        $event = SamplingEvent::create([
            'event_code' => $this->eventCodes->generate($this->district()),
            'premises_id' => $premises->id,
            'fso_id' => $fso->id,
            'food_item' => $data['food_item'],
            'food_category' => $data['food_category'] ?? null,
            'brand_name' => $data['brand_name'] ?? null,
            'is_perishable' => (bool) ($data['is_perishable'] ?? false),
            'witness_name' => $data['witness_name'] ?? '',
            'witness_cnic' => $data['witness_cnic'] ?? null,
            'collected_at' => $data['collected_at'],
        ]);

        if (! empty($data['rapid_test_id'])) {
            RapidTest::where('id', $data['rapid_test_id'])->update(['sampling_event_id' => $event->id]);
        }

        return $event;
    }

    /**
     * Attach ONE part at COLLECTED with its opening custody event. Rejects a
     * duplicate role before hitting the DB constraint.
     */
    public function addPart(SamplingEvent $event, User $actor, PartRole $role, string $sealNumber, string $sealPhotoPath): SamplePart
    {
        $this->assertDraft($event);

        if ($event->parts()->where('role', $role)->exists()) {
            throw ValidationException::withMessages([
                'role' => ["A {$role->value} part already exists for this event."],
            ]);
        }

        return DB::transaction(function () use ($event, $actor, $role, $sealNumber, $sealPhotoPath) {
            $part = $event->parts()->create([
                'role' => $role,
                'qr_token' => $this->uniqueQrToken(),
                'seal_number' => $sealNumber,
                'seal_photo_path' => $sealPhotoPath,
                'status' => PartStatus::COLLECTED,
            ]);

            $this->custody->recordInitialCollection($part, $actor, [
                'notes' => 'Part collected and split before witness.',
            ]);

            return $part;
        });
    }

    /**
     * Finalize: enforce the Rule of Three, then seal all three parts.
     */
    public function finalize(SamplingEvent $event, User $actor): void
    {
        $this->assertDraft($event);
        $this->assertRuleOfThree($event);

        DB::transaction(function () use ($event, $actor) {
            $event->update(['finalized_at' => now()]);

            foreach ($event->parts as $part) {
                $this->custody->transition($part, PartStatus::SEALED, $actor, [
                    'notes' => 'Sealed at finalization of sampling event.',
                ]);
            }
        });
    }

    public function assertRuleOfThree(SamplingEvent $event): void
    {
        $errors = [];
        $parts = $event->parts()->get();

        $roles = $parts->pluck('role')->map(fn (PartRole $r) => $r->value)->sort()->values()->all();
        $expected = collect(PartRole::values())->sort()->values()->all();

        if ($roles !== $expected) {
            $errors['parts'][] = 'The event must have exactly 3 parts: LAB, REFERENCE, and FBO_COPY.';
        }

        foreach ($parts as $part) {
            if (trim((string) $part->seal_number) === '' || trim((string) $part->seal_photo_path) === '') {
                $errors['parts'][] = "The {$part->role->value} part is missing a seal number or seal photo.";
            }
        }

        if (trim((string) $event->witness_name) === '') {
            $errors['witness_name'][] = 'A witness name is required before finalization.';
        }

        if (trim((string) $event->witness_signature_path) === '') {
            $errors['witness_signature'][] = 'A witness signature must be uploaded before finalization.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function assertDraft(SamplingEvent $event): void
    {
        if (! $event->isDraft()) {
            throw ValidationException::withMessages([
                'event' => ['This sampling event is finalized and can no longer be modified.'],
            ]);
        }
    }

    private function uniqueQrToken(): string
    {
        do {
            $token = Str::random(32);
        } while (SamplePart::where('qr_token', $token)->exists());

        return $token;
    }
}
