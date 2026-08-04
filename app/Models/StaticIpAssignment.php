<?php

namespace App\Models;

use App\Models\Concerns\HasHashedMacAddress;
use Illuminate\Database\Eloquent\Model;

class StaticIpAssignment extends Model
{
    use HasHashedMacAddress;

    protected $fillable = [
        'mac_address',
        'ip_address',
        'hostname',
        'kea_subnet_uuid',
        'kea_reservation_uuid',
    ];

    protected $casts = [
        'mac_address' => 'encrypted',
    ];
}
