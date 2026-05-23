<?php

namespace App\Http\Controllers;

use App\Models\EwalletPayment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Display a listing of e-wallet payment verification logs.
     */
    public function logs()
    {
        $payments = EwalletPayment::latest()->paginate(50);
        
        return view('network.verifications', compact('payments'));
    }
}
