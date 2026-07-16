<?php

namespace App\Enums;

enum SopViolationType: string
{
    case SAME_DAY_TRANSFER = 'SAME_DAY_TRANSFER';
    case COLD_CHAIN_BREACH = 'COLD_CHAIN_BREACH';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::SAME_DAY_TRANSFER => 'Same-day transfer deadline missed',
            self::COLD_CHAIN_BREACH => 'Cold-chain temperature breach',
            self::OTHER => 'Other',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
