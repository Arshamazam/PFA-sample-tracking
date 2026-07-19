<?php

namespace App\Services;

use App\Enums\PartStatus;
use App\Models\LabResult;
use App\Models\SamplePart;
use App\Models\TestCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lab workbench actions, shared by the API and the web panel. Never exposes
 * identity — callers are responsible for rendering only blind data.
 */
class LabService
{
    public function __construct(private readonly CustodyStateMachine $custody)
    {
    }

    /**
     * Start (or claim) testing: ASSIGNED_TO_SECTION / ACTIVATED_FOR_RETEST -> TESTING.
     */
    public function start(SamplePart $part, User $analyst): void
    {
        DB::transaction(function () use ($part, $analyst) {
            $labResult = LabResult::firstOrNew(['sample_part_id' => $part->id]);
            if ($labResult->analyst_id === null) {
                $labResult->analyst_id = $analyst->id;
            }
            $labResult->save();

            $this->custody->transition($part, PartStatus::TESTING, $analyst, [
                'notes' => 'Testing started.',
            ]);
        });
    }

    /**
     * Enter/re-enter results. First submission advances TESTING -> RESULT_ENTERED;
     * re-submission archives the prior parameters into lab_result_revisions.
     *
     * @param  array<int, array<string, mixed>>  $parameters
     */
    public function submitResults(SamplePart $part, User $analyst, array $parameters, string $reportPhotoPath): void
    {
        if (! in_array($part->status, [PartStatus::TESTING, PartStatus::RESULT_ENTERED], true)) {
            throw ValidationException::withMessages([
                'blind_code' => ['Results can only be entered while a sample is under test or awaiting verification.'],
            ]);
        }

        $this->assertParametersMatchCatalog($part, $parameters);

        DB::transaction(function () use ($part, $analyst, $parameters, $reportPhotoPath) {
            $labResult = LabResult::firstOrNew(['sample_part_id' => $part->id]);

            if ($labResult->exists && $labResult->parameters !== null) {
                $revisions = $labResult->lab_result_revisions ?? [];
                $revisions[] = [
                    'parameters' => $labResult->parameters,
                    'report_photo_path' => $labResult->report_photo_path,
                    'analyst_id' => $labResult->analyst_id,
                    'archived_at' => now()->toIso8601String(),
                ];
                $labResult->lab_result_revisions = $revisions;
            }

            $labResult->analyst_id ??= $analyst->id;
            $labResult->parameters = $parameters;
            $labResult->report_photo_path = $reportPhotoPath;
            $labResult->save();

            if ($part->status === PartStatus::TESTING) {
                $this->custody->transition($part, PartStatus::RESULT_ENTERED, $analyst, [
                    'notes' => 'Results entered.',
                ]);
            }
        });
    }

    /**
     * Parameter names must come from the catalog template unless flagged additional.
     *
     * @param  array<int, array<string, mixed>>  $parameters
     */
    public function assertParametersMatchCatalog(SamplePart $part, array $parameters): void
    {
        $category = $part->samplingEvent->food_category;
        $section = $part->labResult?->lab_section;

        if ($category === null) {
            return;
        }

        $template = TestCatalog::query()
            ->where('food_category', $category)
            ->when($section !== null, fn ($q) => $q->where('lab_section', $section))
            ->first();

        if ($template === null) {
            return;
        }

        $allowed = collect($template->parameters ?? [])->pluck('name')->all();
        $errors = [];

        foreach ($parameters as $index => $parameter) {
            $isAdditional = (bool) ($parameter['is_additional'] ?? false);

            if (! $isAdditional && ! in_array($parameter['name'], $allowed, true)) {
                $errors["parameters.{$index}.name"][] = sprintf(
                    '"%s" is not part of the %s test template. Mark it additional to record it anyway.',
                    $parameter['name'],
                    $category,
                );
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
