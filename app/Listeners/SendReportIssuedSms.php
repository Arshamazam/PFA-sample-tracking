<?php

namespace App\Listeners;

use App\Events\ReportIssued;
use App\Jobs\SendSms;
use App\Support\Phone;

/**
 * (a) Report issued -> FBO (verdict + tracking link)
 * (b) Report issued -> collecting FSO (event code + verdict)
 * (e) Retest report issued -> FBO (final verdict + link)
 */
class SendReportIssuedSms
{
    public function handle(ReportIssued $event): void
    {
        $part = $event->part();
        $result = $part?->labResult;

        if ($part === null || $result?->verdict === null) {
            return;
        }

        $eventCode = $part->samplingEvent->event_code;
        $verdict = $result->verdict->value;
        $link = url('/t/'.$eventCode);
        $ownerPhone = $part->samplingEvent->premises->owner_phone;
        $fsoPhone = $part->samplingEvent->fso?->phone;

        if ($event->isRetest) {
            if (Phone::isValidPkMobile($ownerPhone)) {
                SendSms::dispatch($ownerPhone, __('sms.retest_report_fbo', [
                    'event' => $eventCode, 'verdict' => $verdict, 'link' => $link,
                ]), 'retest_report_fbo');
            }

            return;
        }

        if (Phone::isValidPkMobile($ownerPhone)) {
            SendSms::dispatch($ownerPhone, __('sms.report_issued_fbo', [
                'event' => $eventCode, 'verdict' => $verdict, 'link' => $link,
            ]), 'report_issued_fbo');
        }

        if (Phone::isValidPkMobile($fsoPhone)) {
            SendSms::dispatch($fsoPhone, __('sms.report_issued_fso', [
                'event' => $eventCode, 'verdict' => $verdict,
            ]), 'report_issued_fso');
        }
    }
}
