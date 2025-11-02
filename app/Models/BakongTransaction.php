<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BakongTransaction extends Model
{
    protected $fillable = [
        'bill_number',
        'amount',
        'currency',
        'qr_string',
        'md5_hash',
        'status',
        'send_from',
        'receive_to',
        'completed_at',
    ];
}
