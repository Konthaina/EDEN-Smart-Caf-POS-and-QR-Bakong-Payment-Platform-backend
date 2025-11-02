<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
// use Illuminate\Database\UniqueConstraintViolationException; // Some installs don't expose this class
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class MenuItemController extends Controller
{
    /**
     * List menu items with pagination.
     * Query:
     *  - per_page=10
     *  - with_trashed=1  (include archived)
     *  - visible_only=1  (only active + not deleted)
     *  - include_recipes=1  (include item-level recipes)
     *  - include_variants=1 (include variants; if visible_only=1 -> only active variants)
     */
    public function index(Request $request)
    {
        $perPage         = (int) $request->get('per_page', 10);
        $withTrashed     = $request->boolean('with_trashed', false);
        $visibleOnly     = $request->boolean('visible_only', false);
        $includeRecipes  = $request->boolean('include_recipes', false);
        $includeVariants = $request->boolean('include_variants', false);

        $q = MenuItem::query()->with('category');

        // Eager-load item recipes if requested (fallback when variant recipe absent)
        if ($includeRecipes) {
            $q->with('recipes.ingredient');
        }

        // Eager-load variants (and their recipes) when requested
        if ($includeVariants) {
            if ($includeRecipes) {
                if ($visibleOnly) {
                    // POS-like: only active (visible) variants
                    $q->with([
                        'variants' => fn($v) => $v->visible()->orderBy('position')->orderBy('id'),
                        'variants.recipes.ingredient'
                    ]);
                } else {
                    // Admin: include all non-trashed variants by default;
                    // if with_trashed=1, include archived as well
                    $q->with([
                        'variants' => $withTrashed
                            ? fn($v) => $v->withTrashed()->orderBy('position')->orderBy('id')
                            : fn($v) => $v->orderBy('position')->orderBy('id'),
                        'variants.recipes.ingredient'
                    ]);
                }
            } else {
                $q->with([
                    'variants' => $visibleOnly
                        ? fn($v) => $v->visible()->orderBy('position')->orderBy('id')
                        : ($withTrashed
                            ? fn($v) => $v->withTrashed()->orderBy('position')->orderBy('id')
                            : fn($v) => $v->orderBy('position')->orderBy('id')),
                ]);
            }
        }

        if ($withTrashed) {
            $q->withTrashed();
        }
        if ($visibleOnly) {
            $q->visible();
        }

        // Optional debug:
        // Log::info($q->toRawSql());

        $menuItems = $q->orderBy('name')->paginate($perPage);
        return response()->json($menuItems);
    }

    /**
     * Store a new menu item.
     * Enforces (category_id, name) uniqueness and soft-delete awareness.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => [
                'required','string','max:150',
                Rule::unique('menu_items','name')
                    ->where(function ($q) use ($request) {
                        $cid = $request->input('category_id');
                        if (is_null($cid)) $q->whereNull('category_id');
                        else $q->where('category_id', $cid);
                    })
                    ->whereNull('deleted_at'),
            ],
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'price'       => 'required|numeric|min:0',
            'image'       => 'nullable|image|max:2048',
            'description' => 'nullable|string',
            'is_active'   => 'nullable|boolean',

            // discount
            'discount_type'      => 'nullable|in:percent,fixed',
            'discount_value'     => 'nullable|numeric|min:0',
            'discount_starts_at' => 'nullable|date',
            'discount_ends_at'   => 'nullable|date|after_or_equal:discount_starts_at',
        ]);

        $data['name'] = preg_replace('/\s+/u', ' ', trim($data['name']));

        if (!empty($data['discount_type'])) {
            if (!isset($data['discount_value'])) {
                return response()->json(['message' => 'discount_value is required'], 422);
            }
            if ($data['discount_type'] === 'percent' && $data['discount_value'] > 100) {
                return response()->json(['message' => 'percent cannot exceed 100'], 422);
            }
            if ($data['discount_type'] === 'fixed' && $data['discount_value'] > $data['price']) {
                return response()->json(['message' => 'fixed discount cannot exceed price'], 422);
            }
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('menu', 'public');
        }

        try {
            $item = MenuItem::create($data);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'A menu item with this name already exists in the selected category.',
            ], 422);
        }

        return response()->json(
            $item->load('category'),
            201
        );
    }

    /**
     * Show a single menu item by ID.
     * Query:
     *  - include_recipes=1
     *  - include_variants=1
     */
    public function show(Request $request, MenuItem $menuItem)
    {
        $includeRecipes  = $request->boolean('include_recipes', false);
        $includeVariants = $request->boolean('include_variants', false);

        $relations = ['category'];
        if ($includeRecipes)      $relations[] = 'recipes.ingredient';
        if ($includeVariants) {
            $relations[] = 'variants';
            if ($includeRecipes) $relations[] = 'variants.recipes.ingredient';
        }

        return response()->json($menuItem->load($relations));
    }

    /**
     * Update a menu item (including discount logic) with unique validation.
     */
    public function update(Request $request, MenuItem $menuItem)
    {
        $rules = [
            'name'        => [
                'sometimes','string','max:150',
                Rule::unique('menu_items','name')
                    ->ignore($menuItem->id)
                    ->where(function ($q) use ($request, $menuItem) {
                        $cid = $request->input('category_id', $menuItem->category_id);
                        if (is_null($cid)) $q->whereNull('category_id');
                        else $q->where('category_id', $cid);
                    })
                    ->whereNull('deleted_at'),
            ],
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'price'       => 'sometimes|numeric|min:0',
            'image'       => 'nullable|image|max:2048',
            'description' => 'sometimes|nullable|string',
            'is_active'   => 'sometimes|boolean',

            'discount_type'      => 'sometimes|nullable|in:percent,fixed',
            'discount_value'     => 'sometimes|nullable|numeric|min:0',
            'discount_starts_at' => 'sometimes|nullable|date',
            'discount_ends_at'   => 'sometimes|nullable|date|after_or_equal:discount_starts_at',
        ];

        $data = $request->validate($rules);

        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $data['name'] = preg_replace('/\s+/u', ' ', trim($data['name']));
        }

        if (array_key_exists('discount_type', $data) && $data['discount_type']) {
            $value = $data['discount_value'] ?? $menuItem->discount_value ?? null;
            $price = $data['price'] ?? $menuItem->price;
            if ($data['discount_type'] === 'percent' && $value !== null && $value > 100) {
                return response()->json(['message' => 'percent cannot exceed 100'], 422);
            }
            if ($data['discount_type'] === 'fixed' && $value !== null && $value > $price) {
                return response()->json(['message' => 'fixed discount cannot exceed price'], 422);
            }
        }

        if ($request->hasFile('image')) {
            if ($menuItem->image && Storage::disk('public')->exists($menuItem->image)) {
                Storage::disk('public')->delete($menuItem->image);
            }
            $data['image'] = $request->file('image')->store('menu', 'public');
        }

        try {
            $menuItem->update($data);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'A menu item with this name already exists in the selected category.',
            ], 422);
        }

        return response()->json([
            'message'   => 'Updated',
            'menu_item' => $menuItem->load('category'),
        ]);
    }

    /**
     * Delete (archive) a menu item by default.
     * Pass ?force=1 to permanently delete (also removes stored image).
     */
    public function destroy(Request $request, MenuItem $menuItem)
    {
        $force = $request->boolean('force', false);

        if ($force) {
            if ($menuItem->image && Storage::disk('public')->exists($menuItem->image)) {
                Storage::disk('public')->delete($menuItem->image);
            }
            $menuItem->forceDelete();
            return response()->json(['message' => 'Permanently deleted']);
        }

        $menuItem->update(['is_active' => false]);
        $menuItem->delete();

        return response()->json(['message' => 'Archived']);
    }

    /**
     * Search with filters + pagination.
     * Query:
     *  - name, category_id, min_price, max_price
     *  - with_trashed=1 | visible_only=1
     */
    public function search(Request $request)
    {
        $q = MenuItem::with('category');

        if ($request->boolean('with_trashed', false)) $q->withTrashed();
        if ($request->boolean('visible_only', false)) $q->visible();

        if ($request->filled('name')) {
            $q->where('name', 'like', '%'.$request->name.'%');
        }
        if ($request->filled('category_id')) {
            $q->where('category_id', $request->category_id);
        }
        if ($request->filled('min_price')) {
            $q->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $q->where('price', '<=', $request->max_price);
        }

        $perPage = (int) $request->get('per_page', 10);
        $results = $q->orderBy('name')->paginate($perPage);

        return response()->json($results);
    }

    public function restore(Request $request, $id)
    {
        $reactivate = $request->boolean('reactivate', true);

        $item = MenuItem::withTrashed()->findOrFail($id);
        if (!$item->trashed()) {
            return response()->json(['message' => 'Menu item is not archived.'], 400);
        }

        $item->restore();
        if ($reactivate) {
            $item->update(['is_active' => true]);
        }

        return response()->json([
            'message'   => 'Menu item restored.',
            'menu_item' => $item->fresh('category'),
        ]);
    }
}
