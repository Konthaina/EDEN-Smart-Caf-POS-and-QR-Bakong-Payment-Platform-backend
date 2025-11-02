<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * GET /api/categories
     * Query params:
     *  - with_trashed=1   → រួមទាំង archived
     *  - visible_only=1   → is_active=true និង មិនរួម archived
     */
    public function index(Request $request)
    {
        $withTrashed  = $request->boolean('with_trashed', false);
        $visibleOnly  = $request->boolean('visible_only', false);

        $q = Category::query();

        if ($withTrashed) {
            $q->withTrashed();
        }

        if ($visibleOnly) {
            $q->where('is_active', true)->whereNull('deleted_at');
        }

        $categories = $q->withCount('menuItems')
                        ->orderBy('id')
                        ->get();

        return response()->json($categories);
    }

    /**
     * POST /api/categories
     * body: name (required), slug (optional), is_active (optional)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100|unique:categories,name',
            'slug'      => 'nullable|string|max:150|unique:categories,slug',
            'is_active' => 'nullable|boolean',
        ]);

        // Auto slug ប្រសិនបើមិនផ្តល់
        if (empty($data['slug'])) {
            $data['slug'] = $this->makeUniqueSlug($data['name']);
        }

        $category = Category::create([
            'name'      => $data['name'],
            'slug'      => $data['slug'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json($category, 201);
    }

    /**
     * GET /api/categories/{category}
     * Query: include_items=1, visible_only=1
     */
    public function show(Request $request, Category $category)
    {
        $includeItems = $request->boolean('include_items', false);
        $visibleOnly  = $request->boolean('visible_only', false);

        if ($includeItems) {
            $category->load(['menuItems' => function ($q) use ($visibleOnly) {
                if ($visibleOnly) {
                    $q->where('is_active', true)->whereNull('deleted_at');
                }
                $q->orderBy('name');
            }]);
        }

        return response()->json($category);
    }

    /**
     * PATCH/PUT /api/categories/{category}
     * name, slug, is_active (optional)
     */
    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'      => 'sometimes|string|max:100|unique:categories,name,' . $category->id,
            'slug'      => 'sometimes|nullable|string|max:150|unique:categories,slug,' . $category->id,
            'is_active' => 'sometimes|boolean',
        ]);

        // បើកែ name ប៉ុន្តែមិនផ្តល់ slug ថ្មី និង slug ខុសឈ្មោះ → មិនប្តូរ slug ដើម្បីរក្សា stable URL
        $payload = [
            'name'      => $data['name']      ?? $category->name,
            'slug'      => array_key_exists('slug', $data)
                            ? $data['slug']
                            : $category->slug,
            'is_active' => $data['is_active'] ?? $category->is_active,
        ];

        // បើ slug សុំនៅ null (category ចាស់មិនទាន់មាន slug) → បង្កើតឲ្យ
        if (empty($payload['slug'])) {
            $payload['slug'] = $this->makeUniqueSlug($payload['name']);
        }

        $category->update($payload);

        return response()->json([
            'message'  => 'Category updated.',
            'category' => $category->fresh(),
        ]);
    }

    /**
     * DELETE /api/categories/{category}
     * Query/Body param: mode=archive|reassign|uncategorize (default: archive)
     *  - archive:      set is_active=false + soft delete (deleted_at)
     *  - reassign:     require target_id (category id). Move all items, then forceDelete()
     *  - uncategorize: set menu_items.category_id=null, then forceDelete()
     */
    public function destroy(Request $request, Category $category)
    {
        $mode     = $request->input('mode', 'archive');
        $targetId = $request->input('target_id');

        if ($mode === 'archive') {
            $category->update(['is_active' => false]);
            $category->delete(); // soft delete
            return response()->json(['message' => 'Category archived.']);
        }

        if ($mode === 'reassign') {
            // Validate target
            if (!$targetId || (int)$targetId === (int)$category->id) {
                return response()->json(['message' => 'Invalid target category.'], 422);
            }
            $target = Category::find($targetId);
            if (!$target) {
                return response()->json(['message' => 'Target category not found.'], 404);
            }

            DB::transaction(function () use ($category, $target) {
                MenuItem::where('category_id', $category->id)->update(['category_id' => $target->id]);
                // delete permanently after reassign
                $category->forceDelete();
            });

            return response()->json(['message' => 'Category merged into target and deleted.']);
        }

        if ($mode === 'uncategorize') {
            DB::transaction(function () use ($category) {
                MenuItem::where('category_id', $category->id)->update(['category_id' => null]);
                $category->forceDelete();
            });

            return response()->json(['message' => 'Category deleted. Items set to Uncategorized.']);
        }

        return response()->json(['message' => 'Invalid mode.'], 422);
    }

    /**
     * POST /api/categories/{id}/restore
     * body/query: reactivate=1 → is_active=true ក្រោយ restore
     */
    public function restore(Request $request, $id)
    {
        $reactivate = $request->boolean('reactivate', true);

        $category = Category::withTrashed()->findOrFail($id);
        if (!$category->trashed()) {
            return response()->json(['message' => 'Category is not archived.'], 400);
        }

        $category->restore();
        if ($reactivate) {
            $category->update(['is_active' => true]);
        }

        return response()->json([
            'message'  => 'Category restored.',
            'category' => $category->fresh(),
        ]);
    }

    // ----------------- Helpers -----------------

    private function makeUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 1;
        while (Category::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }
        return $slug;
    }
}
