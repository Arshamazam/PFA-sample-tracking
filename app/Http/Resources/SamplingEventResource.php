<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SamplingEvent
 */
class SamplingEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_code' => $this->event_code,
            // Derived lifecycle status for the field workflow.
            'status' => $this->finalized_at !== null ? 'FINALIZED' : 'DRAFT',
            'premises' => new PremisesResource($this->whenLoaded('premises')),
            'premises_id' => $this->premises_id,
            'fso_id' => $this->fso_id,
            'food_item' => $this->food_item,
            'food_category' => $this->food_category,
            'brand_name' => $this->brand_name,
            'is_perishable' => $this->is_perishable,
            'witness_name' => $this->witness_name,
            'witness_cnic' => $this->witness_cnic,
            'witness_signature_path' => $this->witness_signature_path,
            'witness_signature_url' => $this->witness_signature_path
                ? route('files.show', ['path' => $this->witness_signature_path])
                : null,
            'collected_at' => $this->collected_at?->toIso8601String(),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'stale_flagged_at' => $this->stale_flagged_at?->toIso8601String(),
            'parts' => SamplePartResource::collection($this->whenLoaded('parts')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
