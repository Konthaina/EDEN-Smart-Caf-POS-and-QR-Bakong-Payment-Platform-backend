<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MenuItem;
use App\Models\Order;

class POSController extends Controller
{
    // Show POS Menu items (with stock info)
    public function menuItems()
    {
        $items = MenuItem::with('category', 'stock')->get();
        return response()->json($items);
    }

    // Create POS order
    public function createOrder(Request $request)
    {
        // Validate and store order, items, and payment (simplified)
        $validated = $request->validate([
            'items' => 'required|array',
            'total' => 'required|numeric',
            // add more as needed
        ]);
        // You would implement order storage logic here
        $order = Order::create([
            'total' => $validated['total'],
            'created_by' => $request->user()->id,
            // add more fields
        ]);
        // Add order items...
        // foreach ($validated['items'] as $item) { ... }

        return response()->json(['order' => $order], 201);
    }

    // Optionally: View today's orders, check receipts, etc.
}
