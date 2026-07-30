<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleVoidRequest extends Model
{
    protected $fillable = [
        'sale_id',
        'requested_by',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
