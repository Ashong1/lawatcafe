<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannedDevice extends Model
{
    protected $fillable = ['mac_address', 'reason', 'hostname'];
}
