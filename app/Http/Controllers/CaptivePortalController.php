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

        // Authorize device on OPNsense
        $ip = session('clientIp', $request->ip());
        $mac = session('clientMac', $request->query('clientMac') ?? $request->input('mac'));
        
        // Generate the voucher
        $code = 'LAWA-' . strtoupper(\Illuminate\Support\Str::random(4));
        
        \App\Models\Voucher::create([
            'code' => $code,
            'duration_minutes' => $duration,
            'is_used' => true,
            'used_at' => now(),
            'ip_address' => $ip,
            'mac_address' => $mac,
        ]);

        // Mark payment as used
        $payment->update(['is_used' => true]);

        // Authorize device via backend API
        $opnsense->authorizeDevice($ip, $code);

        return redirect()->route('portal.success')->with('passcode', $code);
    }

    // Handle standard passcode entry
    public function authenticate(Request $request, \App\Services\OpnSenseService $opnsense)
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

        $ip = session('clientIp', $request->ip());
        $mac = session('clientMac', $request->query('clientMac') ?? $request->input('mac'));

        // 2. Authorize via backend API first
        $authorized = $opnsense->authorizeDevice($ip, $voucher->code);

        if (!$authorized) {
            return back()->with('error', 'Failed to communicate with the firewall. Please try again.');
        }

        // 3. Mark voucher as used in the database
        $voucher->update([
            'is_used' => true,
            'used_at' => now(),
            'ip_address' => $ip,
            'mac_address' => $mac,
        ]);

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