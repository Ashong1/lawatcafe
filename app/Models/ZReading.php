<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One per business day; see ZReadService. */
class ZReading extends Model
{
    protected $fillable = ['number', 'business_date', 'grand_total', 'first_transaction', 'last_transaction', 'totals', 'user_id'];

    protected $casts = ['business_date' => 'date', 'totals' => 'array', 'grand_total' => 'float'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function closes(\DateTimeInterface $at): bool
    {
        return static::whereDate('business_date', $at)->exists();
    }
}
