<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function index()
    {
        return Discount::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:discounts',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'amount' => 'nullable|numeric|min:0',
            'expires_at' => 'nullable|date',
            'active' => 'boolean'
        ]);

        $discount = Discount::create($validated);
        return response()->json(['message' => 'Discount created.', 'discount' => $discount], 201);
    }

    public function show(Discount $discount)
    {
        return $discount;
    }

    public function update(Request $request, Discount $discount)
    {
        $discount->update($request->all());
        return response()->json(['message' => 'Discount updated.', 'discount' => $discount]);

    }

    public function destroy(Discount $discount)
    {
        $discount->delete();
        return response()->json(['message' => 'Discount deleted.']);
    }
    public function validateCode(Request $request)
    {
        $code = strtoupper($request->query('code'));

        $discount = Discount::where('code', $code)
            ->where('active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->first();

        if (!$discount) {
            return response()->json(['message' => 'Invalid or expired code'], 404);
        }

        return response()->json([
            'type' => $discount->percentage ? 'percent' : 'fixed',
            'value' => $discount->percentage ?? $discount->amount,
        ]);
    }
}
