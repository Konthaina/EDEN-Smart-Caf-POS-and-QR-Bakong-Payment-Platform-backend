<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyBakongTransactions extends Command
{
    protected $signature = 'app:verify-bakong-transactions';
    protected $description = 'Verify pending Bakong KHQR transactions using MD5 hash';

    public function handle()
    {
        Log::info('[Schedule] Bakong auto-verify ran at ' . now());

        $controller = app(\App\Http\Controllers\BakongController::class);
        $controller->verifyTransactionByMd5();

        $this->info('✅ Verification complete.');
    }
}
