<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;

class CaptivePortalController extends Controller
{
    // Show the main captive portal page
    public function index()
    {
        return view('portal.index');
    }

    // Handle standard passcode entry
    public function authenticate(Request $request)
    {
        $request->validate([
            'passcode' => 'required|string',
        ]);

        // Find the voucher
        $voucher = Voucher::where('code', $request->passcode)
                          ->where('is_used', false)
                          ->first();

        if (!$voucher) {
            return back()->with('error', 'Invalid or expired passcode. Please try again.');
        }

        // TODO: In the next phase, we will add the OPNsense Router API call here
        // to whitelist the user's MAC address before marking the voucher as used.

        $voucher->update(['is_used' => true]);

        return redirect()->route('portal.success');
    }

    // Handle e-wallet receipt uploads
    public function uploadReceipt(Request $request)
    {
        $request->validate([
            'receipt' => 'required|image|max:5120', // Max 5MB
        ]);

        // TODO: In the next phase, we will send this image to Gemini Vision AI for OCR.
        
        return back()->with('message', 'Receipt uploaded! Verifying with AI...');
    }

    // Show the success page after connection
    public function success()
    {
        return view('portal.success');
    }
}