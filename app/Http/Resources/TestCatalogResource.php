<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TestCatalog
 */
class TestCatalogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'food_category' => $this->food_category,
            'lab_section' => $this->lab_section->value,
            'lab_section_label' => $this->lab_section->label(),
            'test_name' => $this->test_name,
            'parameters' => $this->parameters,
            'tat_hours' => $this->tat_hours,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
