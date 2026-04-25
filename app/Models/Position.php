<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = [
        'market_id',
        'side',
        'entry_price',
        'size',
        'status',
    ];

    protected $casts = [
        'entry_price' => 'float',
        'size' => 'float',
    ];
}