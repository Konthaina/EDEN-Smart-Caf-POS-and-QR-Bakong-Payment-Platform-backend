<?php
namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        return Notification::latest()->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'nullable|string',
            'title' => 'required|string',
            'message' => 'required|string',
        ]);

        $notification = Notification::create([
            'type' => $request->type,
            'title' => $request->title,
            'message' => $request->message,
        ]);

        return response()->json(['message' => 'Notification created', 'notification' => $notification], 201);
    }

    public function markAsRead(Notification $notification)
    {
        $notification->update(['read' => true]);
        return response()->json(['message' => 'Notification marked as read']);
    }

    public function destroy(Notification $notification)
    {
        $notification->delete();
        return response()->json(['message' => 'Notification deleted']);
    }
}
