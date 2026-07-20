<?php

namespace App\Sms;

use App\Contracts\SmsGateway;
use App\Support\SmsResult;

/**
 * Discards messages silently. Useful to fully disable SMS without touching triggers.
 */
class NullGateway implements SmsGateway
{
    public function send(string $to, string $message): SmsResult
    {
        return SmsResult::ok();
    }

    public function name(): string
    {
        return 'null';
    }
}
