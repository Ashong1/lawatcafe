<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Models\Sale;
use App\Models\Ingredient;
use App\Models\Setting;
use App\Models\Shift;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index()
    {
        // Initial load passes initial data
        $activeShift = Shift::where('user_id', auth()->id())->where('status', 'open')->latest()->first();

        $lowStockThreshold = (int) Setting::get('low_stock_threshold', 500);
        $eightySixList = Ingredient::where('current_stock', '<=', $lowStockThreshold)->get();

        $shiftNotes = Setting::get('shift_notes', "Welcome to your shift! No special announcements right now.");

        $pendingOrdersCount = Sale::whereIn('status', ['pending', 'preparing'])->count();

        $unusedVouchers = Voucher::where('is_used', false)->count();

        return view('staff.dashboard', compact(
            'activeShift',
            'eightySixList',
            'shiftNotes',
            'pendingOrdersCount',
            'unusedVouchers'
        ));
    }

    public function getLiveData()
    {
        $activeShift = Shift::where('user_id', auth()->id())->where('status', 'open')->latest()->first();

        $lowStockThreshold = (int) Setting::get('low_stock_threshold', 500);
        $eightySixList = Ingredient::where('current_stock', '<=', $lowStockThreshold)
            ->get(['name', 'current_stock', 'unit'])
            ->map(function($item) {
                return [
                    'name' => $item->name,
                    'current_stock' => $item->current_stock,
                    'unit' => $item->unit,
                    'is_sold_out' => $item->current_stock <= 0
                ];
            });

        $shiftNotes = Setting::get('shift_notes', "Welcome to your shift! No special announcements right now.");
        $pendingOrdersCount = Sale::whereIn('status', ['pending', 'preparing'])->count();
        $unusedVouchers = Voucher::where('is_used', false)->count();

        return response()->json([
            'hasActiveShift' => (bool) $activeShift,
            'shift' => $activeShift ? [
                'started_at' => \Carbon\Carbon::parse($activeShift->started_at)->format('h:i A'),
                'duration' => \Carbon\Carbon::parse($activeShift->started_at)->diffForHumans(),
                'starting_cash' => number_format($activeShift->starting_cash, 2),
                'role' => auth()->user()->role,
            ] : null,
            'eightySixList' => $eightySixList,
            'shiftNotes' => $shiftNotes,
            'pendingOrdersCount' => $pendingOrdersCount,
            'unusedVouchers' => $unusedVouchers,
            'currentTime' => now()->format('l, F jS - h:i A'),
        ]);
    }
}