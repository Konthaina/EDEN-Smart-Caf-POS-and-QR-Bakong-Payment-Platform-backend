<?php

// app/Http/Controllers/BannerController.php
namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index()
    {
        return Banner::orderBy('display_order')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'required|image|max:2048',
            'link' => 'nullable|url',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ]);

        $path = $request->file('image')->store('banners', 'public');

        $banner = Banner::create([
            'title' => $request->title,
            'description' => $request->description,
            'image' => $path,
            'link' => $request->link,
            'is_active' => $request->is_active ?? true,
            'display_order' => $request->display_order ?? 0,
        ]);

        return response()->json($banner, 201);
    }

    public function update(Request $request, Banner $banner)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'link' => 'nullable|url',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ]);

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($banner->image);
            $path = $request->file('image')->store('banners', 'public');
            $banner->image = $path;
        }

        $banner->update($request->only(['title', 'description', 'link', 'is_active', 'display_order']));

        return response()->json($banner);
    }

    public function destroy(Banner $banner)
    {
        Storage::disk('public')->delete($banner->image);
        $banner->delete();
        return response()->json(['message' => 'Banner deleted']);
    }
    public function reorder(Request $request)
    {
        $ordered = $request->input('ordered', []);
        foreach ($ordered as $item) {
            Banner::where('id', $item['id'])->update(['display_order' => $item['display_order']]);
        }
        return response()->json(['message' => 'Order updated']);
    }

}

