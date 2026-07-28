<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFinding extends Model
{
    protected $fillable = ['run_id', 'type', 'severity', 'summary', 'data', 'audience'];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Maps CrossDomainCorrelationService signal types to who should see them
     * on their dashboard. Operational signals go to staff+admin; security/
     * abuse signals stay admin-only.
     */
    public const AUDIENCE_BY_TYPE = [
        'low_stock_high_demand' => 'staff',
        'voucher_revenue_divergence' => 'admin',
        'repeat_mac_abuse' => 'admin',
        'banned_device_reentry' => 'admin',
    ];

    public function run()
    {
        return $this->belongsTo(AiAnalysisRun::class, 'run_id');
    }

    public static function audienceForType(string $type): string
    {
        return self::AUDIENCE_BY_TYPE[$type] ?? 'admin';
    }
}
