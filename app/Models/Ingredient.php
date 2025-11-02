<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ingredient extends Model
{
    use HasFactory;

    // Add low_alert_qty to allow mass-assignment
    protected $fillable = [
        'name',
        'unit',
        'low_alert_qty',
    ];

    // Cast as decimal for consistent numeric handling
    protected $casts = [
        'low_alert_qty' => 'decimal:3',
    ];

    public function stocks()
    {
        return $this->hasMany(\App\Models\Stock::class, 'ingredient_id', 'id');
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }
}
