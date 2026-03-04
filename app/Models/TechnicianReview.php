<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicianReview extends Model
{
    protected $fillable = [
        'sale_id',
        'reviewed_by',
        'status',
        'notes',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
