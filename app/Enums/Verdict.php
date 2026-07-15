<?php

namespace App\Enums;

enum Verdict: string
{
    case FIT = 'FIT';
    case UNFIT = 'UNFIT';

    public function label(): string
    {
        return match ($this) {
            self::FIT => 'Fit for Human Consumption',
            self::UNFIT => 'Unfit for Human Consumption',
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
