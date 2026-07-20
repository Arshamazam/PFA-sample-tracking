<?php

namespace App\Listeners;

use App\Events\DisputeDecided;
use App\Jobs\SendSms;
use App\Models\Dispute;
use App\Support\Phone;

/**
 * (d) Dispute decided -> FBO: accepted (retest info) or rejected.
 */
class SendDisputeDecidedSms
{
    public function handle(DisputeDecided $event): void
    {
        $dispute = Dispute::with('samplingEvent')->find($event->disputeId);
        if ($dispute === null || ! Phone::isValidPkMobile($dispute->filed_by_phone)) {
            return;
        }

        $key = $event->accepted ? 'sms.dispute_accepted' : 'sms.dispute_rejected';

        SendSms::dispatch($dispute->filed_by_phone, __($key, [
            'ref' => $dispute->reference_no,
            'event' => $dispute->samplingEvent->event_code,
        ]), $event->accepted ? 'dispute_accepted' : 'dispute_rejected');
    }
}
