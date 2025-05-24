<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Services\TelegramService;

Route::get('/test-telegram', function () {
    TelegramService::send("✅ Telegram bot connected and working!");
    return 'Sent!';
});

Route::get('/login', function () {
    return response()->json(['message' => 'Redirected to login (fallback).'], 401);
})->name('login');
