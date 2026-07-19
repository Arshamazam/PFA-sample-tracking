<?php

namespace App\Http\Resources;

use App\Enums\PartRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The complete, de-blinded story of a sampling event — event, premises, every part
 * with its custody timeline, the rapid test, the original and (if any) retest
 * results, dispute history, and SOP violations.
 *
 * This is the officer/admin-facing view and the single source Phase 5's review panel
 * and Phase 6's (filtered) public page will build on. NOT for analysts.
 *
 * @mixin \App\Models\SamplingEvent
 */
class EventDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labPart = $this->parts->firstWhere('role', PartRole::LAB);
        $referencePart = $this->parts->firstWhere('role', PartRole::REFERENCE);

        $originalResult = $labPart?->labResult;
        $retestResult = $referencePart?->labResult;

        // Legal precedence: the retest verdict supersedes the original once present.
        // (This rule must be confirmed with PFA legal before production — see README.)
        $finalVerdict = $retestResult?->verdict ?? $originalResult?->verdict;

        return [
            'id' => $this->id,
            'event_code' => $this->event_code,
            'status' => $this->finalized_at ? 'FINALIZED' : 'DRAFT',
            'food_item' => $this->food_item,
            'food_category' => $this->food_category,
            'brand_name' => $this->brand_name,
            'is_perishable' => (bool) $this->is_perishable,
            'collected_at' => $this->collected_at?->toIso8601String(),
            'finalized_at' => $this->finalized_at?->toIso8601String(),

            'witness' => [
                'name' => $this->witness_name,
                'cnic' => $this->witness_cnic,
            ],

            'premises' => new PremisesResource($this->whenLoaded('premises')),
            'fso' => [
                'id' => $this->fso_id,
                'name' => $this->whenLoaded('fso', fn () => $this->fso?->name),
            ],

            'parts' => SamplePartResource::collection($this->whenLoaded('parts')),
            'rapid_tests' => RapidTestResource::collection($this->whenLoaded('rapidTests')),

            'original_result' => $originalResult ? new LabResultResource($originalResult) : null,
            // The reference part only gets a lab_result when it is activated for a
            // retest, so its presence marks a retest (possibly still in progress).
            'retest_result' => $retestResult ? new LabResultResource($retestResult) : null,
            'final_verdict' => $finalVerdict?->value,
            'final_verdict_source' => $retestResult?->verdict !== null ? 'RETEST' : ($originalResult?->verdict !== null ? 'ORIGINAL' : null),

            'disputes' => DisputeResource::collection($this->whenLoaded('disputes')),

            'sop_violations' => SopViolationResource::collection(
                $this->parts->flatMap(fn ($p) => $p->sopViolations ?? collect())
            ),
        ];
    }
}
