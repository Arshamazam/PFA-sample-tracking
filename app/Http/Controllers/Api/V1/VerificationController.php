<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PartStatus;
use App\Enums\Verdict;
use App\Http\Controllers\Controller;
use App\Http\Requests\Verification\ReturnToAnalystRequest;
use App\Http\Requests\Verification\StoreVerdictRequest;
use App\Http\Resources\SamplePartResource;
use App\Jobs\GenerateReportPdf;
use App\Models\SamplePart;
use App\Services\CustodyStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verifying officer (maker-checker) stage.
 *
 * Unlike the analyst, this role sees the FULL de-blinded picture — premises,
 * license, brand, the FSO — because a verdict is a legal determination about a
 * specific business. The one rule enforced here is that the verifier may not be
 * the analyst who produced the result.
 */
class VerificationController extends Controller
{
    public function __construct(private readonly CustodyStateMachine $custody)
    {
    }

    /**
     * Samples awaiting verification (RESULT_ENTERED), full detail, oldest first.
     */
    public function queue(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $parts = SamplePart::query()
            ->where('status', PartStatus::RESULT_ENTERED->value)
            ->with(['labResult.analyst', 'samplingEvent.premises', 'sopViolations'])
            ->oldest('created_at')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return SamplePartResource::collection($parts);
    }

    /**
     * Record the verdict, move the part to VERIFIED, and queue the report PDF.
     */
    public function verdict(StoreVerdictRequest $request, string $blindCode): JsonResponse
    {
        $part = $this->partByBlindCode($blindCode);
        $labResult = $part->labResult;

        if ($labResult === null) {
            throw ValidationException::withMessages([
                'blind_code' => ['This sample has no lab result to verify.'],
            ]);
        }

        // Maker-checker: the verifier must not be the analyst who ran the test.
        if ($labResult->analyst_id !== null && $labResult->analyst_id === $request->user()->id) {
            throw ValidationException::withMessages([
                'verified_by' => ['You analysed this sample; a different officer must verify it (maker-checker).'],
            ]);
        }

        DB::transaction(function () use ($part, $labResult, $request) {
            $labResult->update([
                'verdict' => Verdict::from($request->string('verdict')->value()),
                'verdict_at' => now(),
                'verified_by_id' => $request->user()->id,
            ]);

            $this->custody->transition($part, PartStatus::VERIFIED, $request->user(), [
                'notes' => $request->input('notes') ?? 'Result verified.',
            ]);
        });

        // Report rendering is queued — shared hosting cannot render inline reliably.
        GenerateReportPdf::dispatch($part->id);

        return $this->fullResponse($part);
    }

    /**
     * Send the result back to the analyst for rework: RESULT_ENTERED -> TESTING.
     */
    public function returnToAnalyst(ReturnToAnalystRequest $request, string $blindCode): JsonResponse
    {
        $part = $this->partByBlindCode($blindCode);

        $this->custody->transition($part, PartStatus::TESTING, $request->user(), [
            'notes' => 'Returned to analyst: '.$request->string('notes')->value(),
        ]);

        return $this->fullResponse($part);
    }

    private function partByBlindCode(string $blindCode): SamplePart
    {
        return SamplePart::where('blind_code', $blindCode)->firstOrFail();
    }

    private function fullResponse(SamplePart $part): JsonResponse
    {
        $part->refresh()->load(['labResult.analyst', 'labResult.verifiedBy', 'samplingEvent.premises', 'custodyEvents.actor']);

        return (new SamplePartResource($part))->response();
    }
}
