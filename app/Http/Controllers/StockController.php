<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\Ingredient;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index()
    {
        return Stock::with('ingredient')->get();
    }

    // Accepts either one item or many items
    public function store(Request $request)
    {
        if ($request->has('items')) {
            $data = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.ingredient_id' => 'required|exists:ingredients,id',
                'items.*.quantity' => 'required|numeric|min:0',
            ]);

            $result = [];

            foreach ($data['items'] as $item) {
                $stock = Stock::updateOrCreate(
                    ['ingredient_id' => $item['ingredient_id']],
                    ['quantity' => $item['quantity']]
                );

                $result[] = $stock->load('ingredient');
            }

            return response()->json([
                'message' => 'Stock batch updated successfully.',
                'stocks' => $result
            ]);
        } else {
            $data = $request->validate([
                'ingredient_id' => 'required|exists:ingredients,id',
                'quantity' => 'required|numeric|min:0',
            ]);

            $stock = Stock::updateOrCreate(
                ['ingredient_id' => $data['ingredient_id']],
                ['quantity' => $data['quantity']]
            );

            return response()->json([
                'message' => 'Stock updated.',
                'stock' => $stock->load('ingredient')
            ]);
        }
    }

    public function show(Stock $stock)
    {
        return $stock->load('ingredient');
    }
}
