<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function index()
    {
        return Recipe::with(['menuItem', 'ingredient'])->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'menu_item_id' => 'required|exists:menu_items,id',
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.01',
        ]);

        $recipe = Recipe::updateOrCreate(
            [
                'menu_item_id' => $validated['menu_item_id'],
                'ingredient_id' => $validated['ingredient_id'],
            ],
            ['quantity' => $validated['quantity']]
        );

        return response()->json(['message' => 'Recipe saved.', 'recipe' => $recipe->load('ingredient')]);
    }

    public function destroy(Recipe $recipe)
    {
        $recipe->delete();
        return response()->json(['message' => 'Recipe entry deleted.']);
    }
}
