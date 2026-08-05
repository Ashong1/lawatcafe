<?php

namespace App\Models;

use App\Models\Concerns\HasHashedMacAddress;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasHashedMacAddress;

    // Allow mass assignment for these fields
    protected $fillable = [
        'code',
        'duration_minutes',
        'tier',
        'is_used',
        'used_at',
        'activated_at',
        'ip_address',
        'mac_address',
        'sale_id',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'activated_at' => 'datetime',
        'is_used' => 'boolean',
        'mac_address' => 'encrypted',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
