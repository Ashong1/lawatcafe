<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Support\ReportPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Stock movements for one ReportPeriod, per ingredient. Stock at a moment is
 * worked back from today's count: current stock minus every change logged
 * after that moment.
 */
class StockReportService
{
    public function build(ReportPeriod $p): array
    {
        $sumWhere = fn (string $op, $at) => DB::table('inventory_logs')->where('created_at', $op, $at)
            ->groupBy('ingredient_id')->selectRaw('ingredient_id, SUM(change_amount) as total')->pluck('total', 'ingredient_id');
        $sinceStart = $sumWhere('>=', $p->start);
        $afterEnd = $sumWhere('>', $p->end);

        $byType = DB::table('inventory_logs')->whereBetween('created_at', [$p->start, $p->end])
            ->groupBy('ingredient_id', 'type')->selectRaw('ingredient_id, type, SUM(change_amount) as total')
            ->get()->groupBy('ingredient_id')->map(fn ($rows) => $rows->pluck('total', 'type'));

        $latestCost = DB::table('ingredient_delivery_items')->orderBy('id')->pluck('cost_per_unit', 'ingredient_id');

        $rows = Ingredient::orderBy('name')->get()->map(function (Ingredient $i) use ($sinceStart, $afterEnd, $byType) {
            $t = $byType[$i->id] ?? collect();
            $now = (float) $i->current_stock;

            return [
                'id' => $i->id,
                'ingredient' => $i->name,
                'unit' => $i->unit,
                'start' => round($now - (float) ($sinceStart[$i->id] ?? 0), 2),
                'received' => round((float) ($t['delivery'] ?? 0), 2),
                'used' => round(-(float) ($t['sale'] ?? 0), 2),
                'wasted' => round(-(float) ($t['wastage'] ?? 0), 2),
                'adjusted' => round((float) ($t['adjustment'] ?? 0), 2),
                'end' => round($now - (float) ($afterEnd[$i->id] ?? 0), 2),
                'low' => $now <= (float) $i->low_stock_threshold,
                'now' => $now,
            ];
        });

        $received = DB::table('ingredient_delivery_items')
            ->join('ingredient_deliveries', 'ingredient_deliveries.id', '=', 'ingredient_delivery_items.ingredient_delivery_id')
            ->where('ingredient_deliveries.status', 'confirmed')
            ->whereBetween('ingredient_deliveries.delivery_date', [$p->start, $p->end])
            ->sum(DB::raw('ingredient_delivery_items.quantity * ingredient_delivery_items.cost_per_unit'));

        $reasons = DB::table('inventory_logs')->where('type', 'wastage')->where('change_amount', '<', 0)
            ->whereBetween('created_at', [$p->start, $p->end])->get(['ingredient_id', 'reason'])
            ->groupBy('ingredient_id')->map(fn ($r) => $r->map(fn ($x) => trim(preg_replace('/^Wastage:\s*/', '', $x->reason)))->unique()->implode(', '));

        return [
            'rows' => $rows->all(),
            'received_cost' => round((float) $received, 2),
            'wasted_cost' => round($rows->sum(fn ($r) => $r['wasted'] * (float) ($latestCost[$r['id']] ?? 0)), 2),
            'most_used' => $rows->where('used', '>', 0)->sortByDesc('used')->take(5)->values()->all(),
            'most_wasted' => $rows->where('wasted', '>', 0)->sortByDesc('wasted')->take(5)
                ->map(fn ($r) => $r + ['reasons' => $reasons[$r['id']] ?? ''])->values()->all(),
            'running_low' => $rows->where('low', true)->sortBy('now')->values()->all(),
        ];
    }
}
