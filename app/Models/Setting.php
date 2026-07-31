<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key.
     */
    public static function get($key, $default = null)
    {
        return Cache::rememberForever("setting.{$key}", function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value by key.
     */
    public static function set($key, $value)
    {
        Cache::forget("setting.{$key}");

        return self::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Parsed, deduplicated list of "infrastructure" IPs — hidden from guest
     * counts/tables across the dashboard, network sessions page, and
     * EnforceSessionLimits. Always includes this OPNsense instance's own
     * LAN IP in addition to whatever's in the admin-edited
     * network_infrastructure_ips setting: that IP is *structurally* always
     * infrastructure and must never depend on an admin remembering to also
     * add it to a freeform string — omitting it previously caused OPNsense
     * to count itself as an "active guest" on the dashboard.
     */
    public static function infrastructureIps(): array
    {
        $default = '192.168.254.254,192.168.254.108,192.168.2.117,192.168.2.250,192.168.2.99,192.168.2.100,192.168.2.5,192.168.2.4';
        $ips = array_filter(array_map('trim', explode(',', static::get('network_infrastructure_ips', $default))));

        $opnsenseIp = config('services.opnsense.ip');
        if ($opnsenseIp) {
            $ips[] = $opnsenseIp;
        }

        return array_values(array_unique($ips));
    }
}
