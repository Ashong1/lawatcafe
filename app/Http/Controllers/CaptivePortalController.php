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
    public function verifyPayment(Request $request, \App\Services\OpnSenseService $opnsense)
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

        // Determine duration based on amount from dynamic settings
        $durations = json_decode(\App\Models\Setting::get('voucher_durations', '{"20":60,"50":180,"100":1440}'), true);
        $amount = (float) $payment->amount;
        $duration = 0;

        // Sort keys descending to find the highest matching tier
        krsort($durations);
        foreach ($durations as $minAmount => $mins) {
            if ($amount >= (float)$minAmount) {
                $duration = (int)$mins;
                break;
            }
        }

        if ($duration === 0) {
            return back()->with('error', 'Insufficient amount for a Wi-Fi voucher. Minimum is ₱20.00.');
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
    public function uploadReceipt(Request $request, \App\Services\AIService $ai)
    {
        $request->validate([
            'receipt' => 'required|image|max:5120', // Max 5MB
        ]);

        // Store the image temporarily
        $path = $request->file('receipt')->store('temp_receipts', 'local');

        // Pass this image to the Gemini/OpenRouter API for OCR.
        $aiResult = $ai->extractPaymentDetails($path);
        
        // Cleanup temp file
        \Illuminate\Support\Facades\Storage::disk('local')->delete($path);

        if ($aiResult && !empty($aiResult['reference_number'])) {
            // Found a ref number, store it in session so the view can populate it
            session()->flash('ai_ref', $aiResult['reference_number']);
            return back()->with('message', 'Receipt parsed! Please confirm the reference number and verify.');
        }
        
        return back()->with('error', 'Could not clearly read the reference number from the receipt. Please enter it manually.');
    }

    public function chat(Request $request, \App\Services\AIService $ai)
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'history' => 'nullable|array'
        ]);

        $reply = $ai->chat($request->message, $request->history ?? []);
        return response()->json(['reply' => $reply]);
    }

    // Show the success page after connection
    public function success()
    {
        return view('portal.success');
    }
}