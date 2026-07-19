<?php

namespace App\Console\Commands;

use App\Enums\DisputeStatus;
use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Enums\Verdict;
use App\Models\SamplePart;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Marks retained REFERENCE parts as eligible for destruction once their case is
 * settled — a FIT verdict, or an UNFIT verdict whose dispute window has closed with
 * no open dispute. It NEVER destroys anything; destruction is always a manual,
 * photographed custody event (POST /registration/destroy).
 */
class ProcessRetention extends Command
{
    protected $signature = 'retention:process';

    protected $description = 'Flag retained reference parts as destruction-eligible once their dispute window has settled';

    public function handle(): int
    {
        $windowDays = (int) Setting::get('dispute_window_days', '7');

        $parts = SamplePart::query()
            ->where('role', PartRole::REFERENCE->value)
            ->where('status', PartStatus::IN_RETENTION->value)
            ->whereNull('destruction_eligible_at')
            ->with(['samplingEvent.parts.labResult', 'samplingEvent.disputes'])
            ->get();

        $flagged = 0;

        foreach ($parts as $reference) {
            if ($this->isEligible($reference, $windowDays)) {
                $reference->update(['destruction_eligible_at' => Carbon::now()]);
                $flagged++;
                $this->line("Flagged reference part for event {$reference->samplingEvent->event_code} as destruction-eligible.");
            }
        }

        $this->info("Retention run complete: {$flagged} reference part(s) newly eligible for destruction.");

        return self::SUCCESS;
    }

    private function isEligible(SamplePart $reference, int $windowDays): bool
    {
        $event = $reference->samplingEvent;
        $labResult = $event->parts->firstWhere('role', PartRole::LAB)?->labResult;

        // No verdict yet — the case is not settled.
        if ($labResult?->verdict === null) {
            return false;
        }

        // A FIT verdict is never disputed; the reference can be released for destruction.
        if ($labResult->verdict === Verdict::FIT) {
            return true;
        }

        // UNFIT: eligible only once the window has closed AND no dispute is open.
        $expiresAt = $labResult->verdict_at?->copy()->addDays($windowDays);
        $windowClosed = $expiresAt !== null && Carbon::now()->greaterThan($expiresAt);

        $hasOpenDispute = $event->disputes->contains(
            fn ($d) => in_array($d->status, [
                DisputeStatus::FILED,
                DisputeStatus::ACCEPTED,
                DisputeStatus::RETEST_IN_PROGRESS,
            ], true)
        );

        return $windowClosed && ! $hasOpenDispute;
    }
}
