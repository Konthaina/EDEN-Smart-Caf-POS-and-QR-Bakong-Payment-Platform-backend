<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'type',
        'title',
        'message',
        'read',
        'scheduled_at',
        'recurring',
        'recurring_type',
        'recurring_value',
        'next_run_at',
    ];

    protected $casts = [
        'read' => 'boolean',
        'recurring' => 'boolean',
        'scheduled_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];
}
