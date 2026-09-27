<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkHealthCheck extends Model
{
    protected $fillable = [
        'checked_at', 'overall', 'internet_latency_ms', 'internet_loss_pct', 'dns_ok',
        'dhcp_used', 'dhcp_size', 'guests_online', 'infrastructure_down', 'results',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
        'internet_latency_ms' => 'float',
        'internet_loss_pct' => 'float',
        'dns_ok' => 'boolean',
        'results' => 'array',
    ];
}
