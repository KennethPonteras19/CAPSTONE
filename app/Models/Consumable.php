<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consumable extends Model
{
    protected $fillable = [
        'name',
        'category',
        'unit',
        'stock',
        'minimum_stock',
        'price',
        'is_active',
    ];

    protected $casts = [
        'stock' => 'decimal:3',
        'minimum_stock' => 'decimal:3',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}