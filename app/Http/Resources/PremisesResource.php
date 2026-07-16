<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Premises
 */
class PremisesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'license_no' => $this->license_no,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'owner_name' => $this->owner_name,
            'owner_phone' => $this->owner_phone,
            'source' => $this->source,
        ];
    }
}
