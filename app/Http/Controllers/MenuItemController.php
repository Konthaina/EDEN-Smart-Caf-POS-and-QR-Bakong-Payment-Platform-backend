<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    public function index()
    {
        return MenuItem::with('category')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|max:2048',
            'description' => 'nullable|string',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('menu', 'public');
        }

        $item = MenuItem::create($data);
        return response()->json($item, 201);
    }


    public function show(MenuItem $menuItem)
    {
        return $menuItem->load('category');
    }

    public function update(Request $request, MenuItem $menuItem)
    {
        $data = $request->validate([
            'name' => 'sometimes|string',
            'category_id' => 'sometimes|exists:categories,id',
            'price' => 'sometimes|numeric|min:0',
            'image' => 'nullable|image|max:2048',
            'description' => 'nullable|string',
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $path = $image->store('menu', 'public'); // => storage/app/public/menu/xxx.jpg
            $data['image'] = $path; // Save as: "menu/filename.jpg"
        }

        $menuItem->update($data);
        return response()->json(['message' => 'Updated', 'menu_item' => $menuItem]);
    }

    public function destroy(MenuItem $menuItem)
    {
        $menuItem->delete();
        return response()->json(['message' => 'Deleted']);
    }
    public function search(Request $request)
    {
        $query = MenuItem::query()->with('category');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        return $query->get();
    }
}
