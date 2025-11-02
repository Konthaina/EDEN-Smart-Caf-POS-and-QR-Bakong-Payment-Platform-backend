<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_item_id',
        'menu_item_variant_id', // NEW
        'ingredient_id',
        'quantity',
    ];

    /** Recipe belongs to a menu item (include soft-deleted) */
    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class)->withTrashed();
    }

    /** Optional: recipe belongs to a specific variant (include archived) */
    public function variant()
    {
        return $this->belongsTo(MenuItemVariant::class, 'menu_item_variant_id')->withTrashed();
    }

    /** Recipe uses one ingredient */
    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
