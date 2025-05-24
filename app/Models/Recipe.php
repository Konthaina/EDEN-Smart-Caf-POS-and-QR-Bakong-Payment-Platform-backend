<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_item_id',
        'ingredient_id',
        'quantity',
    ];

    /**
     * Relationship: Recipe belongs to a menu item
     */
    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * Relationship: Recipe uses one ingredient
     */
    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
