<?php

use App\Models\Setting;

if (! function_exists('bakong_merchant_id')) {
    function bakong_merchant_id(): string
    {
        $dbKey = config('bakong.db_key', 'bakong_machine_id');
        return (string) Setting::getCached($dbKey, config('bakong.merchant_id', ''));
    }
}

if (! function_exists('setting')) {
    // generic helper if you ever need other settings
    function setting(string $key, $default = null) {
        return Setting::getCached($key, $default);
    }
}
