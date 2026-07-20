<?php

namespace App\Console\Commands;

use App\Services\AnalyticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Prints the same TAT / volume numbers the dashboard shows — handy for cron-mailed
 * management reports later.
 */
class AnalyticsTat extends Command
{
    protected $signature = 'analytics:tat {--from=} {--to=} {--section=}';

    protected $description = 'Print turnaround, volume, and quality analytics for a date range';

    public function handle(AnalyticsService $analytics): int
    {
        $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : null;
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->endOfDay() : null;
        $section = $this->option('section');

        $tat = $analytics->tatReport($from, $to, $section);
        $volume = $analytics->volume($from, $to);
        $sop = $analytics->sopSummary($from, $to);

        $this->info('Turnaround (hours) — expected catalog TAT: '.($tat['expected_hours'] ?? '—'));
        $this->table(['Segment', 'Avg', 'Median', 'Max', 'n'], collect($tat['segments'])->map(fn ($s, $k) => [
            $k, $s['avg'] ?? '—', $s['median'] ?? '—', $s['max'] ?? '—', $s['n'],
        ])->values()->all());

        $this->info("Volume — FIT {$volume['fit']} ({$volume['fit_pct']}%), UNFIT {$volume['unfit']}, "
            ."retests {$volume['retests']}, overturn rate {$volume['overturn_rate']}%");
        $this->info("SOP violations — total {$sop['total']}, resolved {$sop['resolved']} ({$sop['resolution_rate']}%)");
        $this->info('Overdue samples: '.count($tat['overdue']));

        return self::SUCCESS;
    }
}
