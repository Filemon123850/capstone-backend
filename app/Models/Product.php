<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'sku', 'description',
        'cost_price', 'selling_price', 'stock_quantity',
        'low_stock_threshold', 'unit', 'is_active', 'image_path',
    ];

    protected $casts = [
        'cost_price'      => 'decimal:2',
        'selling_price'   => 'decimal:2',
        'stock_quantity'  => 'integer',
        'low_stock_threshold' => 'integer',
        'is_active'       => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function sales()
    {
        return $this->hasMany(SaleItem::class);
    }
}
