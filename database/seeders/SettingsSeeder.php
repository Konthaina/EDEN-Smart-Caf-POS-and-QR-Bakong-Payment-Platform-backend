<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::updateOrCreate(['key' => 'shop_name'], ['value' => 'My Cafe']);
        Setting::updateOrCreate(['key' => 'shop_logo'], ['value' => '/logo.png']); // Fallback logo path
        Setting::updateOrCreate(['key' => 'tax_rate'], ['value' => '10']);
        Setting::updateOrCreate(['key' => 'exchange_rate_usd_khr'], ['value' => '4100']);

        // NEW: Bakong machine id (use env, fallback to empty string to avoid NULL constraint)
        Setting::updateOrCreate(
            ['key' => 'bakong_machine_id'],
            ['value' => env('BAKONG_MACHINE_ID', '')]
        );
    }
}
