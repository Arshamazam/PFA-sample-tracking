<?php

namespace App\Events;

use App\Models\SamplePart;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a part's report PDF has been generated and it reaches REPORT_ISSUED.
 * Carries whether this was a retest (reference part) so listeners pick the right
 * template and final-verdict wording.
 */
class ReportIssued
{
    use Dispatchable;

    public function __construct(
        public readonly string $samplePartId,
        public readonly bool $isRetest,
    ) {
    }

    public function part(): ?SamplePart
    {
        return SamplePart::with(['labResult', 'samplingEvent.premises', 'samplingEvent.fso'])
            ->find($this->samplePartId);
    }
}
