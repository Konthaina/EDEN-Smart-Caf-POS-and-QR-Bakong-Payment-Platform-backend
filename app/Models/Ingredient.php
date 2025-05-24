<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'unit'];

    public function stock()
    {
        return $this->hasOne(Stock::class);
    }
    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }
}

