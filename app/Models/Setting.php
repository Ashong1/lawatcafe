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
     * Every address here must sit OUTSIDE the Kea dynamic pool
     * (192.168.2.110-199) — a fixed service (Proxmox host/LXC, switch, AP) or
     * a MAC-bound reservation. A pooled address rotates to guest phones, which
     * then vanish from Active Sessions and the dashboard counts;
     * SettingController::updateNetwork rejects them.
     */
    public const DEFAULT_INFRASTRUCTURE_IPS = '192.168.254.254,192.168.254.108,192.168.2.250,192.168.2.99,192.168.2.100,192.168.2.5,192.168.2.4';

    /**
     * Get a setting value by key.
     *
     * The default is applied after the cache, never inside it: caching it
     * freezes the first caller's fallback for every later caller, and a
     * changed default in code has no effect until Setting::set() forgets the
     * key.
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
     * network_infrastructure_ips setting: that IP is always infrastructure
     * and mustn't depend on an admin remembering to list it (otherwise
     * OPNsense counts itself as an active guest).
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

    /**
     * Whether the POS may print customer receipts.
     *
     * Off by default: under BIR rules a POS that issues printed receipts must
     * be registered and accredited first, and this one isn't yet. A single
     * switch, so the day registration comes through one toggle restores it.
     */
    public static function receiptPrintingEnabled(): bool
    {
        return static::get('pos_receipt_printing_enabled', '0') === '1';
    }
}
