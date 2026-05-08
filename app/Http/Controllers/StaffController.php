<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index()
    {
        // Staff only needs to see operational info, not financial info.
        $unusedVouchers = Voucher::where('is_used', false)->count();
        $recentVouchers = Voucher::latest()->take(5)->get();

        return view('staff.dashboard', compact('unusedVouchers', 'recentVouchers'));
    }
}