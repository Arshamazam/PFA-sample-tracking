<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds default application settings.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Window (in days) during which an FBO may file a dispute against an UNFIT verdict.
            'dispute_window_days' => '7',
            // Deadline by which same-day sample transfers must reach the registration section.
            'same_day_transfer_deadline' => '20:00',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
