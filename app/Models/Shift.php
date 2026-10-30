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
        $summary = [
            'starting_cash' => (float) $this->starting_cash,
            ...self::salesFigures(fn () => $this->sales()),
            'pay_ins' => (float) $this->transactions()->where('type', 'pay_in')->sum('amount'),
            'pay_outs' => (float) $this->transactions()->where('type', 'pay_out')->sum('amount'),
        ];
        $summary['expected_cash'] = $summary['starting_cash'] + $summary['cash_sales'] + $summary['pay_ins'] - $summary['pay_outs'];

        return $summary;
    }

    /**
     * Sales figures for any set of sales — one shift (summary()) or a whole
     * day (ZReadService). $sales returns a fresh query each call.
     */
    public static function salesFigures(\Closure $sales): array
    {
        $revenue = fn () => $sales()->where('status', '!=', 'cancelled');

        return [
            'orders' => $revenue()->count(),
            'total_sales' => (float) $revenue()->sum('total_amount'),
            'cash_sales' => (float) $revenue()->where('payment_method', 'Cash')->sum('total_amount'),
            // Per e-wallet, so the cashier knows this money is in the shop's account, not the drawer.
            'ewallet_sales' => $revenue()->where('payment_method', '!=', 'Cash')
                ->selectRaw('payment_method, SUM(total_amount) as total')->groupBy('payment_method')
                ->pluck('total', 'payment_method')->map(fn ($t) => (float) $t)->all(),
            'discounts' => $revenue()->where('discount_amount', '>', 0)
                ->selectRaw("COALESCE(discount_type, 'Discount') as type, SUM(discount_amount) as total")->groupBy('type')
                ->pluck('total', 'type')->map(fn ($t) => (float) $t)->all(),
            'refund_count' => $revenue()->where('refunded_amount', '>', 0)->count(),
            'refund_total' => (float) $revenue()->sum('refunded_amount'),
            'void_count' => $sales()->where('status', 'cancelled')->count(),
            'void_total' => (float) $sales()->where('status', 'cancelled')->sum('total_amount'),
            'first_transaction' => $sales()->orderBy('id')->value('transaction_number'),
            'last_transaction' => $sales()->orderByDesc('id')->value('transaction_number'),
        ];
    }
}
