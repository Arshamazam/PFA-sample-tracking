<?php

namespace App\Sms;

use App\Contracts\SmsGateway;
use App\Support\SmsResult;
use Illuminate\Support\Facades\Http;

/**
 * Example real-provider driver (a generic Pakistani SMS HTTP API). Credentials and
 * endpoint are config-driven; the exact request shape must be confirmed against the
 * provider PFA selects — this is a working template, not a verified integration.
 */
class SendPkGateway implements SmsGateway
{
    public function send(string $to, string $message): SmsResult
    {
        $config = config('sms.drivers.sendpk');

        try {
            $response = Http::timeout(15)
                ->asForm()
                ->post($config['endpoint'], [
                    'api_key' => $config['api_key'],
                    'sender' => $config['sender'],
                    'to' => $to,
                    'message' => $message,
                ]);

            if ($response->successful()) {
                return SmsResult::ok((string) $response->json('id', $response->body()));
            }

            return SmsResult::failed('HTTP '.$response->status().': '.$response->body());
        } catch (\Throwable $e) {
            return SmsResult::failed($e->getMessage());
        }
    }

    public function name(): string
    {
        return 'sendpk';
    }
}
