<?php

namespace App\Services;

use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Enums\Verdict;
use App\Models\SamplePart;
use App\Models\SopViolation;
use App\Models\TestCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Management analytics computed from custody_events timestamps and results. Plain
 * Eloquent aggregates, each cached 10 minutes (file cache) — no chart library.
 */
class AnalyticsService
{
    private const CACHE_TTL = 600; // 10 minutes

    /**
     * Live pipeline: how many active samples sit in each public stage, and the
     * oldest one in each stage.
     *
     * @return array<string, array{count: int, oldest_hours: ?int, oldest_code: ?string}>
     */
    public function pipelineNow(): array
    {
        return Cache::remember('analytics:pipeline', self::CACHE_TTL, function () {
            $map = config('tracking.stages.map');
            $order = array_keys(config('tracking.stages.order'));

            $active = SamplePart::query()
                ->where('role', PartRole::LAB->value)
                ->whereNotIn('status', [PartStatus::REPORT_ISSUED->value, PartStatus::REJECTED->value])
                ->with('samplingEvent:id,event_code')
                ->get(['id', 'sampling_event_id', 'status', 'updated_at']);

            $stages = [];
            foreach ($order as $stageKey) {
                if ($stageKey === 'REPORT_ISSUED') {
                    continue;
                }
                $stages[$stageKey] = ['count' => 0, 'oldest_hours' => null, 'oldest_code' => null];
            }

            foreach ($active as $part) {
                $stage = $map[$part->status->value] ?? null;
                if ($stage === null || ! isset($stages[$stage])) {
                    continue;
                }
                $stages[$stage]['count']++;
                $hours = (int) $part->updated_at->diffInHours(now());
                if ($stages[$stage]['oldest_hours'] === null || $hours > $stages[$stage]['oldest_hours']) {
                    $stages[$stage]['oldest_hours'] = $hours;
                    $stages[$stage]['oldest_code'] = $part->samplingEvent?->event_code;
                }
            }

            return $stages;
        });
    }

    /**
     * Turnaround per pipeline segment, computed from custody_events, vs the expected
     * catalog TAT, plus an overdue list.
     *
     * @return array{segments: array<string, array{avg: ?float, median: ?float, max: ?float, n: int}>, expected_hours: ?int, overdue: array<int, array{event_code: string, hours: int}>}
     */
    public function tatReport(?Carbon $from, ?Carbon $to, ?string $section = null): array
    {
        $key = 'analytics:tat:'.md5(($from?->toDateString() ?? '').($to?->toDateString() ?? '').($section ?? ''));

        return Cache::remember($key, self::CACHE_TTL, function () use ($from, $to, $section) {
            $parts = SamplePart::query()
                ->where('role', PartRole::LAB->value)
                ->when($section, fn ($q) => $q->whereHas('labResult', fn ($r) => $r->where('lab_section', $section)))
                ->whereHas('samplingEvent', function ($q) use ($from, $to) {
                    $q->when($from, fn ($x) => $x->where('collected_at', '>=', $from))
                        ->when($to, fn ($x) => $x->where('collected_at', '<=', $to));
                })
                ->with(['custodyEvents:id,sample_part_id,status,created_at', 'samplingEvent:id,event_code,food_category'])
                ->get();

            $segments = [
                'collected_to_received' => [PartStatus::COLLECTED, PartStatus::RECEIVED_REGISTRATION],
                'received_to_testing' => [PartStatus::RECEIVED_REGISTRATION, PartStatus::TESTING],
                'testing_to_verdict' => [PartStatus::TESTING, PartStatus::VERIFIED],
                'verdict_to_report' => [PartStatus::VERIFIED, PartStatus::REPORT_ISSUED],
            ];

            $collected = array_fill_keys(array_keys($segments), []);
            $overdue = [];

            foreach ($parts as $part) {
                $at = $this->firstTimestamps($part);

                foreach ($segments as $name => [$start, $end]) {
                    if (isset($at[$start->value], $at[$end->value])) {
                        $collected[$name][] = $at[$start->value]->floatDiffInHours($at[$end->value]);
                    }
                }

                // Overdue: total collected->report exceeds expected catalog TAT.
                $expected = $this->expectedTat($part->samplingEvent?->food_category, $section);
                if ($expected !== null && isset($at[PartStatus::COLLECTED->value])) {
                    $endAt = $at[PartStatus::REPORT_ISSUED->value] ?? now();
                    $elapsed = (int) $at[PartStatus::COLLECTED->value]->diffInHours($endAt);
                    if (! isset($at[PartStatus::REPORT_ISSUED->value]) && $elapsed > $expected) {
                        $overdue[] = ['event_code' => $part->samplingEvent->event_code, 'hours' => $elapsed];
                    }
                }
            }

            $segmentStats = [];
            foreach ($collected as $name => $values) {
                $segmentStats[$name] = $this->stats($values);
            }

            usort($overdue, fn ($a, $b) => $b['hours'] <=> $a['hours']);

            return [
                'segments' => $segmentStats,
                'expected_hours' => $this->expectedTat(null, $section),
                'overdue' => array_slice($overdue, 0, 20),
            ];
        });
    }

