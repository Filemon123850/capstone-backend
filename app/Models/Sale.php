<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'sale_number', 'user_id', 'subtotal', 'discount_amount',
        'tax_amount', 'total_amount', 'amount_tendered', 'change_amount',
        'payment_method', 'status', 'customer_name', 'notes',
    ];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'amount_tendered' => 'decimal:2',
        'change_amount'   => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
