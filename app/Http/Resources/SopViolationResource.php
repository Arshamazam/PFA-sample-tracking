<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SopViolation
 */
class SopViolationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sample_part_id' => $this->sample_part_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'details' => $this->details,
            'detected_at' => $this->detected_at?->toIso8601String(),
            'resolved' => $this->resolved_at !== null,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolved_by_id' => $this->resolved_by_id,
            'resolution_notes' => $this->resolution_notes,
        ];
    }
}
