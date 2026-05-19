<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Voucher extends Model
{
    // Allow mass assignment for these fields
    protected $fillable = [
        'code',
        'duration_minutes',
        'is_used',
        'used_at',
        'ip_address',
        'mac_address',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}