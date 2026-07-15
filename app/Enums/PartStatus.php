<?php

namespace App\Enums;

/**
 * Denormalized current status of a sample part. Each transition is recorded as an
 * immutable custody_event. The full state machine is enforced in Phase 2 — this
 * enum only defines the vocabulary of states.
 */
enum PartStatus: string
{
    case COLLECTED = 'COLLECTED';
    case SEALED = 'SEALED';
    case IN_TRANSIT = 'IN_TRANSIT';
    case RECEIVED_REGISTRATION = 'RECEIVED_REGISTRATION';
    case BLIND_CODED = 'BLIND_CODED';
    case ASSIGNED_TO_SECTION = 'ASSIGNED_TO_SECTION';
    case TESTING = 'TESTING';
    case RESULT_ENTERED = 'RESULT_ENTERED';
    case VERIFIED = 'VERIFIED';
    case REPORT_ISSUED = 'REPORT_ISSUED';
    case IN_RETENTION = 'IN_RETENTION';
    case RELEASED_TO_FBO = 'RELEASED_TO_FBO';
    case ACTIVATED_FOR_RETEST = 'ACTIVATED_FOR_RETEST';
    case REJECTED = 'REJECTED';
    case DESTROYED = 'DESTROYED';

    public function label(): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $this->value)));
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
