<?php

namespace App\Services\Agent;

use App\Models\BannedDevice;
use App\Models\Ingredient;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\Voucher;
use App\Services\OpnSenseService;
use Illuminate\Support\Facades\DB;

/**
 * Pure deterministic read/analysis over both the POS and network domains —
 * no LLM call here. Thresholds decide WHETHER something is anomalous
 * (auditable, testable, cheap); the AI layer (see RunAgentAnalysis) only
 * narrates and picks actions on top of whatever this returns.
 */
class CrossDomainCorrelationService
{
    public function __construct(protected OpnSenseService $opnsense) {}

    /** @return array{signals: array} */
    public function run(): array
    {
        $signals = array_values(array_filter([
            $this->voucherRevenueDivergence(),
            ...$this->repeatMacAbuse(),
            ...$this->bannedDeviceReentry(),
            ...$this->lowStockHighDemandMismatch(),
        ]));

        return ['signals' => $signals];
    }

    /**
     * Compares the last 24h's voucher redemption count and POS revenue against
     * the previous 24h. If redemptions are trending up while revenue is flat
     * or down beyond the configured threshold, that's a possible abuse signal
     * (guests buying/using Wi-Fi without buying anything, or voucher fraud).
     */
    private function voucherRevenueDivergence(): ?array
    {
        $threshold = (float) Setting::get('correlation_divergence_threshold', 50);

        $now = now();
        $currentRedemptions = Voucher::where('is_used', true)->where('used_at', '>=', $now->copy()->subDay())->count();
        $priorRedemptions = Voucher::where('is_used', true)->whereBetween('used_at', [$now->copy()->subDays(2), $now->copy()->subDay()])->count();

        $currentRevenue = (float) Sale::revenue()->where('created_at', '>=', $now->copy()->subDay())->sum('total_amount');
        $priorRevenue = (float) Sale::revenue()->whereBetween('created_at', [$now->copy()->subDays(2), $now->copy()->subDay()])->sum('total_amount');

        $redemptionChange = $priorRedemptions > 0 ? (($currentRedemptions - $priorRedemptions) / $priorRedemptions) * 100 : ($currentRedemptions > 0 ? 100 : 0);
        $revenueChange = $priorRevenue > 0 ? (($currentRevenue - $priorRevenue) / $priorRevenue) * 100 : ($currentRevenue > 0 ? 100 : 0);

        $divergence = $redemptionChange - $revenueChange;

        if ($divergence < $threshold || $currentRedemptions < 3) {
            return null;
        }

        return [
            'type' => 'voucher_revenue_divergence',
            'severity' => 'warning',
            'summary' => "Wi-Fi voucher redemptions are up {$this->round($redemptionChange)}% day-over-day while POS revenue changed {$this->round($revenueChange)}% — possible Wi-Fi-only usage without purchases.",
            'data' => [
                'current_redemptions' => $currentRedemptions,
                'prior_redemptions' => $priorRedemptions,
                'current_revenue' => $currentRevenue,
                'prior_revenue' => $priorRevenue,
            ],
        ];
    }

    /** Many voucher redemptions from a single MAC in a short window. */
    private function repeatMacAbuse(): array
    {
        $windowHours = (int) Setting::get('correlation_repeat_mac_window_hours', 24);
        $minCount = (int) Setting::get('correlation_repeat_mac_threshold', 5);

        // mac_address is encrypted (random IV per row), so grouping/matching
        // happens on mac_address_hash — a deterministic HMAC of the MAC —
        // and one representative row per hash is fetched to display the
        // actual (decrypted) address.
        $offenders = Voucher::where('used_at', '>=', now()->subHours($windowHours))
            ->whereNotNull('mac_address_hash')
            ->select('mac_address_hash', DB::raw('count(*) as redemption_count'))
            ->groupBy('mac_address_hash')
            ->having('redemption_count', '>=', $minCount)
            ->get();

        return $offenders->map(function ($row) use ($windowHours) {
            $sample = Voucher::where('mac_address_hash', $row->mac_address_hash)->latest('used_at')->first();

            return [
                'type' => 'repeat_mac_abuse',
                'severity' => 'danger',
                'summary' => "Device {$sample->mac_address} redeemed {$row->redemption_count} vouchers in the last {$windowHours}h.",
                'data' => ['mac_address' => $sample->mac_address, 'redemption_count' => $row->redemption_count],
            ];
        })->all();
    }

    /** A banned device still showing an active session — the block isn't holding at the firewall. */
    private function bannedDeviceReentry(): array
    {
        $bannedMacs = BannedDevice::pluck('mac_address')->map(fn ($m) => $this->normalizeMac($m))->filter();
        if ($bannedMacs->isEmpty()) {
            return [];
        }

        $sessions = collect($this->opnsense->listSessions());
        $activeMacs = $sessions->pluck('macAddress')->map(fn ($m) => $this->normalizeMac($m))->filter();

        $reentered = $bannedMacs->intersect($activeMacs);

        return $reentered->map(fn ($mac) => [
            'type' => 'banned_device_reentry',
            'severity' => 'danger',
            'summary' => "Banned device {$mac} has an active network session — the firewall block is not holding.",
            'data' => ['mac_address' => $mac],
        ])->values()->all();
    }

    /** Ingredients at/below threshold whose linked products are still selling — these need a PO. */
    private function lowStockHighDemandMismatch(): array
    {
        $lowStock = Ingredient::whereColumn('current_stock', '<=', 'low_stock_threshold')->get();
        if ($lowStock->isEmpty()) {
            return [];
        }

        $signals = [];
        foreach ($lowStock as $ingredient) {
            $productIds = $ingredient->products()->pluck('products.id');
            if ($productIds->isEmpty()) {
                continue;
            }

            $recentSales = SaleItem::whereIn('product_id', $productIds)
                ->whereHas('sale', fn ($q) => $q->where('status', '!=', 'cancelled'))
                ->where('created_at', '>=', now()->subDays(7))
                ->sum('quantity');

            if ($recentSales > 0) {
                $signals[] = [
                    'type' => 'low_stock_high_demand',
                    'severity' => 'warning',
                    'summary' => "{$ingredient->name} is low ({$ingredient->current_stock}{$ingredient->unit}) but its products sold {$recentSales} unit(s) in the last 7 days.",
                    'data' => ['ingredient_id' => $ingredient->id, 'ingredient_name' => $ingredient->name, 'recent_sales_qty' => (float) $recentSales],
                ];
            }
        }

        return $signals;
    }

    private function normalizeMac(?string $mac): string
    {
        return strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac ?? ''));
    }

    private function round(float $n): float
    {
        return round($n, 1);
    }
}
