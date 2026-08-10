<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One throughput reading of the guest interface. Written by `shaper:adapt`,
 * read by LinkCapacityLearner — see the migration for why the ceiling in force
 * is stored alongside the rates.
 */
class BandwidthSample extends Model
{
    protected $fillable = [
        'sampled_at',
        'down_mbps',
        'up_mbps',
        'active_guests',
        'ceiling_mbps',
    ];

    protected $casts = [
        'sampled_at' => 'datetime',
        'down_mbps' => 'float',
        'up_mbps' => 'float',
        'ceiling_mbps' => 'float',
        'active_guests' => 'integer',
    ];
}
