<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CaptivePortalController extends Controller
{
    /**
     * Resolve the (ip, mac) pair to trust for authorization/binding decisions.
     *
     * The IP is taken from the actual request (never a client-suppliable
     * query string), and the MAC is cross-checked against OPNsense's own ARP
     * table rather than trusting whatever `clientMac` the guest's browser
     * carried in from the redirect. This closes the gap where a guest could
     * previously pass `?clientIp=<victim-ip>` and have that IP authorized
     * outright.
     */
    private function resolveTrustedIdentity(Request $request, \App\Services\OpnSenseService $opnsense): array
    {
        $ip = $request->ip();
        $mac = $opnsense->resolveMacForIp($ip);

        if (!$mac) {
            Log::warning("MAC binding: could not resolve MAC for IP {$ip} via ARP table; falling back to redirect-supplied value.");
            $mac = session('clientMac');
        }

        return [$ip, $mac];
    }

    /**
     * Whether this MAC is on the (BlocklistController-managed) ban list.
     * Compared with separators/case stripped so "AA:BB:.." and "aa-bb-.."
     * are treated as the same address regardless of how each was stored.
     */
    private function isMacBanned(?string $mac): bool
    {
        if (!$mac) {
            return false;
        }

        $clean = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac));

        return \App\Models\BannedDevice::get()->contains(function ($device) use ($clean) {
            return strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $device->mac_address)) === $clean;
        });
    }
    // Show the main captive portal page
    public function index(Request $request, \App\Services\OpnSenseService $opnsense)
    {
        // 1. Capture OPNsense redirect parameters
        if ($request->has('clientIp')) session(['clientIp' => $request->query('clientIp')]);
        if ($request->has('clientMac')) session(['clientMac' => $request->query('clientMac')]);
        if ($request->has('zone')) session(['zone' => $request->query('zone')]);

        [$ip, $mac] = $this->resolveTrustedIdentity($request, $opnsense);

        $qrCode = \App\Models\Setting::get('payment_qr_code');

        // Fetch and sort durations
        $durationsRaw = \App\Models\Setting::get('voucher_durations', '{"20":60,"50":180,"100":1440}');
        $durations = json_decode($durationsRaw, true);
        if ($durations) {
            ksort($durations); // Sort by price ascending
        } else {
            $durations = [];
        }

        // 2. Check if already connected
        $sessions = $opnsense->listSessions();
        $activeSession = collect($sessions)->filter(function($s) use ($ip, $mac) {
            $sessionIp = str_replace('/32', '', $s['ipAddress'] ?? '');
            $sessionMac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $s['macAddress'] ?? ''));
            $cleanMac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac ?? ''));
            
            return $sessionIp === $ip || (!empty($cleanMac) && $sessionMac === $cleanMac);
        })->first();

        if ($activeSession) {
            // VERIFY IF VOUCHER IS STILL VALID
            $voucher = Voucher::where('is_used', true)
                ->where(function($query) use ($ip, $mac) {
                    $query->where('ip_address', $ip);
                    if (!empty($mac)) {
                        $query->orWhere('mac_address', $mac);
                    }
                })
                ->orderBy('used_at', 'desc')
                ->first();

            if ($voucher) {
                $expirationTime = $voucher->used_at->addMinutes($voucher->duration_minutes);
                if (now()->greaterThan($expirationTime)) {
                    // DISCONNECT EXPIRED SESSION
                    $opnsense->disconnectDevice($activeSession['sessionId']);
                    return redirect()->route('portal.index')->with('error', 'Your session has expired. Please enter a new voucher.');
                }

                return view('portal.status', [
                    'session' => $activeSession,
                    'startTime' => \Carbon\Carbon::createFromTimestamp($activeSession['startTime']),
                    'expirationTime' => $expirationTime,
                    'userName' => $activeSession['userName'] ?? 'Guest',
                    'qrCode' => $qrCode,
                    'durations' => $durations,
                ]);
            }
        }

        return view('portal.index', compact('qrCode', 'durations'));
    }

    // Handle session termination
    public function disconnect(Request $request, \App\Services\OpnSenseService $opnsense, \App\Services\TrafficShapingService $shaping)
    {
        $sessionId = $request->input('session_id');

        if ($sessionId) {
            [$ip, $mac] = $this->resolveTrustedIdentity($request, $opnsense);
            $cleanMac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac ?? ''));

            $ownsSession = collect($opnsense->listSessions())->contains(function ($s) use ($sessionId, $ip, $cleanMac) {
                if (($s['sessionId'] ?? null) != $sessionId) {
                    return false;
                }
                $sessionIp = str_replace('/32', '', $s['ipAddress'] ?? '');
                $sessionMac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $s['macAddress'] ?? ''));
                return $sessionIp === $ip || (!empty($cleanMac) && $sessionMac === $cleanMac);
            });

            if ($ownsSession) {
                $opnsense->disconnectDevice($sessionId);
                $shaping->releaseIp($ip, $opnsense);
            } else {
                Log::warning("Portal disconnect: rejected attempt by {$ip} to disconnect session {$sessionId} it does not own.");
            }
        }

        return redirect()->route('portal.index')->with('message', 'Successfully disconnected.');
    }

    // Handle e-wallet reference number verification
    public function verifyPayment(Request $request, \App\Services\OpnSenseService $opnsense, \App\Services\TrafficShapingService $shaping)
    {
        $request->validate([
            'reference_number' => 'required|string',
        ]);

        return \Illuminate\Support\Facades\DB::transaction(function() use ($request, $opnsense, $shaping) {
            $payment = \App\Models\EwalletPayment::where('reference_number', $request->reference_number)
                                                 ->where('is_used', false)
                                                 ->lockForUpdate() // Lock to prevent race conditions
                                                 ->first();

            if (!$payment) {
                return back()->with('error', 'GCash payment not found or already verified. If you just paid, please wait a minute for the system to process the GCash notification email.');
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

            // Authorize device on OPNsense — IP/MAC resolved from the gateway,
            // never trusted from client-suppliable input.
            [$ip, $mac] = $this->resolveTrustedIdentity($request, $opnsense);

            if ($this->isMacBanned($mac)) {
                Log::warning("Portal verify-payment: rejected banned device {$mac} ({$ip}).");
                return back()->with('error', 'This device has been blocked from network access. Please see staff for assistance.');
            }

            // Generate the voucher
            $code = 'LAWA-' . strtoupper(\Illuminate\Support\Str::random(4));
            
            $voucher = \App\Models\Voucher::create([
                'code' => $code,
                'duration_minutes' => $duration,
                'tier' => 'premium',
                'is_used' => true,
                'used_at' => now(),
                'ip_address' => $ip,
                'mac_address' => $mac,
            ]);

            // Mark payment as used
            $payment->update(['is_used' => true]);

            // Authorize device via backend API
            $opnsense->authorizeDevice($ip, $code);

            $shaping->assignTier($voucher, $ip, $opnsense);

            return redirect()->route('portal.success')->with('passcode', $code);
        });
    }

    // Handle standard passcode entry
    public function authenticate(Request $request, \App\Services\OpnSenseService $opnsense, \App\Services\TrafficShapingService $shaping)
    {
        $request->validate([
            'passcode' => 'required|string',
        ]);

        return \Illuminate\Support\Facades\DB::transaction(function() use ($request, $opnsense, $shaping) {
            // 1. Verify the Lawa't Voucher in your Database
            $code = trim($request->passcode);
            $voucher = \App\Models\Voucher::where('code', $code)
                              ->lockForUpdate()
                              ->first();

            // Distinguish "doesn't exist / mistyped" from "already redeemed" —
            // a single generic message for both makes it impossible for a guest
            // (or the staff helping them) to tell whether they mistyped the code
            // or are trying to reuse one that already worked.
            if (!$voucher) {
                return back()->with('error', 'That code doesn\'t match any voucher — double-check it against your receipt.');
            }

            if ($voucher->is_used) {
                return back()->with('error', 'This code has already been used.');
            }

            [$ip, $mac] = $this->resolveTrustedIdentity($request, $opnsense);

            if ($this->isMacBanned($mac)) {
                Log::warning("Portal authenticate: rejected banned device {$mac} ({$ip}).");
                return back()->with('error', 'This device has been blocked from network access. Please see staff for assistance.');
            }

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

            $shaping->assignTier($voucher, $ip, $opnsense);

            return redirect()->route('portal.success');
        });
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

    public function chat(Request $request, \App\Services\AIService $ai, \App\Services\Agent\ToolCallOrchestrator $orchestrator, \App\Services\OpnSenseService $opnsense)
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'history' => 'nullable|array'
        ]);

        $messages = [['role' => 'system', 'content' => $ai->buildGuestSystemPrompt()]];
        foreach ($request->history ?? [] as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $request->message];

        [$ip, $mac] = $this->resolveTrustedIdentity($request, $opnsense);
        $context = ['ip' => $ip, 'mac' => $mac];

        $result = $orchestrator->run($messages, \App\Services\Agent\ToolRegistry::AUDIENCE_GUEST, null, $context);

        return response()->json(['reply' => $result['reply'] ?? "☕ Serving guests! Check Menu or Wi-Fi tabs!"]);
    }

    // Show the digital menu for Walled Garden access
    public function menu()
    {
        return view('portal.menu');
    }

    /**
     * Intermediate page to handle the OPNsense form submission.
     * This is required for some devices to recognize they are connected to the network.
     */
    public function unlock()
    {
        $opnsenseIp = \App\Models\Setting::get('opnsense_ip', '192.168.1.1');
        $zone = session('zone', \App\Models\Setting::get('opnsense_zone', '0'));
        return view('portal.unlock', compact('opnsenseIp', 'zone'));
    }

    /**
     * Show the success page after connection.
     */
    public function success()
    {
        return view('portal.success');
    }
}