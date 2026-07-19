<?php

namespace App\Services;

use App\Enums\LabSection;
use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Enums\SopViolationType;
use App\Models\LabResult;
use App\Models\SamplePart;
use App\Models\Setting;
use App\Models\SopViolation;
use App\Models\TestCatalog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registration Section actions, shared by the API and the web panel so the two
 * never diverge. Pure model/service logic — no HTTP concerns.
 */
class RegistrationService
{
    public function __construct(
        private readonly CustodyStateMachine $custody,
        private readonly EventCodeGenerator $codes,
    ) {
    }

    /**
     * Receive a part. A broken/unconfirmed seal rejects it (notes mandatory);
     * otherwise it is accepted and a late arrival is flagged.
     */
    public function receive(
        SamplePart $part,
        User $actor,
        bool $sealOk,
        string $sealPhotoPath,
        float|string|null $temperatureC,
        ?string $notes,
    ): PartStatus {
        $context = [
            'photo_path' => $sealPhotoPath,
            'temperature_c' => $temperatureC,
            'notes' => $notes,
            'location_note' => 'Registration Section',
        ];

        if (! $sealOk) {
            if (trim((string) $notes) === '') {
                throw ValidationException::withMessages([
                    'notes' => ['Notes are required when rejecting a sample (broken seal or seal-number mismatch).'],
                ]);
            }

            $this->custody->transition($part, PartStatus::REJECTED, $actor, $context);

            return PartStatus::REJECTED;
        }

        $this->custody->transition($part, PartStatus::RECEIVED_REGISTRATION, $actor, $context);
        $this->recordLateTransferIfNeeded($part);

        return PartStatus::RECEIVED_REGISTRATION;
    }

    public function retain(SamplePart $part, User $actor, string $storageLocation, ?string $notes = null): void
    {
        if ($part->role !== PartRole::REFERENCE) {
            throw ValidationException::withMessages([
                'qr_token' => ['Only the REFERENCE part is placed into retention.'],
            ]);
        }

        $this->custody->transition($part, PartStatus::IN_RETENTION, $actor, [
            'location_note' => $storageLocation,
            'notes' => $notes,
        ]);
    }

    public function blindCode(SamplePart $part, User $actor): void
    {
        DB::transaction(function () use ($part, $actor) {
            if ($part->blind_code === null) {
                $part->update(['blind_code' => $this->codes->generateBlindCode()]);
            }

            $this->custody->transition($part, PartStatus::BLIND_CODED, $actor, [
                'notes' => 'Blind code assigned at registration.',
            ]);
        });
    }

    public function assignSection(SamplePart $part, User $actor, LabSection $section): void
    {
        DB::transaction(function () use ($part, $actor, $section) {
            LabResult::updateOrCreate(
                ['sample_part_id' => $part->id],
                ['lab_section' => $section],
            );

            $this->custody->transition($part, PartStatus::ASSIGNED_TO_SECTION, $actor, [
                'notes' => 'Assigned to '.$section->label().' section.',
            ]);
        });
    }

    public function destroy(SamplePart $part, User $actor, string $photoPath, string $notes): void
    {
        if ($part->role !== PartRole::REFERENCE) {
            throw ValidationException::withMessages([
                'qr_token' => ['Only the reference part is retained and destroyed.'],
            ]);
        }

        $this->custody->transition($part, PartStatus::DESTROYED, $actor, [
            'photo_path' => $photoPath,
            'notes' => $notes,
            'location_note' => 'Registration Section',
        ]);
    }

    /**
     * Section suggestion(s) from the test catalog for a food category.
     *
     * @return array{food_category: ?string, suggested: ?TestCatalog, matches: \Illuminate\Support\Collection<int, TestCatalog>}
     */
    public function suggestSection(?string $foodCategory): array
    {
        $matches = $foodCategory === null
            ? collect()
            : TestCatalog::where('food_category', $foodCategory)->get();

        return [
            'food_category' => $foodCategory,
            'suggested' => $matches->first(),
            'matches' => $matches,
        ];
    }

    /**
     * Flag a SAME_DAY_TRANSFER violation when a sample reaches registration after
     * its collection date, or after the same-day deadline on the collection date.
     */
    private function recordLateTransferIfNeeded(SamplePart $part): void
    {
        $collectedAt = $part->samplingEvent->collected_at;
        if ($collectedAt === null) {
            return;
        }

        $now = Carbon::now();
        $deadline = Setting::get('same_day_transfer_deadline', '20:00');

        $reason = null;
        if ($now->toDateString() > $collectedAt->toDateString()) {
            $reason = 'received_after_collection_date';
        } elseif ($now->toDateString() === $collectedAt->toDateString()) {
            [$hour, $minute] = array_pad(explode(':', $deadline), 2, '0');
            $deadlineAt = $now->copy()->setTime((int) $hour, (int) $minute, 0);

            if ($now->greaterThan($deadlineAt)) {
                $reason = 'received_after_same_day_deadline';
            }
        }

        if ($reason === null) {
            return;
        }

        SopViolation::create([
            'sample_part_id' => $part->id,
            'type' => SopViolationType::SAME_DAY_TRANSFER,
            'details' => [
                'reason' => $reason,
                'collected_at' => $collectedAt->toIso8601String(),
                'received_at' => $now->toIso8601String(),
                'deadline' => $deadline,
            ],
            'detected_at' => $now,
        ]);
    }
}
