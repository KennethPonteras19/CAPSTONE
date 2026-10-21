<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['placed_at' => 'datetime', 'completed_at' => 'datetime', 'scheduled_at' => 'datetime',
            'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2',
            'delivery_fee' => 'decimal:2', 'total_amount' => 'decimal:2', 'paid_amount' => 'decimal:2'];
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function isDraft(): bool
    {
        return in_array($this->status, ['Held', 'Quotation'], true);
    }

    public function getBalanceAttribute(): float
    {
        return max(0, round($this->total_amount - $this->paid_amount, 2));
    }
}
