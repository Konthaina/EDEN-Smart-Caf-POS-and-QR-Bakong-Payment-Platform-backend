<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function index(Request $request)
    {
        // Include parent item and variant (both may be soft-deleted) to avoid "lost parent" in UI.
        $recipes = Recipe::with([
            'menuItem' => function ($q) {
                $q->withTrashed();
            },
            'variant'  => function ($q) {
                $q->withTrashed();
            },
            'ingredient'
        ])->get();

        return response()->json($recipes);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'menu_item_id'         => 'nullable|exists:menu_items,id',
            'menu_item_variant_id' => 'nullable|exists:menu_item_variants,id',
            'ingredient_id'        => 'required|exists:ingredients,id',
            'quantity'             => 'required|numeric|min:0.01',
        ]);

        $hasItem    = !empty($validated['menu_item_id']);
        $hasVariant = !empty($validated['menu_item_variant_id']);

        // Enforce "exactly one owner": item OR variant
        if ($hasItem === $hasVariant) {
            return response()->json(['message' => 'Provide exactly one of menu_item_id or menu_item_variant_id.'], 422);
        }

        $recipe = Recipe::updateOrCreate(
            [
                'menu_item_id'         => $validated['menu_item_id'] ?? null,
                'menu_item_variant_id' => $validated['menu_item_variant_id'] ?? null,
                'ingredient_id'        => $validated['ingredient_id'],
            ],
            ['quantity' => $validated['quantity']]
        );

        return response()->json([
            'message' => 'Recipe saved.',
            'recipe'  => $recipe->load([
                'ingredient',
                'menuItem' => fn($q) => $q->withTrashed(),
                'variant'  => fn($q) => $q->withTrashed(),
            ]),
        ]);
    }

    public function destroy(Recipe $recipe)
    {
        $recipe->delete();
        return response()->json(['message' => 'Recipe entry deleted.']);
    }
}
