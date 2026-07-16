<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Field-side (FSO) view of a sample part — shows full detail including the seal
 * and QR payload for label printing.
 *
 * NOTE: Phase 3 introduces the "blind wall". The lab-facing view of a part must
 * hide business identity and expose only blind_code; that will be a SEPARATE
 * resource (or a role-conditioned variant) so this field view stays intact.
 *
 * @mixin \App\Models\SamplePart
 */
class SamplePartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sampling_event_id' => $this->sampling_event_id,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'qr_token' => $this->qr_token,
            'qr_svg_url' => route('sample-parts.qr', ['samplePart' => $this->id]),
            'tracking_url' => rtrim(config('app.url'), '/').'/track/p/'.$this->qr_token,
            'blind_code' => $this->blind_code,
            'seal_number' => $this->seal_number,
            'seal_photo_path' => $this->seal_photo_path,
            'seal_photo_url' => $this->seal_photo_path
                ? route('files.show', ['path' => $this->seal_photo_path])
                : null,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'custody_events' => CustodyEventResource::collection($this->whenLoaded('custodyEvents')),
            'lab_result' => new LabResultResource($this->whenLoaded('labResult')),
            'sampling_event' => new SamplingEventResource($this->whenLoaded('samplingEvent')),
            'sop_violations' => SopViolationResource::collection($this->whenLoaded('sopViolations')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
