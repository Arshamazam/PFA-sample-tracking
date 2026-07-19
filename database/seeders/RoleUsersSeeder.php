<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds one test user per role.
 *
 * TEMPORARY test accounts — these exist only until the PFA staff database is
 * integrated (same interim pattern as the PFA Warehouse system). Do NOT ship
 * these credentials to production.
 */
class RoleUsersSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserRole::cases() as $role) {
            $email = strtolower($role->value).'@pfa.test';

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $role->label().' (TEST)',
                    'password' => Hash::make('password'),
                    'role' => $role,
                    'phone' => null,
                    'cnic' => null,
                    'is_active' => true,
                    // Interim shared password must be rotated on first web login.
                    'must_change_password' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
