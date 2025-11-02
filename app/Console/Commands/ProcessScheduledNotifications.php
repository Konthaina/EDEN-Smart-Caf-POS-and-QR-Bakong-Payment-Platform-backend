<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessScheduledNotifications extends Command
{

    protected $signature = 'notifications:process';
    protected $description = 'Process scheduled and recurring notifications';

    public function handle()
    {
        $now = Carbon::now();
        Log::info('Processing scheduled notifications...');

        // Process one-time scheduled notifications (not recurring)
        $oneTime = Notification::where('recurring', false)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->get();

        foreach ($oneTime as $notification) {
            // Optionally: set a "sent" flag if you want, or just let frontend show based on scheduled_at
            // Example: $notification->update(['sent' => true]);
        }

        // Process recurring notifications
        $recurrings = Notification::where('recurring', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->get();

        foreach ($recurrings as $notification) {
            // Calculate next run time
            $next = null;
            switch ($notification->recurring_type) {
                case 'daily':
                    $next = Carbon::parse($notification->next_run_at)->addDay();
                    break;
                case 'weekly':
                    $weekday = $notification->recurring_value ?: 'monday'; // e.g. "monday"
                    $next = Carbon::parse($notification->next_run_at)->next($weekday);
                    break;
                case 'monthly':
                    $next = Carbon::parse($notification->next_run_at)->addMonth();
                    break;
                default:
                    $next = null;
            }
            $notification->update(['next_run_at' => $next]);
        }

        $this->info('Processed scheduled and recurring notifications.');
    }
}
