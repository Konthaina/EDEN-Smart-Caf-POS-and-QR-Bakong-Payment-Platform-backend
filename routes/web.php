<?php

use Illuminate\Support\Facades\Route;
Route::get('/', function () {
    return view('welcome');
});

use App\Services\TelegramService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

Route::get('/test-telegram', function () {
    TelegramService::send("✅ Telegram bot connected and working!");
    return 'Sent!';
});

Route::get('/login', function () {
    return response()->json(['message' => 'Redirected to login (fallback).'], 401);
})->name('login');
Route::get('/test-schedule', function () {
    \App\Models\Notification::create([
        'title' => 'Test Schedule',
        'message' => 'Did this work?',
        'scheduled_at' => now()->addMinute(),
    ]);
    return 'Notification created for next minute!';
});

Route::get('/telegram-debug', function () {
    $token = config('services.telegram.bot_token');

    $response = Http::get("https://api.telegram.org/bot{$token}/getUpdates");

    return $response->json();
});
