<?php

namespace App\Enums;

/**
 * Field devices used for on-site rapid screening tests.
 */
enum RapidTestDevice: string
{
    case LACTOSCAN = 'LACTOSCAN';
    case OIL_TESTOMETER = 'OIL_TESTOMETER';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::LACTOSCAN => 'Lactoscan (Milk Analyzer)',
            self::OIL_TESTOMETER => 'Oil Testometer',
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
