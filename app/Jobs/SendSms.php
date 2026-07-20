<?php

namespace App\Jobs;

use App\Contracts\SmsGateway;
use App\Models\SmsLog;
use App\Support\Phone;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued SMS send. Every attempt (success or failure) is written to sms_logs for
 * audit. A failed send throws so the queue retries with backoff.
 */
class SendSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $to,
        public readonly string $message,
        public readonly ?string $trigger = null,
    ) {
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(SmsGateway $gateway): void
    {
        $to = Phone::normalize($this->to) ?? $this->to;

        // Trim to a single SMS segment; templates are authored <=160 chars.
        $message = mb_substr($this->message, 0, 160);

        $result = $gateway->send($to, $message);

        SmsLog::create([
            'to' => $to,
            'message' => $message,
            'driver' => $gateway->name(),
            'status' => $result->success ? 'SENT' : 'FAILED',
            'provider_message_id' => $result->providerMessageId,
            'error' => $result->error,
            'trigger' => $this->trigger,
            'sent_at' => $result->success ? now() : null,
        ]);

        if (! $result->success) {
            // Throwing lets the queue retry (up to $tries) with backoff.
            throw new \RuntimeException("SMS send failed via {$gateway->name()}: {$result->error}");
        }
    }
}
