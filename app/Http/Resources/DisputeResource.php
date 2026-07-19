<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full, de-blinded dispute view for officers/admin — includes both the original
 * result and, once present, the retest result.
 *
 * @mixin \App\Models\Dispute
 */
class DisputeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = $this->samplingEvent;
        $original = $event?->parts->firstWhere('role', \App\Enums\PartRole::LAB)?->labResult;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'event_code' => $event?->event_code,
            'sampling_event_id' => $this->sampling_event_id,
            'filed_by_name' => $this->filed_by_name,
            'filed_by_phone' => $this->filed_by_phone,
            'filed_by_cnic' => $this->filed_by_cnic,
            'reason' => $this->reason,
            'filed_at' => $this->filed_at?->toIso8601String(),
            'decided_by_id' => $this->decided_by_id,
            'decided_by_name' => $this->whenLoaded('decidedBy', fn () => $this->decidedBy?->name),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'decision_notes' => $this->decision_notes,
            'original_result' => $original ? new LabResultResource($original) : null,
            'retest_result' => $this->retest_lab_result_id
                ? new LabResultResource($this->whenLoaded('retestLabResult', fn () => $this->retestLabResult))
                : null,
        ];
    }
}
