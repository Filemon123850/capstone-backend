<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'sale_number',
        'user_id',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'amount_tendered',
        'change_amount',
        'payment_method',
        'status',
        'customer_name',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function technicianReview()
    {
        return $this->hasOne(TechnicianReview::class);
    }
}
