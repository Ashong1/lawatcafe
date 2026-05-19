<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;

class CaptivePortalController extends Controller
{
    // Show the main captive portal page
    public function index(Request $request)
    {
        $qrCode = \App\Models\Setting::get('payment_qr_code');
        
        // Capture OPNsense redirect parameters if they exist
        if ($request->has('clientIp')) session(['clientIp' => $request->query('clientIp')]);
        if ($request->has('clientMac')) session(['clientMac' => $request->query('clientMac')]);
        if ($request->has('zone')) session(['zone' => $request->query('zone')]);

        return view('portal.index', compact('qrCode'));
    }

    // Handle e-wallet reference number verification
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'reference_number' => 'required|string',
        ]);

        $payment = \App\Models\EwalletPayment::where('reference_number', $request->reference_number)
                                             ->where('is_used', false)
                                             ->first();

        if (!$payment) {
            return back()->with('error', 'Payment not found or already verified. If you just paid, please wait a minute for the system to process the email.');
        }

        // Determine duration based on amount (matching POS logic)
        $duration = 0;
        $amount = (float) $payment->amount;

        if ($amount >= 100) {
            $duration = 1440; // 24 Hours
        } elseif ($amount >= 50) {
            $duration = 180;  // 3 Hours
        } elseif ($amount >= 20) {
            $duration = 60;   // 1 Hour
        } else {
            return back()->with('error', 'Insufficient amount for a Wi-Fi voucher. Minimum is ₱20.00.');
        }

        // Generate the voucher
        $code = 'LAWA-' . strtoupper(\Illuminate\Support\Str::random(4));
        
        \App\Models\Voucher::create([
            'code' => $code,
            'duration_minutes' => $duration,
            'is_used' => true,
            'used_at' => now(),
            'ip_address' => session('clientIp', $request->ip()),
            'mac_address' => session('clientMac'),
        ]);

        // Mark payment as used
        $payment->update(['is_used' => true]);

        // Serve the Auto-Unlock view to the phone
        return view('network.opnsense_unlock', [
            'opnsenseIp' => '192.168.2.251',
            'zone' => '0'
        ]);
    }

    // Handle standard passcode entry
    public function authenticate(Request $request)
    {
        $request->validate([
            'passcode' => 'required|string',
        ]);

        // 1. Verify the Lawa't Voucher in your Database
        $voucher = \App\Models\Voucher::where('code', $request->passcode)
                          ->where('is_used', false)
                          ->first();

        // If the voucher is wrong, we send them back with the red error
        if (!$voucher) {
            return back()->with('error', 'Invalid or expired voucher code.');
        }

        // 2. Mark voucher as used in the database
        $voucher->update([
            'is_used' => true,
            'used_at' => now(),
            'ip_address' => session('clientIp', $request->ip()),
            'mac_address' => session('clientMac'),
        ]);

        // 3. STOP trying to use the API here! 
        // Instead, serve the new view that forces the phone to unlock itself.
        return view('network.opnsense_unlock', [
            'opnsenseIp' => '192.168.2.251',
            'zone' => '0' 
        ]);
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