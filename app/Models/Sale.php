<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $table = 'sales';

    public function scopeForReporting(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereNull('status')->orWhereNotIn('status', ['Cancelled', 'Held', 'Quotation']);
        });
    }

    protected $fillable = [
        'receipt_no',
        'staff_name',
        'status',
        'item_name',
        'qty',
        'total_amount',
        'payment_status',
        'paid_amount',
        'order_id',
        'order_item_id',
    ];
}
