<?php

namespace App\Models;

use App\Models\Concerns\HasHashedMacAddress;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasHashedMacAddress;

    // Allow mass assignment for these fields
    protected $fillable = [
        'code',
        'duration_minutes',
        'tier',
        'is_used',
        'used_at',
        'activated_at',
        'disconnected_at',
        'ip_address',
        'mac_address',
        'sale_id',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'activated_at' => 'datetime',
        'disconnected_at' => 'datetime',
        'is_used' => 'boolean',
        'mac_address' => 'encrypted',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Five characters from an alphabet without look-alikes (no 0/O, 1/I/L),
     * so a code read off a slip is typed right the first time and there are
     * 28 million possibilities per prefix instead of 1.7 million.
     */
    public const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function generateCode(string $prefix = 'LAWA'): string
    {
        do {
            $code = $prefix.'-';
            for ($i = 0; $i < 5; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
