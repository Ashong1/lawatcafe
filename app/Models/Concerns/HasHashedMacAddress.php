<?php

namespace App\Models\Concerns;

/**
 * mac_address is stored via Laravel's `encrypted` cast, which uses a random
 * IV — the same MAC encrypts to different ciphertext every time, so it can
 * never be matched with `WHERE mac_address = ?` or grouped with `GROUP BY`.
 * mac_address_hash is a deterministic HMAC "blind index" kept in sync
 * automatically on save; all exact-match lookups and uniqueness checks
 * should go through it instead of the encrypted column.
 */
trait HasHashedMacAddress
{
    protected static function bootHasHashedMacAddress(): void
    {
        static::saving(function ($model) {
            $model->mac_address_hash = static::hashMac($model->mac_address);
        });
    }

    public static function hashMac(?string $mac): ?string
    {
        if (! $mac) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac));

        return hash_hmac('sha256', $normalized, config('app.key'));
    }

    public static function findByMac(string $mac)
    {
        return static::where('mac_address_hash', static::hashMac($mac))->first();
    }
}
