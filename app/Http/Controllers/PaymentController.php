<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    // 🔍 List all payments (for admin)
    public function index()
    {
        return Payment::with('order.user')->latest()->get();
    }

    // 📦 View one payment
    public function show(Payment $payment)
    {
        return $payment->load(['order', 'logs']);
    }

    // 💰 Store a new payment (manual input or from frontend)
    public function store(Request $request)
    {
        Log::info('📥 Incoming Payment Request', $request->all());

        $validated = $request->validate([
            'order_id'      => 'required|exists:orders,id',
            'method'        => 'required|in:cash,static_qr,khqr,aba,card',
            'amount'        => 'required|numeric|min:0.01',
            'tax_amount'    => 'nullable|numeric|min:0',
            'exchange_rate' => 'nullable|numeric|min:0',
            'total_khr'     => 'nullable|numeric|min:0',
            'transaction_id'=> 'nullable|string',
            'note'          => 'nullable|string'
        ]);

        try {
            $order = Order::findOrFail($validated['order_id']);

            if ($order->status === 'paid' || $order->status === 'completed') {
                Log::warning("🛑 Order {$order->id} already paid or completed.");
                return response()->json(['message' => 'Order already paid'], 400);
            }

            $payment = Payment::create([
                'order_id'      => $order->id,
                'method'        => $validated['method'],
                'amount'        => $validated['amount'],
                'tax_amount'    => $validated['tax_amount'] ?? 0,
                'exchange_rate' => $validated['exchange_rate'] ?? null,
                'total_khr'     => $validated['total_khr'] ?? null,
                'transaction_id'=> $validated['transaction_id'] ?? Str::uuid(),
                'status'        => 'approved',
                'confirmed_at'  => now()
            ]);

            Log::info('✅ Payment saved', $payment->toArray());

            if (!empty($validated['note'])) {
                PaymentLog::create([
                    'payment_id' => $payment->id,
                    'raw_data' => $validated['note'],
                    'source' => 'manual'
                ]);
            }

            $order->update([
                'status' => 'completed',
                'payment_method' => $validated['method'],
                'paid_at' => now()
            ]);

            Log::info("🟢 Order {$order->id} updated to completed.");

            return response()->json(['message' => 'Payment recorded', 'payment' => $payment], 201);
        } catch (\Throwable $e) {
            Log::error('❌ Payment processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Payment failed', 'error' => $e->getMessage()], 500);
        }
    }

    // ✍️ Log extra info for payment
    public function log(Request $request, Payment $payment)
    {
        $request->validate([
            'raw_data' => 'required|string',
            'source' => 'required|string'
        ]);

        $log = PaymentLog::create([
            'payment_id' => $payment->id,
            'raw_data' => $request->raw_data,
            'source' => $request->source
        ]);

        return response()->json(['message' => 'Log saved', 'log' => $log]);
    }
}
