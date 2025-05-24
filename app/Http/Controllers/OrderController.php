<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MenuItem;
use App\Models\Stock;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\OrderExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    // 🔍 Get all orders
    public function index()
    {
        return Order::with(['user', 'orderItems.menuItem'])->get();
    }

    // 🔍 Get a single order
    public function show(Order $order)
    {
        return $order->load(['user', 'discount','orderItems.menuItem']);

    }
    public function update(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:pending,completed,cancelled',
        ]);

        $order->status = $request->status;
        $order->save();

        return response()->json(['message' => 'Order status updated.']);
    }

    public function export(Request $request)
    {
        $user = $request->user();

        // ✅ Role-based access (Admin only)
        if (!$user || $user->role->name !== 'Admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $status = $request->status;
        $from = $request->from;
        $to = $request->to;
        $format = $request->query('format', 'csv');

        $orders = Order::with('discount')
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
            ->when($status && $status !== 'all', fn($q) => $q->where('status', $status))
            ->get();

        if ($format === 'excel') {
            return Excel::download(new OrderExport($orders), 'orders.xlsx');
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.orders_pdf', ['orders' => $orders]);
            return $pdf->download('orders.pdf');
        }

        // Default CSV fallback
        $csvHeader = [
            'Order Code',
            'User ID',
            'Discount',
            'Promo Code',
            'Payment Method',
            'Total Amount',
            'Status',
            'Date'
        ];

        $callback = function () use ($orders, $csvHeader) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $csvHeader);

            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->order_code,
                    $order->user_id,
                    number_format($order->discount_amount, 2),
                    $order->discount?->code ?? '-',
                    ucfirst($order->payment_method ?? '-'),
                    number_format($order->total_amount, 2),
                    ucfirst($order->status),
                    $order->created_at->format('d/m/Y'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename=order_report.csv",
        ]);
    }

    // 🧾 Place a new order with optional discount
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'code' => 'nullable|string'
        ]);

        DB::beginTransaction();

        try {
            $total = 0;
            $discount = null;
            $discountAmount = 0;

            // 🔍 Validate discount code
            if ($request->code) {
                $discount = Discount::where('code', strtoupper($request->code))
                    ->where('active', true)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                    })
                    ->first();

                if (!$discount) {
                    return response()->json(['message' => 'Invalid or expired discount code.'], 400);
                }
            }

            $order = Order::create([
                'user_id' => $request->user()->id,
                'order_code' => 'ORD-' . strtoupper(Str::random(8)),
                'total_amount' => 0,
                'discount_id' => null,
                'discount_amount' => 0,
                'status' => 'pending',
                'payment_method' => null,
            ]);

            foreach ($validated['items'] as $item) {
                $menuItem = MenuItem::with('recipes')->findOrFail($item['menu_item_id']);
                $quantity = $item['quantity'];
                $subtotal = $menuItem->price * $quantity;

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $quantity,
                    'price' => $menuItem->price,
                    'subtotal' => $subtotal,
                ]);

                $total += $subtotal;

                foreach ($menuItem->recipes as $recipe) {
                    $requiredQty = $recipe->quantity * $quantity;
                    $stock = Stock::where('ingredient_id', $recipe->ingredient_id)->first();

                    if (!$stock || $stock->quantity < $requiredQty) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Insufficient stock for ingredient: ' . $recipe->ingredient->name
                        ], 400);
                    }

                    $stock->quantity -= $requiredQty;
                    $stock->save();
                }
            }

            // 💸 Apply discount
            if ($discount) {
                if ($discount->percentage) {
                    $discountAmount = $total * ($discount->percentage / 100);
                } elseif ($discount->amount) {
                    $discountAmount = min($discount->amount, $total);
                }
            }

            $order->update([
                'total_amount' => $total - $discountAmount,
                'discount_id' => $discount?->id,
                'discount_amount' => $discountAmount
            ]);

            DB::commit();
            return response()->json($order->load('orderItems.menuItem'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Order failed', 'error' => $e->getMessage()], 500);
        }
    }

    public function pay(Request $request, Order $order)
    {
        $request->validate([
            'payment_method' => 'required|in:cash,aba,bakong'
        ]);

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Only pending orders can be paid.'], 400);
        }

        $order->update([
            'payment_method' => $request->payment_method,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        // ✅ Add loyalty points if the user is a Customer
        $user = $order->user;

        if ($user && $user->role && $user->role->name === 'Customer') {
            $earnedPoints = floor($order->total_amount); // 1 point per $1
            $user->increment('points', $earnedPoints);
        }

        return response()->json(['message' => 'Order marked as paid and points awarded.']);
    }

    public function cancel(Order $order)
    {
        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Only pending orders can be cancelled.'], 400);
        }

        DB::beginTransaction();

        try {
            foreach ($order->orderItems as $item) {
                $recipes = $item->menuItem->recipes;

                foreach ($recipes as $recipe) {
                    $restoredQty = $recipe->quantity * $item->quantity;

                    $stock = Stock::firstOrCreate(['ingredient_id' => $recipe->ingredient_id]);
                    $stock->quantity += $restoredQty;
                    $stock->save();
                }
            }

            $order->update(['status' => 'cancelled']);

            DB::commit();
            return response()->json(['message' => 'Order cancelled and stock restored.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to cancel order.', 'error' => $e->getMessage()], 500);
        }
    }

    public function aiReorder(Request $request)
    {
        $user = $request->user();

        $topItems = OrderItem::select('menu_item_id', DB::raw('COUNT(*) as total'))
            ->whereHas('order', fn($q) => $q->where('user_id', $user->id)->where('status', 'completed'))
            ->groupBy('menu_item_id')
            ->orderByDesc('total')
            ->take(3)
            ->pluck('menu_item_id');

        if ($topItems->isEmpty()) {
            return response()->json(['message' => 'No frequent items found.'], 404);
        }

        DB::beginTransaction();

        try {
            $order = Order::create([
                'user_id' => $user->id,
                'order_code' => 'ORD-' . strtoupper(Str::random(8)),
                'total_amount' => 0,
                'status' => 'pending',
                'payment_method' => null,
            ]);

            $total = 0;

            foreach ($topItems as $menuItemId) {
                $menuItem = MenuItem::with('recipes')->find($menuItemId);
                if (!$menuItem) continue;

                $quantity = 1;
                $subtotal = $menuItem->price * $quantity;

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $quantity,
                    'price' => $menuItem->price,
                    'subtotal' => $subtotal,
                ]);

                foreach ($menuItem->recipes as $recipe) {
                    $requiredQty = $recipe->quantity * $quantity;
                    $stock = Stock::where('ingredient_id', $recipe->ingredient_id)->first();

                    if (!$stock || $stock->quantity < $requiredQty) {
                        DB::rollBack();
                        return response()->json(['message' => 'Insufficient stock for ' . $menuItem->name], 400);
                    }

                    $stock->quantity -= $requiredQty;
                    $stock->save();
                }

                $total += $subtotal;
            }

            $order->update(['total_amount' => $total]);

            DB::commit();
            return response()->json($order->load('orderItems.menuItem'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'AI reorder failed', 'error' => $e->getMessage()], 500);
        }
    }
}
