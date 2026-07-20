<?php

namespace App\Listeners;

use App\Events\DisputeFiled;
use App\Jobs\SendSms;
use App\Models\Dispute;
use App\Support\Phone;

/**
 * (c) Dispute filed -> FBO confirmation with reference number.
 */
class SendDisputeFiledSms
{
    public function handle(DisputeFiled $event): void
    {
        $dispute = Dispute::with('samplingEvent')->find($event->disputeId);
        if ($dispute === null || ! Phone::isValidPkMobile($dispute->filed_by_phone)) {
            return;
        }

        SendSms::dispatch($dispute->filed_by_phone, __('sms.dispute_filed', [
            'ref' => $dispute->reference_no,
            'event' => $dispute->samplingEvent->event_code,
        ]), 'dispute_filed');
    }
}
