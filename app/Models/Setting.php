<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Marks "there is no row for this key" in the cache.
     *
     * rememberForever cannot cache a null — Cache::get returns null for both
     * "absent" and "stored null", so the closure would re-run on every call.
     * A sentinel lets a missing setting stay cached without freezing any one
     * caller's fallback value.
     */
    private const MISSING = '__setting_missing__';

    /**
     * Seed value for network_infrastructure_ips, shared with the settings
     * screen so the code and the textarea can never disagree.
     *
     * Every address here must sit OUTSIDE the Kea dynamic pool — a fixed
     * service (Proxmox host/LXC, switch, AP) or a MAC-bound reservation.
     * 192.168.2.117 used to be in this list purely because an access point
     * happened to hold that lease the day the list was written; the pool is
     * 192.168.2.110-199, so once the lease rotated, real guest phones landed
     * on .117 and were filed as infrastructure — invisible in Active Sessions
     * and uncounted on the dashboard. Do not add a pooled address here;
     * SettingController::updateNetwork now rejects them.
     */
    public const DEFAULT_INFRASTRUCTURE_IPS = '192.168.254.254,192.168.254.108,192.168.2.250,192.168.2.99,192.168.2.100,192.168.2.5,192.168.2.4';

    /**
     * Get a setting value by key.
     *
     * The default is applied *after* the cache, never inside it. Caching the
     * default meant the first caller to ask for an unset key froze its own
     * fallback in place forever: every later caller got that value regardless
     * of the default it passed, and editing the default in code had no effect
     * because the cache still held the old one. That is exactly how
     * portal_browse_url kept resolving to neverssl.com long after the code
     * stopped saying so — only an explicit Setting::set(), which forgets the
     * key, could ever clear it.
     */
    public static function get($key, $default = null)
    {
        $value = Cache::rememberForever(
            "setting.{$key}",
            fn () => self::where('key', $key)->value('value') ?? self::MISSING
        );

        return $value === self::MISSING ? $default : $value;
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
        $ips = array_filter(array_map('trim', explode(',', static::get('network_infrastructure_ips', self::DEFAULT_INFRASTRUCTURE_IPS))));

        $opnsenseIp = config('services.opnsense.ip');
        if ($opnsenseIp) {
            $ips[] = $opnsenseIp;
        }

        return array_values(array_unique($ips));
    }
}
