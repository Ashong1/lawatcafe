<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EwalletPayment extends Model
{
    protected $fillable = [
        'reference_number',
        'amount',
        'sender_details',
        'is_used',
        'email_date'
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'email_date' => 'datetime',
    ];
}
