<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return '✅ Laravel is running on Railway!';
});

Route::get('/test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'API is live',
    ]);
});


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
