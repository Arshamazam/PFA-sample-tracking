<?php

namespace App\Http\Resources;

use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Enums\Verdict;
use App\Models\LabResult;
use App\Models\SamplePart;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * THE PUBLIC WALL.
 *
 * The ONLY representation of an event shown on the unauthenticated tracking site.
 * Everything here is allow-listed; nothing identifying an official, a seal, a blind
 * code, a temperature, a GPS point, a witness, an SOP violation, or an individual
 * test PARAMETER may appear (verdict only). `tests/Feature/PublicWallTest.php`
 * recursively scans the payload for forbidden keys — extend it, never weaken it.
 *
 * @mixin \App\Models\SamplingEvent
 */
class PublicEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labPart = $this->parts->firstWhere('role', PartRole::LAB);
        $referencePart = $this->parts->firstWhere('role', PartRole::REFERENCE);

        $original = $labPart?->labResult;
        $retest = $referencePart?->labResult;

        $reportIssued = $labPart?->status === PartStatus::REPORT_ISSUED;
        $closedDispute = $this->disputes->firstWhere('status', \App\Enums\DisputeStatus::CLOSED);
        $afterRetest = $closedDispute !== null && $retest?->verdict !== null;

        // Verdict is public ONLY once the report is issued.
        $finalVerdict = null;
        if ($reportIssued) {
            $finalVerdict = $afterRetest ? $retest?->verdict : $original?->verdict;
        }

        return [
            'event_code' => $this->event_code,
            'food_item' => $this->food_item,
            'brand_name' => $this->brand_name,
            'premises' => [
                'name' => $this->premises?->name,
                'city' => $this->premises?->city,
            ],
            'license_no' => $this->premises?->license_no,
            'collected_on' => $this->collected_at?->toDateString(),

            'timeline' => $this->publicTimeline($labPart),
            'current_stage' => $labPart ? $this->publicStageLabel($labPart->status) : null,

            'report_issued' => $reportIssued,
            'verdict' => $finalVerdict?->value,
            'verdict_label' => $finalVerdict === null ? null
                : ($finalVerdict === Verdict::FIT ? 'Fit for use' : 'Unfit for use'),
            'after_retest' => $afterRetest,
            'report_photo_url' => ($reportIssued && $this->reportPhotoPart($labPart, $referencePart, $afterRetest))
                ? route('track.report-photo', ['part' => $this->reportPhotoPart($labPart, $referencePart, $afterRetest)->id])
                : null,

            'dispute_window' => $this->disputeWindow($labPart, $original, $afterRetest),
        ];
    }

    /**
     * Reached public stages with the timestamp each was first entered.
     *
     * @return array<int, array{stage: string, label: string, at: ?string}>
     */
    private function publicTimeline(?SamplePart $labPart): array
    {
        if ($labPart === null) {
            return [];
        }

        $map = config('tracking.stages.map');
        $order = config('tracking.stages.order');

        // Earliest custody timestamp per public stage.
        $firstAt = [];
        foreach ($labPart->custodyEvents->sortBy('created_at') as $ce) {
            $stage = $map[$ce->status->value] ?? null;
            if ($stage !== null && ! isset($firstAt[$stage])) {
                $firstAt[$stage] = $ce->created_at;
            }
        }

        $timeline = [];
        foreach ($order as $stageKey => $label) {
            if (isset($firstAt[$stageKey])) {
                $timeline[] = [
                    'stage' => $stageKey,
                    'label' => $label,
                    'at' => $firstAt[$stageKey]?->toIso8601String(),
                ];
            }
        }

        return $timeline;
    }

    private function publicStageLabel(PartStatus $status): ?string
    {
        $stage = config('tracking.stages.map')[$status->value] ?? null;

        return $stage ? (config('tracking.stages.order')[$stage] ?? null) : null;
    }

    /**
     * Which part's report photo to show (the retest report supersedes the original).
     */
    private function reportPhotoPart(?SamplePart $labPart, ?SamplePart $referencePart, bool $afterRetest): ?SamplePart
    {
        $part = $afterRetest ? $referencePart : $labPart;

        return ($part?->status === PartStatus::REPORT_ISSUED && $part->labResult?->report_photo_path)
            ? $part
            : null;
    }

    /**
     * Public note about the resampling window on an UNFIT verdict.
     *
     * @return array{open: bool, until: ?string}|null
     */
    private function disputeWindow(?SamplePart $labPart, ?LabResult $original, bool $afterRetest): ?array
    {
        if ($afterRetest || $labPart?->status !== PartStatus::REPORT_ISSUED
            || $original?->verdict !== Verdict::UNFIT || $original->verdict_at === null) {
            return null;
        }

        $windowDays = (int) Setting::get('dispute_window_days', '7');
        $until = $original->verdict_at->copy()->addDays($windowDays);

        return [
            'open' => now()->lessThanOrEqualTo($until),
            'until' => $until->toDateString(),
        ];
    }
}