    /**
     * SOP violation counts by type and resolution rate over a range.
     *
     * @return array{by_type: array<string, int>, total: int, resolved: int, resolution_rate: float}
     */
    public function sopSummary(?Carbon $from, ?Carbon $to): array
    {
        $key = 'analytics:sop:'.md5(($from?->toDateString() ?? '').($to?->toDateString() ?? ''));

        return Cache::remember($key, self::CACHE_TTL, function () use ($from, $to) {
            $q = SopViolation::query()
                ->when($from, fn ($x) => $x->where('detected_at', '>=', $from))
                ->when($to, fn ($x) => $x->where('detected_at', '<=', $to));

            $total = (clone $q)->count();
            $resolved = (clone $q)->whereNotNull('resolved_at')->count();
            $byType = (clone $q)->selectRaw('type, COUNT(*) as c')->groupBy('type')->pluck('c', 'type')->all();

            return [
                'by_type' => $byType,
                'total' => $total,
                'resolved' => $resolved,
                'resolution_rate' => $total > 0 ? round($resolved / $total * 100, 1) : 0.0,
            ];
        });
    }

    /**
     * Volume, verdict split, and the retest overturn rate (UNFIT -> FIT).
     *
     * @return array{per_week: array<string, int>, fit: int, unfit: int, fit_pct: float, retests: int, overturns: int, overturn_rate: float}
     */
    public function volume(?Carbon $from, ?Carbon $to): array
    {
        $key = 'analytics:volume:'.md5(($from?->toDateString() ?? '').($to?->toDateString() ?? ''));

        return Cache::remember($key, self::CACHE_TTL, function () use ($from, $to) {
            $labParts = SamplePart::query()
                ->where('role', PartRole::LAB->value)
                ->whereHas('samplingEvent', function ($q) use ($from, $to) {
                    $q->whereNotNull('finalized_at')
                        ->when($from, fn ($x) => $x->where('collected_at', '>=', $from))
                        ->when($to, fn ($x) => $x->where('collected_at', '<=', $to));
                })
                ->with(['labResult:id,sample_part_id,verdict', 'samplingEvent:id,collected_at'])
                ->get();

            $perWeek = [];
            $fit = $unfit = 0;
            foreach ($labParts as $part) {
                $week = $part->samplingEvent->collected_at?->format('o-\WW') ?? 'unknown';
                $perWeek[$week] = ($perWeek[$week] ?? 0) + 1;
                if ($part->labResult?->verdict === Verdict::FIT) {
                    $fit++;
                }
                if ($part->labResult?->verdict === Verdict::UNFIT) {
                    $unfit++;
                }
            }
            ksort($perWeek);

            // Retests: reference parts with a verdict; overturn = original UNFIT -> retest FIT.
            $retestRefs = SamplePart::query()
                ->where('role', PartRole::REFERENCE->value)
                ->whereHas('labResult', fn ($q) => $q->whereNotNull('verdict'))
                ->with(['labResult', 'samplingEvent.parts.labResult'])
                ->get();

            $retests = $retestRefs->count();
            $overturns = $retestRefs->filter(function ($ref) {
                $original = $ref->samplingEvent->parts->firstWhere('role', PartRole::LAB)?->labResult;

                return $original?->verdict === Verdict::UNFIT && $ref->labResult?->verdict === Verdict::FIT;
            })->count();

            $decided = $fit + $unfit;

            return [
                'per_week' => $perWeek,
                'fit' => $fit,
                'unfit' => $unfit,
                'fit_pct' => $decided > 0 ? round($fit / $decided * 100, 1) : 0.0,
                'retests' => $retests,
                'overturns' => $overturns,
                'overturn_rate' => $retests > 0 ? round($overturns / $retests * 100, 1) : 0.0,
            ];
        });
    }

    /**
     * First timestamp the part entered each status.
     *
     * @return array<string, Carbon>
     */
    private function firstTimestamps(SamplePart $part): array
    {
        $at = [];
        foreach ($part->custodyEvents->sortBy('created_at') as $ce) {
            $at[$ce->status->value] ??= $ce->created_at;
        }

        return $at;
    }

    private function expectedTat(?string $foodCategory, ?string $section): ?int
    {
        $tat = TestCatalog::query()
            ->when($foodCategory, fn ($q) => $q->where('food_category', $foodCategory))
            ->when($section, fn ($q) => $q->where('lab_section', $section))
            ->max('tat_hours');

        return $tat !== null ? (int) $tat : null;
    }

    /**
     * @param  array<int, float>  $values
     * @return array{avg: ?float, median: ?float, max: ?float, n: int}
     */
    private function stats(array $values): array
    {
        $n = count($values);
        if ($n === 0) {
            return ['avg' => null, 'median' => null, 'max' => null, 'n' => 0];
        }

        sort($values);
        $mid = intdiv($n, 2);
        $median = $n % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;

        return [
            'avg' => round(array_sum($values) / $n, 1),
            'median' => round($median, 1),
            'max' => round(max($values), 1),
            'n' => $n,
        ];
    }
}
