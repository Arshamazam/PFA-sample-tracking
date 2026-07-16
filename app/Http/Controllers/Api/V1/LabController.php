<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LabSection;
use App\Enums\PartStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\StoreLabResultsRequest;
use App\Http\Resources\BlindSamplePartResource;
use App\Models\LabResult;
use App\Models\SamplePart;
use App\Models\TestCatalog;
use App\Services\CustodyStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The lab analyst's workbench.
 *
 * EVERY response here goes through BlindSamplePartResource — analysts address
 * samples only by blind_code and must never see the originating business. See
 * tests/Feature/BlindWallTest.php.
 */
class LabController extends Controller
{
    public function __construct(private readonly CustodyStateMachine $custody)
    {
    }

    /**
     * Work queue for a section: samples assigned to it or already under test,
     * oldest first.
     */
    public function queue(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'section' => ['required', Rule::in(LabSection::values())],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $parts = SamplePart::query()
            ->whereIn('status', [PartStatus::ASSIGNED_TO_SECTION->value, PartStatus::TESTING->value])
            ->whereHas('labResult', fn ($q) => $q->where('lab_section', $validated['section']))
            ->with(['labResult', 'samplingEvent', 'custodyEvents'])
            ->oldest('created_at')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return BlindSamplePartResource::collection($parts);
    }

    /**
     * Start testing a sample: ASSIGNED_TO_SECTION -> TESTING, claiming it for the
     * analyst.
     */
    public function start(Request $request, string $blindCode): JsonResponse
    {
        $part = $this->partByBlindCode($blindCode);

        DB::transaction(function () use ($part, $request) {
            $labResult = LabResult::firstOrNew(['sample_part_id' => $part->id]);
            if ($labResult->analyst_id === null) {
                $labResult->analyst_id = $request->user()->id;
            }
            $labResult->save();

            $this->custody->transition($part, PartStatus::TESTING, $request->user(), [
                'notes' => 'Testing started.',
            ]);
        });

        return $this->blindResponse($part);
    }

    /**
     * Enter or re-enter results. First submission moves TESTING -> RESULT_ENTERED;
     * while RESULT_ENTERED the analyst may re-submit, and the previous parameters
     * are archived into lab_result_revisions.
     */
    public function storeResults(StoreLabResultsRequest $request, string $blindCode): JsonResponse
    {
        $part = $this->partByBlindCode($blindCode);

        if (! in_array($part->status, [PartStatus::TESTING, PartStatus::RESULT_ENTERED], true)) {
            throw ValidationException::withMessages([
                'blind_code' => ['Results can only be entered while a sample is under test or awaiting verification.'],
            ]);
        }

        $parameters = $request->validated('parameters');
        $this->assertParametersMatchCatalog($part, $parameters);

        $reportPhotoPath = $request->file('report_photo')->store('lab-reports', 'local');

        DB::transaction(function () use ($part, $request, $parameters, $reportPhotoPath) {
            $labResult = LabResult::firstOrNew(['sample_part_id' => $part->id]);

            // Archive the previous submission before overwriting.
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

            $labResult->analyst_id ??= $request->user()->id;
            $labResult->parameters = $parameters;
            $labResult->report_photo_path = $reportPhotoPath;
            $labResult->save();

            // Only the first submission advances the state.
            if ($part->status === PartStatus::TESTING) {
                $this->custody->transition($part, PartStatus::RESULT_ENTERED, $request->user(), [
                    'notes' => 'Results entered.',
                ]);
            }
        });

        return $this->blindResponse($part);
    }

    /**
     * Parameter names must come from the catalog template for the sample's food
     * category, unless explicitly flagged is_additional.
     *
     * @param  array<int, array<string, mixed>>  $parameters
     */
    private function assertParametersMatchCatalog(SamplePart $part, array $parameters): void
    {
        $category = $part->samplingEvent->food_category;
        $section = $part->labResult?->lab_section;

        if ($category === null) {
            return; // nothing to validate against
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
                    '"%s" is not part of the %s test template. Mark it is_additional=true to record it anyway.',
                    $parameter['name'],
                    $category,
                );
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function partByBlindCode(string $blindCode): SamplePart
    {
        return SamplePart::where('blind_code', $blindCode)->firstOrFail();
    }

    private function blindResponse(SamplePart $part): JsonResponse
    {
        $part->refresh()->load(['labResult', 'samplingEvent', 'custodyEvents']);

        return (new BlindSamplePartResource($part))->response();
    }
}
