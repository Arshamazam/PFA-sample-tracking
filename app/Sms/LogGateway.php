<?php

namespace App\Sms;

use App\Contracts\SmsGateway;
use App\Support\SmsResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Default local/testing driver — "sends" by writing to the log. Never touches a
 * real gateway, so it is safe in development and CI.
 */
class LogGateway implements SmsGateway
{
    public function send(string $to, string $message): SmsResult
    {
        Log::channel(config('sms.log_channel', 'stack'))
            ->info('[SMS:log] to '.$to.' — '.$message);

        return SmsResult::ok('log-'.Str::random(10));
    }

    public function name(): string
    {
        return 'log';
    }
}
