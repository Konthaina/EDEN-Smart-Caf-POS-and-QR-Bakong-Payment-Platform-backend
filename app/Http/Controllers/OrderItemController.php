<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    // 🔍 View all order items
    public function index()
    {
        return OrderItem::with(['order', 'menuItem'])->get();
    }

    // 🔍 View a single order item
    public function show(OrderItem $orderItem)
    {
        return $orderItem->load(['order', 'menuItem']);
    }

    // ❌ Delete an order item (if allowed)
    public function destroy(OrderItem $orderItem)
    {
        $orderItem->delete();
        return response()->json(['message' => 'Order item deleted.']);
    }
}
