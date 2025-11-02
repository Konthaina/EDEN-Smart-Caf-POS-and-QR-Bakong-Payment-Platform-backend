<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\OrderExport;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderController extends Controller
{
    private const MAX_ORDERS_IN_WINDOW = 3;
    private const WINDOW_SECONDS       = 60;
    private const COOLDOWN_SECONDS     = 300;

    /**
     * List orders with server-side filters + sort + pagination.
     */
    public function index(Request $request)
    {
        $sortable = [
            'order_code'      => 'orders.order_code',
            'user.name'       => 'u.name',
            'discount_amount' => 'orders.discount_amount',
            'discount.code'   => 'd.code',
            'payment_method'  => 'orders.payment_method',
            'total_amount'    => 'orders.total_amount',
            'status'          => 'orders.status',
            'created_at'      => 'orders.created_at',
        ];

        $sortBy  = $request->query('sort_by', 'created_at');
        $sortCol = $sortable[$sortBy] ?? 'orders.created_at';
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $q      = trim((string) $request->query('q', ''));
        $from   = $request->query('from'); // YYYY-MM-DD
        $to     = $request->query('to');   // YYYY-MM-DD
        $status = $request->query('status', 'all');

        $query = Order::with([
                'user' => fn($w) => $w->withTrashed()->select('id', 'name', 'email', 'role_id', 'deleted_at'),
                'discount',
                'orderItems.menuItem',
                'orderItems.menuItemVariant',
            ])
            ->leftJoin('users as u', 'u.id', '=', 'orders.user_id')
            ->leftJoin('discounts as d', 'd.id', '=', 'orders.discount_id')
            ->select('orders.*')
            ->when($from, fn($qq) => $qq->whereDate('orders.created_at', '>=', $from))
            ->when($to,   fn($qq) => $qq->whereDate('orders.created_at', '<=', $to))
            ->when($status && $status !== 'all', fn($qq) => $qq->where('orders.status', $status))
            ->when($q !== '', function ($qq) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
                $qq->where(function ($sub) use ($like) {
                    $sub->where('orders.order_code', 'like', $like)
                        ->orWhere('u.name', 'like', $like)
                        ->orWhere('d.code', 'like', $like)
                        ->orWhere('orders.payment_method', 'like', $like);
                });
            })
            ->orderBy($sortCol, $sortDir);

        $perPage = max(1, (int) $request->get('per_page', 10));
        if ($request->has('per_page') || $request->has('page')) {
            return $query->paginate($perPage)->appends($request->query());
        }

        return $query->get();
    }

    /**
     * Get a single order (includes soft-deleted user).
     */
    public function show(Order $order)
    {
        return $order->load([
            'user' => fn($w) => $w->withTrashed()->select('id', 'name', 'email', 'role_id', 'deleted_at'),
            'discount',
            'orderItems.menuItem',
            'orderItems.menuItemVariant',
        ]);
    }

    /**
     * Update order status.
     */
    public function update(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:pending,completed,cancelled',
        ]);

        DB::beginTransaction();
        try {
            $order->status = $request->status;
            $order->save();

            DB::commit();
            return response()->json(['message' => 'Order status updated.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Order status update failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Order status update failed'], 500);
        }
    }

    /**
     * Export orders (Admin only). Supports CSV/PDF/Excel.
     */
    public function export(Request $request)
    {
        $user = $request->user();
        $roleName = strtolower($user->role->name ?? $user->role ?? '');
        if (!in_array($roleName, ['admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $status = $request->status;
        $from   = $request->from;
        $to     = $request->to;
        $format = $request->query('format', 'csv');

        $orders = Order::with([
                'user' => fn($w) => $w->withTrashed()->select('id', 'name', 'email'),
                'discount'
            ])
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('created_at', '<=', $to))
            ->when($status && $status !== 'all', fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        if ($format === 'excel') {
            return Excel::download(new OrderExport($orders), 'orders.xlsx');
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.orders_pdf', ['orders' => $orders]);
            return $pdf->download('orders.pdf');
        }

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
                    number_format((float) $order->discount_amount, 2),
                    $order->discount?->code ?? '-',
                    ucfirst($order->payment_method ?? '-'),
                    number_format((float) $order->total_amount, 2),
                    ucfirst($order->status),
                    $order->created_at->format('d/m/Y'),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=order_report.csv',
        ]);
    }

    /**
     * Create order (PENDING). No stock logic. Supports discount code or manual discount.
     */
    public function store(Request $request)
    {
        $this->enforceOrderRateLimit($request);

        $validated = $request->validate([
            'items'                          => 'required|array|min:1',
            'items.*.menu_item_id'           => 'required|exists:menu_items,id',
            'items.*.menu_item_variant_id'   => 'nullable|exists:menu_item_variants,id',
            'items.*.quantity'               => 'required|integer|min:1',
            'items.*.customizations'         => 'nullable|array',
            'items.*.note'                   => 'nullable|string',
            'code'                           => 'nullable|string',
            'rt_discount_percent'            => ['nullable', 'numeric', 'min:0', 'max:100', 'prohibits:code,rt_discount_amount'],
            'rt_discount_amount'             => ['nullable', 'numeric', 'min:0', 'prohibits:code,rt_discount_percent'],
        ]);

        $manualDiscountProvided = $request->filled('rt_discount_percent') || $request->filled('rt_discount_amount');
        if ($manualDiscountProvided) {
            $roleName = strtolower($request->user()->role->name ?? $request->user()->role ?? '');
            if (!in_array($roleName, ['admin', 'super admin', 'super_admin'])) {
                return response()->json(['message' => 'Unauthorized to apply manual discount.'], 403);
            }
        }

        DB::beginTransaction();
        try {
            $total          = 0.0;
            $discountModel  = null;
            $discountAmount = 0.0;

            if ($request->code && !$manualDiscountProvided) {
                $discountModel = Discount::where('code', strtoupper($request->code))
                    ->where('active', true)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                    })
                    ->first();

                if (!$discountModel) {
                    return response()->json(['message' => 'Invalid or expired discount code.'], 400);
                }
            }

            $taxRate      = (float) (function_exists('get_setting') ? get_setting('tax_rate', 0) : 0);
            $exchangeRate = (float) (function_exists('get_setting') ? get_setting('exchange_rate_usd_khr', 4100) : 4100);

            $order = Order::create([
                'user_id'         => $request->user()->id,
                'order_code'      => 'ORD-' . strtoupper(Str::random(8)),
                'total_amount'    => 0,
                'discount_id'     => $discountModel?->id,
                'discount_amount' => 0,
                'status'          => 'pending',
                'payment_method'  => null,
                'tax_rate'        => $taxRate,
                'exchange_rate'   => $exchangeRate,
                'tax_amount'      => 0,
                'total_khr'       => 0,
            ]);

            foreach ($validated['items'] as $line) {
                $menuItem = MenuItem::findOrFail($line['menu_item_id']);

                $variant  = null;
                if (!empty($line['menu_item_variant_id'])) {
                    $variant = MenuItemVariant::with('menuItem')
                        ->findOrFail($line['menu_item_variant_id']);

                    if ((int) $variant->menu_item_id !== (int) $menuItem->id) {
                        DB::rollBack();
                        return response()->json(['message' => 'Variant does not belong to the given menu item.'], 422);
                    }
                }

                $quantity  = (int) $line['quantity'];
                $unitPrice = $variant ? (float) $variant->final_price : (float) $menuItem->final_price;
                $subtotal  = $unitPrice * $quantity;

                OrderItem::create([
                    'order_id'             => $order->id,
                    'menu_item_id'         => $menuItem->id,
                    'menu_item_variant_id' => $variant?->id,
                    'quantity'             => $quantity,
                    'price'                => $unitPrice,
                    'subtotal'             => $subtotal,
                    'customizations'       => $line['customizations'] ?? null,
                    'note'                 => $line['note'] ?? null,
                ]);

                $total += $subtotal;
            }

            // Discount calculation
            if ($request->filled('rt_discount_percent')) {
                $percent        = (float) $request->rt_discount_percent;
                $discountAmount = round($total * ($percent / 100), 2);
                $discountModel  = null;
            } elseif ($request->filled('rt_discount_amount')) {
                $flat           = (float) $request->rt_discount_amount;
                $discountAmount = round(min($flat, $total), 2);
                $discountModel  = null;
            } elseif ($discountModel) {
                if ($discountModel->percentage) {
                    $discountAmount = round($total * ((float) $discountModel->percentage / 100), 2);
                } elseif ($discountModel->amount) {
                    $discountAmount = round(min((float) $discountModel->amount, $total), 2);
                }
            }

            $taxableBase = max(0, $total - $discountAmount);
            $taxAmount   = $taxRate > 0 ? round($taxableBase * $taxRate / 100, 2) : 0.0;
            $finalTotal  = round($taxableBase + $taxAmount, 2);
            $totalKhr    = (int) round($finalTotal * $exchangeRate);

            $order->update([
                'total_amount'    => $finalTotal,
                'discount_id'     => $discountModel?->id,
                'discount_amount' => $discountAmount,
                'tax_amount'      => $taxAmount,
                'total_khr'       => $totalKhr,
                'tax_rate'        => $taxRate,
                'exchange_rate'   => $exchangeRate,
            ]);

            $order->load([
                'user' => fn($w) => $w->withTrashed()->select('id', 'name'),
                'discount:id,code',
                'orderItems.menuItem',
                'orderItems.menuItemVariant',
            ]);

            DB::commit();

            return response()->json($order, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Order failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Order failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancel pending order.
     */
    public function cancel(Order $order)
    {
        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Only pending orders can be cancelled.'], 400);
        }

        $order->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Order cancelled.']);
    }

    /**
     * Simple AI reorder for the current user (no variants, no stock logic).
     */
    public function aiReorder(Request $request)
    {
        $this->enforceOrderRateLimit($request);

        $user = $request->user();

        $topItems = \App\Models\OrderItem::select('menu_item_id', DB::raw('COUNT(*) as total'))
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
            $taxRate      = (float) (function_exists('get_setting') ? get_setting('tax_rate', 0) : 0);
            $exchangeRate = (float) (function_exists('get_setting') ? get_setting('exchange_rate_usd_khr', 4100) : 4100);

            $order = Order::create([
                'user_id'        => $user->id,
                'order_code'     => 'ORD-' . strtoupper(Str::random(8)),
                'total_amount'   => 0,
                'status'         => 'pending',
                'payment_method' => null,
                'tax_rate'       => $taxRate,
                'exchange_rate'  => $exchangeRate,
                'tax_amount'     => 0,
                'total_khr'      => 0,
            ]);

            $total = 0.0;

            foreach ($topItems as $menuItemId) {
                $menuItem = MenuItem::find($menuItemId);
                if (!$menuItem) continue;

                $quantity = 1;
                $unit     = (float) $menuItem->final_price;
                $subtotal = $unit * $quantity;

                OrderItem::create([
                    'order_id'     => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity'     => $quantity,
                    'price'        => $unit,
                    'subtotal'     => $subtotal,
                ]);

                $total += $subtotal;
            }

            $taxAmount  = $taxRate > 0 ? round($total * $taxRate / 100, 2) : 0.0;
            $finalTotal = round($total + $taxAmount, 2);
            $totalKhr   = (int) round($finalTotal * $exchangeRate);

            $order->update([
                'total_amount'  => $finalTotal,
                'tax_amount'    => $taxAmount,
                'total_khr'     => $totalKhr,
                'tax_rate'      => $taxRate,
                'exchange_rate' => $exchangeRate,
            ]);

            $order->load([
                'user' => fn($w) => $w->withTrashed()->select('id', 'name'),
                'discount:id,code',
                'orderItems.menuItem',
            ]);

            DB::commit();

            return response()->json($order);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('AI reorder failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'AI reorder failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * NEW: finalize a PENDING order with CASH (USD or KHR).
     * POST /api/orders/{order}/pay-cash
     * body: { tendered_amount: number, currency: "USD"|"KHR" }
     */
    public function payCash(Request $request, Order $order)
    {
        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Only pending orders can be paid.'], 400);
        }

        $data = $request->validate([
            'tendered_amount' => ['required', 'numeric', 'min:0'],
            'currency'        => ['required', Rule::in(['USD','KHR'])],
        ]);

        $rate    = max(1, (float) $order->exchange_rate); // 1 USD = X KHR
        $dueUsd  = (float) $order->total_amount;
        $dueKhr  = (int) round($dueUsd * $rate);

        $cashUsd = null;
        $cashKhr = null;
        $chgUsd  = 0.00;
        $chgKhr  = 0;

        if ($data['currency'] === 'USD') {
            $cashUsd = (float) $data['tendered_amount'];
            $cashKhr = (int) round($cashUsd * $rate);

            $chgUsd = round(max(0, $cashUsd - $dueUsd), 2);
            $chgKhr = (int) round($chgUsd * $rate);
        } else { // KHR
            $cashKhr = (int) round($data['tendered_amount']);
            $cashUsd = round($cashKhr / $rate, 2);

            $chgKhr = max(0, $cashKhr - $dueKhr);
            $chgUsd = round($chgKhr / $rate, 2);
        }

        if (($data['currency'] === 'USD' && $cashUsd + 1e-9 < $dueUsd) ||
            ($data['currency'] === 'KHR' && $cashKhr < $dueKhr)) {
            return response()->json(['message' => 'Insufficient cash provided.'], 422);
        }

        $order->update([
            'payment_method'    => 'cash',
            'tendered_currency' => $data['currency'],
            'cash_tendered_usd' => $cashUsd,
            'cash_tendered_khr' => $cashKhr,
            'change_usd'        => $chgUsd,
            'change_khr'        => $chgKhr,
            'paid_at'           => now(),
            'status'            => 'completed',
        ]);

        return $order->load([
            'user' => fn($w) => $w->withTrashed()->select('id','name','email','role_id','deleted_at'),
            'discount',
            'orderItems.menuItem',
            'orderItems.menuItemVariant',
        ]);
    }

    /**
     * Anti-spam for Customer/Table roles.
     */
    private function enforceOrderRateLimit(Request $request): void
    {
        $user     = $request->user();
        $roleName = strtolower($user->role->name ?? $user->role ?? '');

        if (!in_array($roleName, ['customer', 'table'])) {
            return;
        }

        $actorId     = $user->id ?? $request->ip();
        $attemptKey  = "order:attempts:{$actorId}";
        $cooldownKey = "order:cooldown:{$actorId}";

        if ($until = Cache::get($cooldownKey)) {
            $remaining = Carbon::now()->diffInSeconds(Carbon::parse($until), false);
            if ($remaining > 0) {
                $mins = (int) ceil($remaining / 60);
                abort(429, "Too many orders. Please wait {$mins} minute(s) and try again.");
            } else {
                Cache::forget($cooldownKey);
            }
        }

        if (RateLimiter::tooManyAttempts($attemptKey, self::MAX_ORDERS_IN_WINDOW)) {
            $until = Carbon::now()->addSeconds(self::COOLDOWN_SECONDS);
            Cache::add($cooldownKey, $until, self::COOLDOWN_SECONDS);
            RateLimiter::clear($attemptKey);

            $mins = (int) ceil(self::COOLDOWN_SECONDS / 60);
            abort(429, "Order limit exceeded. Please wait {$mins} minute(s) before placing another order.");
        }

        RateLimiter::hit($attemptKey, self::WINDOW_SECONDS);
    }
}
