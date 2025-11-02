<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IngredientController extends Controller
{
    /**
     * List all ingredients.
     */
    public function index()
    {
        return Ingredient::all();
    }

    /**
     * Create a new ingredient.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:ingredients',
            'unit' => 'required|string',
            'low_alert_qty' => 'nullable|numeric|min:0',
        ]);

        $ingredient = Ingredient::create($validated);

        return response()->json($ingredient, 201);
    }

    /**
     * Show a single ingredient.
     */
    public function show(Ingredient $ingredient)
    {
        return $ingredient;
    }

    /**
     * Update an ingredient.
     */
    public function update(Request $request, Ingredient $ingredient)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:ingredients,name,' . $ingredient->id,
            'unit' => 'required|string',
            'low_alert_qty' => 'nullable|numeric|min:0',

        ]);

        $ingredient->update($validated);

        return response()->json([
            'message'    => 'Ingredient updated.',
            'ingredient' => $ingredient,
        ]);
    }

    /**
     * Delete an ingredient and its related stock rows
     * (prevents orphan stocks that break the stock page).
     */
    public function destroy(Ingredient $ingredient)
    {
        try {
            DB::transaction(function () use ($ingredient) {
                // Delete related stock rows (hasMany)
                $ingredient->stocks()->delete();

                // Delete related recipe rows (hasMany)  <<< FIXED
                $ingredient->recipes()->delete();

                // Finally delete the ingredient
                $ingredient->delete();
            });

            return response()->json(['message' => 'Ingredient and related records deleted.']);
        } catch (\Throwable $e) {
            Log::error('Ingredient delete failed', [
                'ingredient_id' => $ingredient->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Cannot delete ingredient. It may still be referenced by other records.',
            ], 409);
        }
    }
}
