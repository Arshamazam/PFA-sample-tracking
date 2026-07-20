<?php

// SMS templates. Keep each <= 160 characters (one segment). Tracking links use the
// shortened /t/{event_code} redirect. Urdu variants live in lang/ur/sms.php.
return [
    'report_issued_fbo' => 'PFA: Test report for :event is ready. Result: :verdict. Track: :link',
    'report_issued_fso' => 'PFA: Sample :event report issued. Verdict: :verdict.',
    'retest_report_fbo' => 'PFA: Final result for :event after retest: :verdict. Track: :link',
    'dispute_filed' => 'PFA: Resampling application :ref for :event received. You will be notified of the decision.',
    'dispute_accepted' => 'PFA: Resampling :ref for :event ACCEPTED. The reference sample will be retested.',
    'dispute_rejected' => 'PFA: Resampling :ref for :event was not accepted. Contact the PFA office for details.',
    'violation_summary' => 'PFA alert: :count SOP violation(s) recorded in the last hour. Please review in the panel.',
];
