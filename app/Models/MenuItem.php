<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category_id',
        'price',
        'image',
        'description',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }

}
