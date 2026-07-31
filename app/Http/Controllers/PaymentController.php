<?php

namespace App\Http\Controllers;

use App\Models\EwalletPayment;

class PaymentController extends Controller
{
    /**
     * Display a listing of e-wallet payment verification logs.
     */
    public function logs()
    {
        $payments = EwalletPayment::where('sender_details', 'no-reply@gcash.com')
            ->latest()
            ->paginate(50);

        return view('network.verifications', compact('payments'));
    }
}
