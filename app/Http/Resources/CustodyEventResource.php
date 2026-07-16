<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CustodyEvent
 */
class CustodyEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'actor' => $this->when(
                $this->relationLoaded('actor') && $this->actor !== null,
                fn () => [
                    'id' => $this->actor->id,
                    'name' => $this->actor->name,
                    'role' => $this->actor->role->value,
                ],
            ),
            'actor_id' => $this->actor_id,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'location_note' => $this->location_note,
            'temperature_c' => $this->temperature_c !== null ? (float) $this->temperature_c : null,
            'photo_path' => $this->photo_path,
            'photo_url' => $this->photo_path
                ? route('files.show', ['path' => $this->photo_path])
                : null,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
