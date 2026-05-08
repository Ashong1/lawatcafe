<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VoucherController extends Controller
{
    /**
     * Display a listing of all generated vouchers.
     */
    public function index()
    {
        // Fetch all vouchers, showing the newest ones first
        $vouchers = Voucher::latest()->get();
        
        return view('network.vouchers', compact('vouchers'));
    }

    /**
     * Batch generate 5 unique vouchers.
     */
    public function generateBatch()
    {
        $vouchersCreated = 0;
        $batchSize = 5;

        // Loop until we successfully create 5 unique vouchers
        while ($vouchersCreated < $batchSize) {
            $code = 'LAWA-' . strtoupper(Str::random(4));

            // Collision check: only create if the code doesn't already exist
            if (!Voucher::where('code', $code)->exists()) {
                Voucher::create([
                    'code' => $code,
                    'duration_minutes' => 60, // Default 1 hour
                    'is_used' => false,
                ]);
                $vouchersCreated++;
            }
        }

        return redirect()->back()->with('success', "{$batchSize} new vouchers generated successfully!");
    }

    /**
     * Display the printable version of a specific voucher.
     */
    public function print(Voucher $voucher)
    {
        return view('network.print-voucher', compact('voucher'));
    }

    /**
     * Remove the specified voucher from the database.
     */
    public function destroy(Voucher $voucher)
    {
        $voucher->delete();

        return redirect()->back()->with('success', 'Voucher removed from the system.');
    }
}