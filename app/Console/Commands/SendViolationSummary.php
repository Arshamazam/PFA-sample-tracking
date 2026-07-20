<?php

namespace App\Console\Commands;

use App\Jobs\SendSms;
use App\Models\Setting;
use App\Models\SopViolation;
use App\Support\Phone;
use Illuminate\Console\Command;

/**
 * Batches SOP violations into at most one supervisor SMS per hour (scheduled),
 * rather than one message per violation.
 */
class SendViolationSummary extends Command
{
    protected $signature = 'sms:violation-summary {--hours=1 : Window to summarise}';

    protected $description = 'Send a batched SOP-violation summary SMS to the supervisor';

    public function handle(): int
    {
        $phone = Setting::get('supervisor_phone');

        if (! Phone::isValidPkMobile($phone)) {
            $this->info('No valid supervisor_phone configured; nothing sent.');

            return self::SUCCESS;
        }

        $since = now()->subHours((int) $this->option('hours'));
        $count = SopViolation::where('detected_at', '>=', $since)->count();

        if ($count === 0) {
            $this->info('No violations in the window; nothing sent.');

            return self::SUCCESS;
        }

        SendSms::dispatch($phone, __('sms.violation_summary', ['count' => $count]), 'violation_summary');
        $this->info("Queued supervisor summary for {$count} violation(s).");

        return self::SUCCESS;
    }
}
