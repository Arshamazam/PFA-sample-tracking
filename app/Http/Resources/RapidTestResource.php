<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\RapidTest
 */
class RapidTestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sampling_event_id' => $this->sampling_event_id,
            'premises' => new PremisesResource($this->whenLoaded('premises')),
            'premises_id' => $this->premises_id,
            'device' => $this->device->value,
            'device_label' => $this->device->label(),
            'reading' => $this->reading,
            'passed' => $this->passed,
            'photo_path' => $this->photo_path,
            'photo_url' => $this->photo_path
                ? route('files.show', ['path' => $this->photo_path])
                : null,
            'tested_at' => $this->tested_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
