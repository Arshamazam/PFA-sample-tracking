<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LabSection;
use App\Enums\PartStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\StoreLabResultsRequest;
use App\Http\Resources\BlindSamplePartResource;
use App\Models\SamplePart;
use App\Services\LabService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * The lab analyst's workbench.
 *
 * EVERY response here goes through BlindSamplePartResource — analysts address
 * samples only by blind_code and must never see the originating business. See
 * tests/Feature/BlindWallTest.php.
 */
class LabController extends Controller
{
    public function __construct(private readonly LabService $lab)
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
            // ACTIVATED_FOR_RETEST is an activated reference part awaiting testing —
            // it behaves exactly like ASSIGNED_TO_SECTION from the analyst's side.
            ->whereIn('status', [
                PartStatus::ASSIGNED_TO_SECTION->value,
                PartStatus::ACTIVATED_FOR_RETEST->value,
                PartStatus::TESTING->value,
            ])
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

        $this->lab->start($part, $request->user());

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
        $reportPhotoPath = $request->file('report_photo')->store('lab-reports', 'local');

        $this->lab->submitResults($part, $request->user(), $request->validated('parameters'), $reportPhotoPath);

        return $this->blindResponse($part);
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
