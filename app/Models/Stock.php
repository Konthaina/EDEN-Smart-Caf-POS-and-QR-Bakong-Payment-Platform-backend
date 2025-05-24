<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = ['ingredient_id', 'quantity'];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
