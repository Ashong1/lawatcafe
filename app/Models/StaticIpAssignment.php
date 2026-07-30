<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaticIpAssignment extends Model
{
    protected $fillable = [
        'mac_address',
        'ip_address',
        'hostname',
        'kea_subnet_uuid',
        'kea_reservation_uuid',
    ];
}
