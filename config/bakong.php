<?php

return [
    'base_url'     => env('BAKONG_BASE_URL', 'https://api-bakong.nbc.gov.kh/'),
    'fixed_token'  => env('BAKONG_FIXED_TOKEN', ''),
    // fallback merchant id from .env (used only if DB setting is empty)
    'merchant_id'  => env('BAKONG_MERCHANT_ID', ''),
    // the key name we store in the settings table
    'db_key'       => 'bakong_machine_id',
];
