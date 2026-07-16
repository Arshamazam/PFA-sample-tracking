<?php

namespace App\Console\Commands;

use App\Enums\PartStatus;
use App\Jobs\GenerateReportPdf;
use App\Models\SamplePart;
use Illuminate\Console\Command;

/**
 * Re-queues report generation for samples that were verified but whose report never
 * materialised (the PDF job failed or was lost). Safe to run repeatedly: the job is
 * idempotent and only acts on parts still at VERIFIED.
 */
class RetryFailedReports extends Command
{
    protected $signature = 'reports:retry-failed {--limit=50 : Maximum number of reports to re-queue}';

    protected $description = 'Re-dispatch report PDF generation for verified samples that have no report yet';

    public function handle(): int
    {
        $parts = SamplePart::query()
            ->where('status', PartStatus::VERIFIED->value)
            ->whereHas('labResult', fn ($q) => $q->whereNotNull('verdict')->whereNull('report_pdf_path'))
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($parts as $part) {
            GenerateReportPdf::dispatch($part->id);
            $this->line("Re-queued report for blind code {$part->blind_code}.");
        }

        $this->info("Re-queued {$parts->count()} report(s).");

        return self::SUCCESS;
    }
}
