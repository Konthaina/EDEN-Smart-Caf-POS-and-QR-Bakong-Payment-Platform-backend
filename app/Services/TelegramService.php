<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService
{
    public static function send(string $message): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (!$token || !$chatId) {
            logger()->warning('TelegramService: Missing bot token or chat ID');
            return;
        }

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML', // or 'MarkdownV2'
            ]);
        } catch (\Exception $e) {
            logger()->error('TelegramService Error: ' . $e->getMessage());
        }
    }
}
