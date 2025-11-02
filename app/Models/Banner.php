<?php

// app/Models/Banner.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title',
        'description',
        'image',
        'link',
        'is_active',
        'display_order',
    ];
}
