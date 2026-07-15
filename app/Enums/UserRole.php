<?php

namespace App\Enums;

/**
 * Staff roles in the sampling / chain-of-custody workflow.
 *
 * NOTE: These are interim roles pending integration with the PFA staff database
 * (same pattern as the PFA Warehouse system).
 */
enum UserRole: string
{
    case FSO = 'FSO';                                  // Food Safety Officer (field sampling)
    case TRANSPORT = 'TRANSPORT';                      // Carries sealed parts to registration
    case REGISTRATION_OFFICER = 'REGISTRATION_OFFICER'; // Receives, assigns blind codes
    case LAB_ANALYST = 'LAB_ANALYST';                  // Tests against blind code only
    case VERIFYING_OFFICER = 'VERIFYING_OFFICER';      // Verifies results, issues verdict
    case ADMIN = 'ADMIN';

    public function label(): string
    {
        return match ($this) {
            self::FSO => 'Food Safety Officer',
            self::TRANSPORT => 'Transport Officer',
            self::REGISTRATION_OFFICER => 'Registration Officer',
            self::LAB_ANALYST => 'Lab Analyst',
            self::VERIFYING_OFFICER => 'Verifying Officer',
            self::ADMIN => 'Administrator',
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
