<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'user_id', 'starting_cash', 'expected_cash', 'ending_cash',
        'opened_at', 'closed_at', 'status', 'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function transactions()
    {
        return $this->hasMany(ShiftTransaction::class);
    }

    /**
     * The figures on a closing report, an X-Read and the End of Day page,
     * worked out in one place. Voided orders are counted apart from sales.
     */
    public function summary(): array
    {
        $sales = fn () => $this->sales()->revenue();
        $voided = $this->sales()->where('status', 'cancelled');
        $summary = [
            'starting_cash' => (float) $this->starting_cash,
            'orders' => $sales()->count(),
            'total_sales' => (float) $sales()->sum('total_amount'),
            'cash_sales' => (float) $sales()->where('payment_method', 'Cash')->sum('total_amount'),
            // Per e-wallet, so the cashier knows this money is in the shop's account, not the drawer.
            'ewallet_sales' => $sales()->where('payment_method', '!=', 'Cash')
                ->selectRaw('payment_method, SUM(total_amount) as total')->groupBy('payment_method')
                ->pluck('total', 'payment_method')->map(fn ($t) => (float) $t)->all(),
            'discounts' => $sales()->where('discount_amount', '>', 0)
                ->selectRaw("COALESCE(discount_type, 'Discount') as type, SUM(discount_amount) as total")->groupBy('type')
                ->pluck('total', 'type')->map(fn ($t) => (float) $t)->all(),
            'void_count' => (clone $voided)->count(),
            'void_total' => (float) (clone $voided)->sum('total_amount'),
            'pay_ins' => (float) $this->transactions()->where('type', 'pay_in')->sum('amount'),
            'pay_outs' => (float) $this->transactions()->where('type', 'pay_out')->sum('amount'),
            'first_transaction' => $this->sales()->orderBy('id')->value('transaction_number'),
            'last_transaction' => $this->sales()->orderByDesc('id')->value('transaction_number'),
        ];
        $summary['expected_cash'] = $summary['starting_cash'] + $summary['cash_sales'] + $summary['pay_ins'] - $summary['pay_outs'];

        return $summary;
    }
}
