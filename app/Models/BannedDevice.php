<?php

namespace App\Models;

use App\Models\Concerns\HasHashedMacAddress;
use Illuminate\Database\Eloquent\Model;

class BannedDevice extends Model
{
    use HasHashedMacAddress;

    protected $fillable = ['mac_address', 'reason', 'hostname'];

    protected $casts = [
        'mac_address' => 'encrypted',
    ];
}
