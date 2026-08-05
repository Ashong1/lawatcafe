<?php

namespace App\Http\Controllers;

use App\Models\BannedDevice;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Voucher;
use App\Services\Agent\ChatStreamResponder;
use App\Services\Agent\ConversationHistoryService;
use App\Services\Agent\ToolRegistry;
use App\Services\AIService;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    private function resolveTrustedIdentity(Request $request, OpnSenseService $opnsense): array
    {
        $ip = $request->ip();
        $mac = $opnsense->resolveMacForIp($ip);

        if (! $mac) {
            Log::warning("MAC binding: could not resolve MAC for IP {$ip} via ARP table; falling back to redirect-supplied value.");
            $mac = session('clientMac');
        }

        return [$ip, $mac];
    }

    /**
     * Where the "browse the web" buttons point.
     *
     * Falls back to the portal itself rather than a third-party page. The old
     * default was neverssl.com — a plain-HTTP page used as a trick to make a
     * phone's sign-in assistant notice it has internet. It predates the shop
     * having a real portal hostname, and to a paying customer, being dumped on
     * an unbranded stranger's page reads as the Wi-Fi being broken. Set the
     * portal_browse_url setting to bring back a genuine outbound destination.
     */
    private function browseUrl(): string
    {
        return Setting::get('portal_browse_url') ?: route('portal.index');
    }

    /**
     * The device's live session on OPNsense, or null if the firewall has none.
     *
     * This is the authoritative answer to "is this device actually online" —
     * the voucher row only says what the guest *paid for*, which stays true
     * after the session ends. Anything that reports connectivity has to ask
     * the firewall, not the database.
     */
    private function liveSessionFor(string $ip, ?string $mac, OpnSenseService $opnsense): ?array
    {
        $cleanMac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac ?? ''));

        return collect($opnsense->listSessions())->first(function ($s) use ($ip, $cleanMac) {
            $sessionIp = str_replace('/32', '', $s['ipAddress'] ?? '');
            $sessionMac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $s['macAddress'] ?? ''));

            return $sessionIp === $ip || (! empty($cleanMac) && $sessionMac === $cleanMac);
        });
    }

    /**
     * The most recent redeemed voucher belonging to this device, matched on
     * IP or (preferably) the MAC blind index. Shared by index() and the
     * RFC 8908 captive-portal API so the "which session is this device on"
     * question is only answered in one place — they previously would have
     * had to duplicate this query and could drift apart.
     */
    private function activeVoucherFor(string $ip, ?string $mac): ?Voucher
    {
        return Voucher::where('is_used', true)
            ->where(function ($query) use ($ip, $mac) {
                $query->where('ip_address', $ip);
                if (! empty($mac)) {
                    $query->orWhere('mac_address_hash', Voucher::hashMac($mac));
                }
            })
            ->orderBy('used_at', 'desc')
            ->first();
    }

    /**
     * Seconds left on a redeemed voucher, or null if it has no time left.
     * Never returns a negative — an expired voucher is "no session", not
     * "negative session".
     */
    private function secondsRemainingOn(?Voucher $voucher): ?int
    {
        if (! $voucher || ! $voucher->used_at) {
            return null;
        }

        $remaining = (int) round(now()->diffInSeconds(
            $voucher->used_at->copy()->addMinutes($voucher->duration_minutes),
            false
        ));

        return $remaining > 0 ? $remaining : null;
    }

    /**
     * RFC 8908 Captive Portal API.
     *
     * Advertised to clients via DHCP option 114 (RFC 8910) from Kea on
     * OPNsense — see docs/CAPTIVE_PORTAL.md. iOS 14+ and Android 11+ poll
     * this and render the remaining session time natively in Wi-Fi settings,
     * which is the only way a guest can watch their time tick down WITHOUT
     * keeping a browser tab open: the Captive Network Assistant that shows
     * the portal is dismissed by the OS the moment the device is authorized,
     * taking the portal's own countdown with it.
     *
     * Deliberately unauthenticated and side-effect free (a pure read) — it's
     * reachable pre-auth by definition, so it must not trust anything the
     * client sends beyond its own network identity, and must not mutate.
     */
    public function captivePortalApi(Request $request, OpnSenseService $opnsense)
    {
        [$ip, $mac] = $this->resolveTrustedIdentity($request, $opnsense);

        $secondsRemaining = $this->secondsRemainingOn($this->activeVoucherFor($ip, $mac));

        // Paid-for time is not the same as being let through. A voucher keeps
        // its remaining minutes after the guest hits Disconnect, after
        // EnforceSessionLimits reaps an idle session, and after OPNsense
        // restarts — in all three the firewall is blocking traffic. Reporting
        // captive:false off the voucher alone told the OS "you are online"
        // while nothing loaded, and because the OS then believes there is no
        // portal, it never re-opens the sign-in window either.
        $hasLiveSession = $this->liveSessionFor($ip, $mac, $opnsense) !== null;

        $isCaptive = $secondsRemaining === null || ! $hasLiveSession || $this->isMacBanned($mac);

        $payload = [
            'captive' => $isCaptive,
            'user-portal-url' => route('portal.index'),
            'venue-info-url' => route('portal.menu'),
            'can-extend-session' => true,
        ];

        // RFC 8908 §5: seconds-remaining is only meaningful for a client that
        // currently has access, and is omitted entirely otherwise.
        if (! $isCaptive) {
            $payload['seconds-remaining'] = $secondsRemaining;
        }

        return response()
            ->json($payload)
            // The RFC mandates this exact media type — a plain application/json
            // response is ignored by the OS captive-portal agents.
            ->header('Content-Type', 'application/captive+json')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Whether this MAC is on the (BlocklistController-managed) ban list.
     * Compared with separators/case stripped so "AA:BB:.." and "aa-bb-.."
     * are treated as the same address regardless of how each was stored.
     */
    private function isMacBanned(?string $mac): bool
    {
        if (! $mac) {
            return false;
        }

        $clean = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac));

        return BannedDevice::get()->contains(function ($device) use ($clean) {
            return strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $device->mac_address)) === $clean;
        });
    }

    // Show the main captive portal page
    public function index(Request $request, OpnSenseService $opnsense)
    {
        // 1. Capture OPNsense redirect parameters
        if ($request->has('clientIp')) {
            session(['clientIp' => $request->query('clientIp')]);
        }
        if ($request->has('clientMac')) {
            session(['clientMac' => $request->query('clientMac')]);
        }
        if ($request->has('zone')) {
            session(['zone' => $request->query('zone')]);
        }

        [$ip, $mac] = $this->resolveTrustedIdentity($request, $opnsense);

        // 2. Check if already connected
        $activeSession = $this->liveSessionFor($ip, $mac, $opnsense);

        if ($activeSession) {
            // VERIFY IF VOUCHER IS STILL VALID
            $voucher = $this->activeVoucherFor($ip, $mac);

            if ($voucher) {
                $expirationTime = $voucher->used_at->addMinutes($voucher->duration_minutes);
                if (now()->greaterThan($expirationTime)) {
                    // DISCONNECT EXPIRED SESSION
                    $opnsense->disconnectDevice($activeSession['sessionId']);

                    return redirect()->route('portal.index')->with('error', 'Your session has expired. Please enter a new voucher.');
                }

                return view('portal.status', [
                    'session' => $activeSession,
                    'startTime' => Carbon::createFromTimestamp($activeSession['startTime']),
                    'expirationTime' => $expirationTime,
                    'userName' => $activeSession['userName'] ?? 'Guest',
                    'browseUrl' => $this->browseUrl(),
                ]);
            }
        }

        return view('portal.index');
    }

    // Handle session termination
    public function disconnect(Request $request, OpnSenseService $opnsense, TrafficShapingService $shaping)
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

                return $sessionIp === $ip || (! empty($cleanMac) && $sessionMac === $cleanMac);
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

    // The self-service GCash tab was removed from the portal UI (system is
    // cash-only now) — reject direct POSTs here too, not just hide the
    // button, so the flow is actually closed off rather than just hidden.
    public function verifyPayment(Request $request)
    {
        $message = 'This location only accepts cash. Please pay at the counter.';

        return $request->wantsJson()
            ? response()->json(['success' => false, 'message' => $message], 422)
            : back()->with('error', $message);
    }

    // Handle standard passcode entry
    public function authenticate(Request $request, OpnSenseService $opnsense, TrafficShapingService $shaping)
    {
        $request->validate([
            'passcode' => 'required|string',
        ]);

        return DB::transaction(function () use ($request, $opnsense, $shaping) {
            // 1. Verify the Lawa't Voucher in your Database.
            // Codes are issued uppercase (VoucherService), the field renders
            // uppercase, and a phone keyboard will happily add a trailing space
            // after autocorrect — so collapse whitespace and case before looking
            // up rather than blaming the guest for their keyboard.
            $code = strtoupper(preg_replace('/\s+/', '', (string) $request->passcode));

            $voucher = Voucher::where('code', $code)
                ->lockForUpdate()
                ->first();

            // Guests routinely type the code without the printed dash. Falling
            // back to a separator-insensitive match beats telling someone their
            // valid code is wrong. Only runs when the indexed lookup missed, and
            // this table holds one row per voucher ever sold, not per request.
            if (! $voucher) {
                $voucher = Voucher::whereRaw("REPLACE(code, '-', '') = ?", [str_replace('-', '', $code)])
                    ->lockForUpdate()
                    ->first();
            }

            // Distinguish "doesn't exist / mistyped" from "already redeemed" —
            // a single generic message for both makes it impossible for a guest
            // (or the staff helping them) to tell whether they mistyped the code
            // or are trying to reuse one that already worked.
            if (! $voucher) {
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

            if (! $authorized) {
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
    // Same reasoning as verifyPayment() — the receipt-OCR-to-reference-number
    // flow it fed only existed to support the now-removed GCash tab.
    public function uploadReceipt(Request $request)
    {
        $message = 'This location only accepts cash. Please pay at the counter.';

        return $request->wantsJson()
            ? response()->json(['success' => false, 'message' => $message], 422)
            : back()->with('error', $message);
    }

    public function chat(Request $request, AIService $ai, OpnSenseService $opnsense, ConversationHistoryService $conversations, ChatStreamResponder $responder)
    {
        // history.*.role is deliberately restricted to user/assistant: this
        // endpoint is unauthenticated and reachable directly (not just via
        // the JS widget), so nothing stops an attacker from POSTing
        // {"history":[{"role":"system","content":"..."}]} to inject a fake
        // system-level instruction ahead of the real one — a classic prompt
        // injection vector via conversation history rather than the message
        // itself. The array cap here is a generous DoS backstop, not a
        // conversation-length limit — slidingWindow() below is what actually
        // bounds what reaches the model (see DashboardController::adminChat()).
        $request->validate([
            'message' => 'required|string|max:500',
            'history' => 'nullable|array|max:100',
            'history.*.role' => 'required_with:history|in:user,assistant',
            // nullable, not required_with — see the matching fix (and full
            // reasoning) on DashboardController::adminChat().
            'history.*.content' => 'nullable|string|max:2000',
        ]);

        $messages = [['role' => 'system', 'content' => $ai->buildGuestSystemPrompt()]];
        foreach ($conversations->slidingWindow($request->history ?? [], 20) as $msg) {
            if (empty($msg['content'])) {
                continue;
            }
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $request->message];

        [$ip, $mac] = $this->resolveTrustedIdentity($request, $opnsense);
        $context = ['ip' => $ip, 'mac' => $mac];

        // The shared agent-chat.blade.php widget (embedded here, floating on
        // admin/staff) always requests `Accept: text/event-stream` and reads
        // the response as a chunked SSE stream — this used to return a plain
        // JSON body instead, which the client's stream reader never matched
        // against its `data: ...\n\n` parser, so a guest's reply silently
        // never arrived. No $conversation/$conversations here — guest chat
        // has no durable per-user history (shared kiosks, no account to key it off).
        return $responder->stream(
            $messages,
            ToolRegistry::AUDIENCE_GUEST,
            null,
            $context,
            $request->message,
            '☕ Serving guests! Check Menu or Wi-Fi tabs!',
        );
    }

    // Show the digital menu for Walled Garden access
    /**
     * Guest-facing digital menu. Reads the real product catalogue — this used
     * to be a hardcoded mockup in the Blade view (six invented items with
     * invented prices), so the menu guests saw had no relationship to what the
     * shop actually sells or charges.
     *
     * Only 'Active' products appear, matching every other customer-facing
     * surface (POS, AI menu context).
     */
    public function menu()
    {
        $categories = Category::orderBy('sort_order')->orderBy('name')->get();

        $byCategory = Product::where('status', 'Active')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        $menu = $categories
            ->map(fn (Category $c) => [
                'name' => $c->name,
                // Same fallback the admin product/category tables use, so an
                // unset or bad icon can't blow up a guest-facing page.
                'icon' => 'lucide-'.($c->icon ?: 'coffee'),
                'description' => $c->description,
                'items' => $byCategory->get($c->name, collect()),
            ])
            // An empty category reads as a broken menu to a customer.
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->values();

        // products.category is free text with no FK to categories (see
        // docs/DATABASE.md), so a product can carry a category name that no
        // longer has a matching row. Those would silently vanish from the
        // guest menu otherwise — surface them rather than lose them.
        // collect() re-wraps as a BASE collection on purpose: groupBy() on an
        // Eloquent collection returns an Eloquent one, whose except()/reject()
        // are overridden to match on model primary keys and would call
        // getKey() on these grouped sub-collections.
        $knownCategories = $categories->pluck('name')->all();
        $uncategorised = collect($byCategory)
            ->reject(fn ($items, $categoryName) => in_array($categoryName, $knownCategories, true))
            ->flatten();

        if ($uncategorised->isNotEmpty()) {
            $menu->push([
                'name' => 'More',
                'icon' => 'lucide-coffee',
                'description' => null,
                'items' => $uncategorised,
            ]);
        }

        return view('portal.menu', ['menu' => $menu]);
    }

    /**
     * Intermediate page to handle the OPNsense form submission.
     * This is required for some devices to recognize they are connected to the network.
     */
    public function unlock()
    {
        $opnsenseIp = Setting::get('opnsense_ip', '192.168.1.1');
        $zone = session('zone', Setting::get('opnsense_zone', '0'));

        return view('portal.unlock', compact('opnsenseIp', 'zone'));
    }

    /**
     * Show the success page after connection.
     */
    public function success()
    {
        // Plain HTTP on purpose: this link exists to make the phone's
        // captive-network assistant notice working internet and dismiss
        // itself. An HTTPS target defeats that (the assistant can't complete
        // the handshake pre-validation on some stacks), and an HSTS-preloaded
        // host would silently upgrade and do the same. Configurable so the
        // shop isn't permanently tied to a third-party domain.
        return view('portal.success', [
            'browseUrl' => $this->browseUrl(),
        ]);
    }
}
