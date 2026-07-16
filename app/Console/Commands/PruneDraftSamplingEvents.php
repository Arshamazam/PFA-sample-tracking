<?php

namespace App\Console\Commands;

use App\Models\SamplingEvent;
use Illuminate\Console\Command;

/**
 * Flags sampling events that were left as drafts (never finalized) for more than
 * 24 hours. We FLAG, never delete — abandoned drafts and any collected parts must
 * remain auditable. Flagged drafts can then be surfaced for follow-up.
 */
class PruneDraftSamplingEvents extends Command
{
    protected $signature = 'sampling:prune-drafts {--hours=24 : Draft age threshold in hours}';

    protected $description = 'Flag stale, unfinalized draft sampling events (older than N hours) without deleting them';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $threshold = now()->subHours($hours);

        $flagged = SamplingEvent::query()
            ->whereNull('finalized_at')
            ->whereNull('stale_flagged_at')
            ->where('created_at', '<', $threshold)
            ->update(['stale_flagged_at' => now()]);

        $this->info("Flagged {$flagged} stale draft sampling event(s) older than {$hours}h.");

        return self::SUCCESS;
    }
}
