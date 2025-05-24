<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Stock;

class MenuItemAvailabilityController extends Controller
{
    public function check($id)
    {
        $menuItem = MenuItem::with('recipes.ingredient')->findOrFail($id);

        if ($menuItem->recipes->isEmpty()) {
            return response()->json(['message' => 'No recipe defined.'], 400);
        }

        $quantities = [];

        foreach ($menuItem->recipes as $recipe) {
            $stock = Stock::where('ingredient_id', $recipe->ingredient_id)->first();

            if (! $stock || $stock->quantity < $recipe->quantity) {
                return response()->json([
                    'menu_item' => $menuItem->name,
                    'available' => 0,
                    'limiting_ingredient' => $recipe->ingredient->name
                ]);
            }

            $quantities[] = floor($stock->quantity / $recipe->quantity);
        }

        return response()->json([
            'menu_item' => $menuItem->name,
            'available' => min($quantities)
        ]);
    }
}
