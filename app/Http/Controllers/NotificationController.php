<?php
namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // Get all notifications
    public function index()
    {
        return Notification::orderByDesc('created_at')->get();
    }

    // Get only active notifications (for customer)
    public function active()
    {
        $now = now();
        $notifications = Notification::where('read', false)
            ->where(function($q) use ($now) {
                $q->whereNull('scheduled_at')
                  ->orWhere('scheduled_at', '<=', $now);
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json($notifications);
    }

    // Store new notification (schedule or recurring)
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'nullable|string',
            'title' => 'required|string',
            'message' => 'required|string',
            'scheduled_at' => 'nullable|date',
            'recurring' => 'nullable|boolean',
            'recurring_type' => 'nullable|string|in:daily,weekly,monthly,custom',
            'recurring_value' => 'nullable|string',
        ]);

        $data = $request->only([
            'type', 'title', 'message', 'scheduled_at', 'recurring', 'recurring_type', 'recurring_value'
        ]);
        $data['read'] = false;
        $data['next_run_at'] = $request->recurring ? $request->scheduled_at : null;

        $notification = Notification::create($data);

        return response()->json([
            'message' => 'Notification created',
            'notification' => $notification
        ], 201);
    }

    // Mark as read
    public function markAsRead(Notification $notification)
    {
        $notification->update(['read' => true]);
        return response()->json(['message' => 'Notification marked as read']);
    }

    // Update notification
    public function update(Request $request, Notification $notification)
    {
        $request->validate([
            'type' => 'nullable|string',
            'title' => 'required|string',
            'message' => 'required|string',
            'scheduled_at' => 'nullable|date',
            'recurring' => 'nullable|boolean',
            'recurring_type' => 'nullable|string|in:daily,weekly,monthly,custom',
            'recurring_value' => 'nullable|string',
        ]);

        $data = $request->only([
            'type', 'title', 'message', 'scheduled_at', 'recurring', 'recurring_type', 'recurring_value'
        ]);
        $notification->update($data);

        return response()->json(['message' => 'Notification updated', 'notification' => $notification]);
    }

    // Delete notification
    public function destroy(Notification $notification)
    {
        $notification->delete();
        return response()->json(['message' => 'Notification deleted']);
    }
}
