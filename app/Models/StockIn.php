<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockIn extends Model
{
    protected $table = 'stock_ins';

    protected $fillable = [
        'product_id',
        'quantity',
        'supplier',
        'po_number',
        'received_at',
    ];

    public function product()
    {
        return $this->belongsTo(
            Product::class,
            'product_id'
        );
    }
}