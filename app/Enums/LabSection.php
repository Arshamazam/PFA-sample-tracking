<?php

namespace App\Enums;

/**
 * Laboratory sections a sample part can be routed to for analysis.
 */
enum LabSection: string
{
    case FAT_OIL = 'FAT_OIL';
    case MICROBIOLOGY = 'MICROBIOLOGY';
    case CHEMICAL = 'CHEMICAL';
    case GENERAL = 'GENERAL';

    public function label(): string
    {
        return match ($this) {
            self::FAT_OIL => 'Fat & Oil',
            self::MICROBIOLOGY => 'Microbiology',
            self::CHEMICAL => 'Chemical',
            self::GENERAL => 'General',
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
