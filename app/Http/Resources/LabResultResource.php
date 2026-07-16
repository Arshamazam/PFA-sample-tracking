<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FULL (de-blinded) result view. Only for verifying officers, registration, admin
 * and the owning FSO — never for analysts (they get BlindSamplePartResource).
 *
 * @mixin \App\Models\LabResult
 */
class LabResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_section' => $this->lab_section->value,
            'lab_section_label' => $this->lab_section->label(),
            'analyst_id' => $this->analyst_id,
            'analyst_name' => $this->whenLoaded('analyst', fn () => $this->analyst?->name),
            'verified_by_id' => $this->verified_by_id,
            'verified_by_name' => $this->whenLoaded('verifiedBy', fn () => $this->verifiedBy?->name),
            'parameters' => $this->parameters,
            'revision_count' => count($this->lab_result_revisions ?? []),
            'verdict' => $this->verdict?->value,
            'verdict_label' => $this->verdict?->label(),
            'verdict_at' => $this->verdict_at?->toIso8601String(),
            'report_photo_path' => $this->report_photo_path,
            'report_photo_url' => $this->report_photo_path
                ? route('files.show', ['path' => $this->report_photo_path])
                : null,
            'report_pdf_path' => $this->report_pdf_path,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
