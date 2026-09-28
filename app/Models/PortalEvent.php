<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/** One thing a guest did on the captive portal. See the Portal report page. */
class PortalEvent extends Model
{
    public const UPDATED_AT = null;

    public const VISIT = 'visit';

    public const CODE_TRIED = 'code_tried';

    public const CODE_FAILED = 'code_failed';

    public const CONNECTED = 'connected';

    public const MORE_TIME = 'more_time';

    public const TIME_UP = 'time_up';

    public const TIME_ADDED = 'time_added';

    public const DROPPED = 'dropped';

    public const KEEP_DAYS = 90;

    protected $fillable = ['type', 'ip_address', 'voucher_code', 'meta'];

    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];

    /** Never lets a logging failure break the guest's page. */
    public static function record(string $type, ?string $ip = null, ?string $code = null, array $meta = []): void
    {
        try {
            static::create(['type' => $type, 'ip_address' => $ip, 'voucher_code' => $code, 'meta' => $meta ?: null]);
        } catch (\Throwable $e) {
            Log::warning("PortalEvent {$type} not recorded: ".$e->getMessage());
        }
    }
}
