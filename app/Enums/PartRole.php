<?php

namespace App\Enums;

/**
 * The three physical parts produced by every sampling event (the "Rule of Three").
 * Split in front of a witness from the food business.
 */
enum PartRole: string
{
    case LAB = 'LAB';
    case REFERENCE = 'REFERENCE';
    case FBO_COPY = 'FBO_COPY';

    public function label(): string
    {
        return match ($this) {
            self::LAB => 'Laboratory Sample',
            self::REFERENCE => 'Reference Sample',
            self::FBO_COPY => 'FBO Copy',
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
