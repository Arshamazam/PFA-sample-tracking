<?php

namespace App\Http\Resources;

use App\Enums\PartStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A retained reference part with its retention age and destruction eligibility.
 * Officer/admin-facing, so it may reference the event and storage location.
 *
 * @mixin \App\Models\SamplePart
 */
class RetentionPartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $retainedAt = $this->custodyEvents
            ->firstWhere(fn ($e) => $e->status === PartStatus::IN_RETENTION);

        $eligibleAt = $this->destruction_eligible_at;

        return [
            'id' => $this->id,
            'qr_token' => $this->qr_token,
            'event_code' => $this->samplingEvent?->event_code,
            'status' => $this->status->value,
            'storage_location' => $retainedAt?->location_note,
            'retained_at' => $retainedAt?->created_at?->toIso8601String(),
            'days_retained' => $retainedAt?->created_at
                ? $retainedAt->created_at->diffInDays(now())
                : null,
            'destruction_eligible_at' => $eligibleAt?->toIso8601String(),
            'is_destruction_eligible' => $eligibleAt !== null && ! $eligibleAt->isFuture(),
        ];
    }
}
