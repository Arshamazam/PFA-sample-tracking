<?php

namespace App\Support;

/**
 * Outcome of an SMS send attempt.
 */
final class SmsResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $error = null,
    ) {
    }

    public static function ok(?string $providerMessageId = null): self
    {
        return new self(true, $providerMessageId, null);
    }

    public static function failed(string $error): self
    {
        return new self(false, null, $error);
    }
}
