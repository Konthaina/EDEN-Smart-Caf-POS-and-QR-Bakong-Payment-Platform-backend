<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function index()
    {
        return Ingredient::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:ingredients',
            'unit' => 'required|string'
        ]);

        $ingredient = Ingredient::create($validated);

        return response()->json($ingredient, 201);
    }

    public function show(Ingredient $ingredient)
    {
        return $ingredient;
    }

    public function update(Request $request, Ingredient $ingredient)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:ingredients,name,' . $ingredient->id,
            'unit' => 'required|string'
        ]);

        $ingredient->update($validated);

        return response()->json(['message' => 'Ingredient updated.', 'ingredient' => $ingredient]);
    }

    public function destroy(Ingredient $ingredient)
    {
        $ingredient->delete();
        return response()->json(['message' => 'Ingredient deleted.']);
    }
}
