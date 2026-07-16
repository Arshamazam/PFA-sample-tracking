<?php

namespace App\Http\Resources;

use App\Enums\PartStatus;
use App\Models\TestCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * THE BLIND WALL.
 *
 * The ONLY representation of a sample part that may be shown to a LAB_ANALYST.
 * Analysts must never learn which business a sample came from, so this resource
 * exposes a deliberately minimal, allow-listed set of fields.
 *
 * Exposed: blind_code, food_category, food_item (generic name), is_perishable,
 * lab_section, status, received/assigned timestamps, parameter template.
 *
 * NEVER expose (directly or nested): qr_token, seal_number/seal photos,
 * anything about the premises (name, address, license_no), brand_name, witness
 * details, the FSO's identity, event_code, sampling_event id, or the part id.
 * Analysts address samples solely by blind_code.
 *
 * `tests/Feature/BlindWallTest.php` recursively scans every analyst-facing payload
 * for these forbidden keys and is the regression lock for this guarantee — extend
 * it, never weaken it.
 *
 * @mixin \App\Models\SamplePart
 */
class BlindSamplePartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = $this->samplingEvent;
        $labSection = $this->labResult?->lab_section;

        return [
            'blind_code' => $this->blind_code,
            'food_category' => $event?->food_category,
            'food_item' => $event?->food_item,
            'is_perishable' => (bool) $event?->is_perishable,
            'lab_section' => $labSection?->value,
            'lab_section_label' => $labSection?->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'received_at' => $this->custodyEventAt(PartStatus::RECEIVED_REGISTRATION),
            'assigned_at' => $this->custodyEventAt(PartStatus::ASSIGNED_TO_SECTION),
            'parameters_template' => $this->parametersTemplate(),
            // The analyst's own entered results (no verdict — that is not theirs).
            'parameters' => $this->labResult?->parameters,
        ];
    }

    /**
     * Timestamp of the first custody event that moved the part into a status.
     */
    private function custodyEventAt(PartStatus $status): ?string
    {
        $event = $this->custodyEvents
            ->firstWhere(fn ($e) => $e->status === $status);

        return $event?->created_at?->toIso8601String();
    }

    /**
     * The test_catalog parameter template for this sample's category + section.
     *
     * @return array<int, mixed>|null
     */
    private function parametersTemplate(): ?array
    {
        $category = $this->samplingEvent?->food_category;
        if ($category === null) {
            return null;
        }

        $query = TestCatalog::query()->where('food_category', $category);

        if ($this->labResult?->lab_section !== null) {
            $query->where('lab_section', $this->labResult->lab_section);
        }

        return $query->first()?->parameters;
    }
}
