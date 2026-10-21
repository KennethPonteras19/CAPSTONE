<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public const MAX_IMAGE_SIZE_KB = 10240;

    protected $appends = ['image_url'];

    protected $fillable = [
        'name',
        'category',
        'stock',
        'brand',
        'stock_unit',
        'price',
        'status',
        'image_path',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/'.$this->image_path) : null;
    }
}
