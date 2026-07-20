<?php

namespace App\Contracts;

use App\Support\SmsResult;

/**
 * A swappable SMS gateway. Adding PFA's chosen provider later is a single new
 * class implementing this interface plus a config entry — nothing else changes.
 */
interface SmsGateway
{
    public function send(string $to, string $message): SmsResult;

    /** Short identifier used in the audit log (e.g. "log", "sendpk"). */
    public function name(): string;
}
