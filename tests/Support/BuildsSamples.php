<?php

namespace Tests\Support;

use App\Enums\LabSection;
use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Enums\UserRole;
use App\Enums\Verdict;
use App\Models\LabResult;
use App\Models\Premises;
use App\Models\SamplePart;
use App\Models\SamplingEvent;
use App\Models\TestCatalog;
use App\Models\User;
use App\Services\EventCodeGenerator;
use Illuminate\Support\Carbon;

/**
 * Helpers for standing up samples at a given point in the pipeline without
 * driving every upstream endpoint.
 */
trait BuildsSamples
{
    protected function makeUser(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /**
     * A LAB part belonging to a real event/premises, parked at $status.
     */
    protected function makeLabPart(
        PartStatus $status = PartStatus::IN_TRANSIT,
        bool $perishable = false,
        ?string $blindCode = null,
        ?string $foodCategory = 'MILK',
    ): SamplePart {
        $event = SamplingEvent::factory()->create([
            'premises_id' => Premises::factory(),
            'fso_id' => $this->makeUser(UserRole::FSO)->id,
            'is_perishable' => $perishable,
            'food_category' => $foodCategory,
            'food_item' => 'Loose Milk',
            'brand_name' => 'Dairy Best',
            'finalized_at' => now(),
        ]);

        return SamplePart::factory()->for($event, 'samplingEvent')->create([
            'role' => PartRole::LAB,
            'status' => $status,
            'blind_code' => $blindCode,
        ]);
    }

    /**
     * A LAB part that has been blind-coded and assigned to a section, with a
     * lab_result row — i.e. ready for the analyst.
     */
    protected function makeAssignedPart(
        string $blindCode = 'BC-2026-000001',
        \App\Enums\LabSection $section = \App\Enums\LabSection::CHEMICAL,
        PartStatus $status = PartStatus::ASSIGNED_TO_SECTION,
        ?User $analyst = null,
    ): SamplePart {
        $part = $this->makeLabPart(status: $status, blindCode: $blindCode);

        LabResult::create([
            'sample_part_id' => $part->id,
            'lab_section' => $section,
            'analyst_id' => $analyst?->id,
        ]);

        $this->makeCatalogEntry($part->samplingEvent->food_category, $section);

        return $part->refresh();
    }

    /**
     * A finalized event whose LAB part has reached REPORT_ISSUED with a verdict, plus
     * a REFERENCE part in retention — i.e. ready to be disputed.
     *
     * @return array{event: SamplingEvent, lab: SamplePart, reference: SamplePart, analyst: User, verifier: User}
     */
    protected function makeReportedEvent(
        Verdict $verdict = Verdict::UNFIT,
        ?User $verifier = null,
        ?User $analyst = null,
        ?Carbon $verdictAt = null,
        LabSection $section = LabSection::CHEMICAL,
        bool $withReference = true,
        ?PartStatus $referenceStatus = PartStatus::IN_RETENTION,
    ): array {
        $verifier ??= $this->makeUser(UserRole::VERIFYING_OFFICER);
        $analyst ??= $this->makeUser(UserRole::LAB_ANALYST);
        $verdictAt ??= Carbon::now();

        $event = SamplingEvent::factory()->create([
            'premises_id' => Premises::factory(),
            'fso_id' => $this->makeUser(UserRole::FSO)->id,
            'food_category' => 'MILK',
            'food_item' => 'Loose Milk',
            'brand_name' => 'Dairy Best',
            'finalized_at' => now(),
        ]);

        // Advance the blind-code counter so the original code differs from any later retest code.
        $originalBlind = app(EventCodeGenerator::class)->generateBlindCode();

        $lab = SamplePart::factory()->for($event, 'samplingEvent')->create([
            'role' => PartRole::LAB,
            'status' => PartStatus::REPORT_ISSUED,
            'blind_code' => $originalBlind,
        ]);

        LabResult::create([
            'sample_part_id' => $lab->id,
            'lab_section' => $section,
            'analyst_id' => $analyst->id,
            'verified_by_id' => $verifier->id,
            'parameters' => [
                ['name' => 'Fat', 'value' => '2.9', 'unit' => '%', 'permissible_limit' => 'min 3.5', 'within_limit' => false],
            ],
            'verdict' => $verdict,
            'verdict_at' => $verdictAt,
            'report_pdf_path' => 'reports/'.$event->event_code.'/'.$lab->id.'.pdf',
        ]);

        $reference = null;
        if ($withReference) {
            $reference = SamplePart::factory()->for($event, 'samplingEvent')->create([
                'role' => PartRole::REFERENCE,
                'status' => $referenceStatus,
            ]);
        }

        $this->makeCatalogEntry('MILK', $section);

        return [
            'event' => $event->refresh(),
            'lab' => $lab->refresh(),
            'reference' => $reference?->refresh(),
            'analyst' => $analyst,
            'verifier' => $verifier,
        ];
    }

    /**
     * A test_catalog template for a category/section (tests don't run seeders).
     */
    protected function makeCatalogEntry(
        ?string $foodCategory = 'MILK',
        \App\Enums\LabSection $section = \App\Enums\LabSection::CHEMICAL,
    ): ?TestCatalog {
        if ($foodCategory === null) {
            return null;
        }

        return TestCatalog::firstOrCreate(
            ['food_category' => $foodCategory, 'lab_section' => $section],
            [
                'test_name' => $foodCategory.' Composition',
                'tat_hours' => 48,
                'parameters' => [
                    ['name' => 'Fat', 'unit' => '%', 'permissible_limit' => 'min 3.5'],
                    ['name' => 'Solids-Not-Fat (SNF)', 'unit' => '%', 'permissible_limit' => 'min 8.9'],
                ],
            ],
        );
    }
}
