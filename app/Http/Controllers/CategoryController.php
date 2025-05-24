<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return Category::all();
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:categories']);
        $category = Category::create(['name' => $request->name]);

        return response()->json($category, 201);
    }

    public function show(Category $category)
    {
        return $category;
    }

    public function update(Request $request, Category $category)
    {
        $request->validate(['name' => 'required|string|unique:categories,name,' . $category->id]);
        $category->update(['name' => $request->name]);

        return response()->json(['message' => 'Category updated.', 'category' => $category]);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json(['message' => 'Category deleted.']);
    }
}
