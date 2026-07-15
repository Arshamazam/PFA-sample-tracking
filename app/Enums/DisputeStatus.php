<?php

namespace App\Enums;

enum DisputeStatus: string
{
    case FILED = 'FILED';
    case ACCEPTED = 'ACCEPTED';
    case REJECTED = 'REJECTED';
    case RETEST_IN_PROGRESS = 'RETEST_IN_PROGRESS';
    case CLOSED = 'CLOSED';

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
