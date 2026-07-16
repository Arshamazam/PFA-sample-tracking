<?php

namespace App\Jobs;

use App\Enums\PartStatus;
use App\Models\SamplePart;
use App\Services\CustodyStateMachine;
use App\Services\QrService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Renders the official test report PDF for a verified sample part.
 *
 * Queued deliberately: PDF rendering is too slow/memory-hungry to run inside a
 * request on shared hosting. If this job fails the part simply stays at VERIFIED
 * (the REPORT_ISSUED transition is the last step), and `reports:retry-failed` can
 * pick it up again.
 */
class GenerateReportPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $samplePartId)
    {
    }

    public function handle(CustodyStateMachine $custody, QrService $qr): void
    {
        $part = SamplePart::with([
            'labResult.analyst',
            'labResult.verifiedBy',
            'samplingEvent.premises',
            'custodyEvents',
        ])->find($this->samplePartId);

        if ($part === null) {
            return;
        }

        // Idempotency: only a verified, not-yet-issued part needs a report.
        if ($part->status !== PartStatus::VERIFIED) {
            return;
        }

        $labResult = $part->labResult;
        if ($labResult === null || $labResult->verdict === null) {
            return;
        }

        $event = $part->samplingEvent;

        $receivedAt = $part->custodyEvents
            ->firstWhere(fn ($e) => $e->status === PartStatus::RECEIVED_REGISTRATION)?->created_at;

        $pdf = Pdf::loadView('reports.sample-report', [
            'part' => $part,
            'event' => $event,
            'premises' => $event->premises,
            'labResult' => $labResult,
            'receivedAt' => $receivedAt,
            'qrDataUri' => $qr->svgDataUri($part, 120),
            'trackingUrl' => $qr->trackingUrl($part),
            'config' => config('pfa.report'),
        ])->setPaper('a4');

        $path = sprintf('reports/%s/%s.pdf', $event->event_code, $part->id);
        Storage::disk('local')->put($path, $pdf->output());

        $labResult->update(['report_pdf_path' => $path]);

        // System actor — the report is issued by the system, not a person.
        $custody->transition($part, PartStatus::REPORT_ISSUED, null, [
            'notes' => 'Test report generated and issued.',
        ]);
    }
}
