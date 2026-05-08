<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    // Add this line to allow data to be saved!
    protected $fillable = [
        'transaction_number',
        'total_amount',
        'payment_method',
        'user_id'
    ];

    // Optional: Setup the relationship so you can get the user who made the sale
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}