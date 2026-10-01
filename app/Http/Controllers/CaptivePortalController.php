<?php

namespace App\Http\Controllers;

use App\Models\BannedDevice;
use App\Models\Category;
use App\Models\PortalEvent;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\SystemAlert;
use App\Services\Agent\ChatStreamResponder;
use App\Services\Agent\ConversationHistoryService;
use App\Services\Agent\LessonLibrary;
use App\Services\Agent\ToolRegistry;
use App\Services\AIService;
use App\Services\NetworkHealthService;
use App\Services\OpnSenseService;
use App\Services\PortalQuickReplies;
use App\Services\QrCodeService;
use App\Services\TrafficShapingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CaptivePortalController extends Controller
{
    /**
     * Resolve the (ip, mac) pair to trust for authorization/binding decisions.
     *
     * The IP is taken from the actual request (never a client-suppliable
     * query string), and the MAC is cross-checked against OPNsense's own ARP
     * table rather than trusting whatever `clientMac` the guest's browser
     * carried in from the redirect — otherwise a guest could pass
     * `?clientIp=<victim-ip>` and get that IP authorized.
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
     * Where to send a device the instant its session goes live.
     *
     * A phone's sign-in window closes only when the OS's own connectivity probe
     * succeeds, and a page served by the portal doesn't count as internet — so
     * the target is the platform's probe endpoint (the address the OS already
     * wants, with nothing on it), never the portal or a third-party site.
     * Plain HTTP only: the probes are HTTP, and HTTPS can't complete through a
     * portal that is mid-transition on some stacks.
     */
    private function captiveHandoffUrl(Request $request): string
    {
        // An explicitly configured destination always wins — a shop may prefer
        // to land guests on its own site.
        if ($configured = Setting::get('portal_browse_url')) {
            return $configured;
        }

        if ($this->isAppleDevice($request)) {
            return 'http://captive.apple.com/hotspot-detect.html';
        }

        // Android's probe, and a reasonable default for anything else: it is the
        // most widely mirrored of the two and returns 204 No Content.
        return 'http://connectivitycheck.gstatic.com/generate_204';
    }

    private function isAppleDevice(Request $request): bool
    {
        return (bool) preg_match('/iPhone|iPad|iPod|Macintosh/i', (string) $request->userAgent());
    }

    /**
     * A tap-to-open-in-Safari link for Apple devices, or null elsewhere.
     *
     * iOS gives the sign-in sheet no automatic way out — no intent:, and the
     * sheet waits for the guest to tap Done. iOS 17+ registers the
     * x-safari-http(s):// schemes that apps use to hand a page to Safari; the
     * sign-in sheet may or may not honour it (unverifiable server-side), and on
     * a device that refuses, the tap does nothing and the typed address shown
     * beside it still works.
     */
    private function safariUrl(Request $request): ?string
    {
        return $this->isAppleDevice($request) ? 'x-safari-'.self::browserPortalUrl() : null;
    }

    /**
     * The portal by IP, for links that hand off to another browser. That
     * browser may resolve names through its vendor's cloud DNS (Xiaomi's,
     * Huawei's), which can't find the local .lab name.
     */
    public static function browserPortalUrl(): string
    {
        return 'http://'.config('services.portal.ip').'/portal';
    }

    /**
     * Whether this request is Android's sign-in window specifically.
     *
     * Narrower than the general captive-assistant check on purpose: the intent:
     * scheme handoff below is Android-only, and offering it to an iOS device
     * would just produce a failed navigation inside a window that was working.
     * Every Android WebView tags itself "; wv)" — see
     * portal/partials/captive-assistant.blade.php for the same detection
     * client-side, and why the old Chrome/Safari test never matched.
     */
    private function isAndroidAssistant(Request $request): bool
    {
        $ua = (string) $request->userAgent();

        // Only the "; wv)" tag. Xiaomi's window lacks it and must NOT get the
        // intent: handoff: it opens Xiaomi's own browser, whose cloud DNS
        // can't find the portal's .lab name.
        return preg_match('/Android/i', $ua) === 1 && preg_match('/;\s*wv\)/i', $ua) === 1;
    }

    /**
     * Last step of the Android handoff: ask the OS to open the status page in
     * the guest's own browser, falling back to the connectivity probe.
     *
     * A portal has no supported way to drive the browser; intent: is Android's
     * one lever and the sign-in WebView may refuse it, so success is a bonus
     * and the fallback is the normal path.
     */
    public function handoff(Request $request)
    {
        return view('portal.handoff', [
            'statusUrl' => self::browserPortalUrl(),
            'fallbackUrl' => $this->captiveHandoffUrl($request),
        ]);
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
     * RFC 8908 captive-portal API so "which session is this device on" is
     * answered in one place.
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
     * activeVoucherFor() also matches on IP, so a different phone later given
     * the same DHCP address would match too. Recovery needs the MAC the code
     * was redeemed on (or an unbound voucher).
     */
    private function boundToThisDevice(Voucher $voucher, ?string $mac): bool
    {
        if (empty($voucher->mac_address_hash)) {
            return true;
        }

        return ! empty($mac) && hash_equals($voucher->mac_address_hash, Voucher::hashMac($mac));
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
     * Whether this device is the one a redeemed voucher is bound to.
     *
     * The MAC is the binding whenever both sides of it are known — it survives
     * the DHCP lease changing under the guest, which the IP does not. The IP is
     * only a fallback for vouchers redeemed when the ARP lookup came back empty
     * (see resolveTrustedIdentity), since those have no MAC to compare against
     * and refusing them outright would strand a paying guest.
     */
    private function voucherBelongsTo(Voucher $voucher, string $ip, ?string $mac): bool
    {
        if (! empty($mac) && ! empty($voucher->mac_address_hash)) {
            return hash_equals($voucher->mac_address_hash, Voucher::hashMac($mac));
        }

        return $voucher->ip_address === $ip;
    }

    /**
     * RFC 8908 Captive Portal API: iOS 14+ and Android 11+ can show the
     * remaining time natively in Wi-Fi settings, after the sign-in window has
     * closed. Clients find it via DHCP option 114 — which this Kea build can't
     * send (docs/INFRASTRUCTURE.md), so it is ready for when it can.
     *
     * Unauthenticated and read-only by design: it is reachable pre-auth, so it
     * trusts nothing but the client's own network identity.
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
    public function index(Request $request, OpnSenseService $opnsense, TrafficShapingService $shaping)
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

        // Recover a session the guest still has time on — never activated (the
        // sign-in window died first) or dropped by the network layer. Re-typing
        // a paid code reads as "the portal is broken". The safety bar is time
        // remaining and not being banned, not activation history: a stale
        // voucher fails secondsRemainingOn(), and matching is on the MAC blind
        // index, so someone reusing the guest's IP can't pass.
        //
        // Exception: the guest's own Disconnect (disconnected_at) — disconnect()
        // redirects here, so recovering it would put them straight back online.
        // Re-entering the code clears it (grantAccess()).
        if (! $activeSession) {
            $pending = $this->activeVoucherFor($ip, $mac);

            if ($pending && ! $pending->disconnected_at && $this->boundToThisDevice($pending, $mac)
                && $this->secondsRemainingOn($pending) && ! $this->isMacBanned($mac)) {
                Log::info("Portal: recovering voucher {$pending->code} for {$ip} (activated: ".($pending->activated_at ? 'yes' : 'no').').');

                if ($this->grantAccess($pending, $ip, $opnsense, $shaping)) {
                    $activeSession = $this->liveSessionFor($ip, $mac, $opnsense);
                }
            }
        }

        if ($activeSession) {
            // VERIFY IF VOUCHER IS STILL VALID
            $voucher = $this->activeVoucherFor($ip, $mac);

            // A trusted device's session comes from OPNsense's allow-list, not
            // a voucher; an old code found for its IP or MAC must not end it.
            $voucherSession = ($activeSession['authenticated_via'] ?? 'API') === 'API';

            if ($voucher && $voucherSession) {
                $expirationTime = $voucher->used_at->addMinutes($voucher->duration_minutes);
                if (now()->greaterThan($expirationTime)) {
                    // DISCONNECT EXPIRED SESSION — never a protected IP in
                    // practice (infrastructure has no voucher), but this is
                    // guest-facing, unauthenticated input, so check anyway.
                    if ($opnsense->isProtectedIp($ip)) {
                        Log::warning("Portal: refusing to disconnect protected IP {$ip} despite an expired voucher match.");
                    } else {
                        $opnsense->disconnectDevice($activeSession['sessionId']);
                    }

                    return redirect()->route('portal.index')->with('error', __('Your session has expired. Please enter a new voucher.'));
                }

                return view('portal.status', [
                    'safariUrl' => $this->safariUrl($request),
                    'session' => $activeSession,
                    'startTime' => Carbon::createFromTimestamp($activeSession['startTime']),
                    'expirationTime' => $expirationTime,
                    // Not $activeSession['userName']: that's the firewall's internal login name.
                    'userName' => $this->deviceNameFor($ip, $mac, $opnsense) ?? $voucher->code,
                    'voucherCode' => $voucher->code,
                    'secondsLeft' => (int) $this->secondsRemainingOn($voucher),
                    'tier' => $voucher->tier,
                    'tierMbps' => (float) Setting::get("bw_{$voucher->tier}_down", $voucher->tier === 'premium' ? '10' : '2'),
                    'premiumMbps' => (float) Setting::get('bw_premium_down', '10'),
                    'quickReplies' => app(PortalQuickReplies::class)->all(),
                    'browseUrl' => $this->browseUrl(),
                ]);
            }
        }

        $timeUp = $this->recentlyEndedVoucher($ip, $mac);
        PortalEvent::record($timeUp ? PortalEvent::TIME_UP : PortalEvent::VISIT, $ip, $timeUp?->code);

        // Drives the "where is my code" wording — see portal/index.blade.php.
        return view('portal.index', [
            'timeUp' => $timeUp,
            'quickReplies' => app(PortalQuickReplies::class)->all(),
            'receiptPrintingEnabled' => Setting::receiptPrintingEnabled(),
            'safariUrl' => $this->safariUrl($request),
            'signInDown' => $this->signInIsDown(),
            // The voucher slip's QR carries its code (?code=), so scanning fills it in.
            'prefillCode' => strtoupper(substr(preg_replace('/[^A-Za-z0-9-]/', '', (string) $request->query('code')), 0, 12)),
        ]);
    }

    /**
     * "Need more time?" on the status page: tells staff and admins, who sell
     * the next voucher at the counter (the shop is cash-only).
     */
    public function requestMoreTime(Request $request, OpnSenseService $opnsense)
    {
        [$ip, $mac] = $this->resolveTrustedIdentity($request, $opnsense);
        $voucher = $this->activeVoucherFor($ip, $mac);
        $left = $voucher ? intdiv((int) $this->secondsRemainingOn($voucher), 60) : 0;
        $device = $this->deviceNameFor($ip, $mac, $opnsense) ?? $ip;

        PortalEvent::record(PortalEvent::MORE_TIME, $ip, $voucher?->code);

        $recipients = User::whereIn('role', ['staff', 'admin', 'super_admin'])->get();
        Notification::send($recipients, new SystemAlert(
            'A guest wants more Wi-Fi time',
            "{$device} ".($voucher ? "(code {$voucher->code}, {$left} min left)" : '')." asked for more time. They'll come to the counter.",
            'timer',
            // Opens Active Sessions on this guest, where +30 min / +1 hr are one tap.
            route('network.sessions', array_filter(['find' => $voucher?->code ?? $ip])),
        ));

        return redirect()->route('portal.index')->with('message', __('Staff have been told. Please pay at the counter for more time.'));
    }

    /**
     * This device's code ran out within the last 12 hours and it has no newer
     * time — the "Your time is up" screen. Without it, a guest cut off by the
     * firewall lands on a plain "Connect" page and assumes the code broke.
     */
    private function recentlyEndedVoucher(string $ip, ?string $mac): ?Voucher
    {
        $voucher = $this->activeVoucherFor($ip, $mac);

        if (! $voucher || ! $voucher->used_at || ! $this->boundToThisDevice($voucher, $mac) || $this->secondsRemainingOn($voucher)) {
            return null;
        }

        $endedAt = $voucher->used_at->copy()->addMinutes($voucher->duration_minutes);

        return $endedAt->gt(now()->subHours(12)) ? $voucher->setAttribute('ended_at', $endedAt) : null;
    }

    /**
     * The minute-by-minute health check can't reach the firewall, so a code
     * typed now would fail. Only a recent result counts.
     */
    private function signInIsDown(): bool
    {
        $latest = app(NetworkHealthService::class)->latest();
        $checkedAt = isset($latest['checked_at']) ? Carbon::parse($latest['checked_at']) : null;

        return $checkedAt && $checkedAt->gt(now()->subMinutes(5))
            && ($latest['checks']['firewall']['status'] ?? null) === 'fail';
    }

    /** The phone's own name from its DHCP lease, or its maker, for guest-facing labels. */
    private function deviceNameFor(string $ip, ?string $mac, OpnSenseService $opnsense): ?string
    {
        $lease = collect($opnsense->getDhcpLeases())->first(fn ($l) => ($l['address'] ?? null) === $ip);
        $name = trim((string) ($lease['hostname'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        return ($lease['mac_info'] ?? '') ? __(':maker phone', ['maker' => $lease['mac_info']]) : null;
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
                // Mark it first so the redirect below can't race index()'s
                // recovery path into re-authorizing the device.
                $this->activeVoucherFor($ip, $mac)?->update(['disconnected_at' => now()]);

                $opnsense->disconnectDevice($sessionId);
                $shaping->releaseIp($ip, $opnsense);
            } else {
                Log::warning("Portal disconnect: rejected attempt by {$ip} to disconnect session {$sessionId} it does not own.");
            }
        }

        return redirect()->route('portal.index')->with('message', __("You're logged out. Your code keeps its remaining time."));
    }

    // The self-service GCash tab was removed from the portal UI (system is
    // cash-only now) — reject direct POSTs here too, not just hide the
    // button, so the flow is actually closed off rather than just hidden.
    public function verifyPayment(Request $request)
    {
        $message = 'This location only accepts cash. Please pay at the counter.';

        return $request->wantsJson()
            ? response()->json(['success' => false, 'message' => $message], 422)
            : redirect()->route('portal.index')->with('error', $message);
    }

    // Handle standard passcode entry
    public function authenticate(Request $request, OpnSenseService $opnsense, TrafficShapingService $shaping)
    {
        $request->validate([
            'passcode' => 'required|string',
        ]);

        return DB::transaction(function () use ($request, $opnsense) {
            // 1. Verify the Lawa't Voucher in your Database.
            // Codes are issued uppercase (VoucherService), the field renders
            // uppercase, and a phone keyboard will happily add a trailing space
            // after autocorrect — so collapse whitespace and case before looking
            // up rather than blaming the guest for their keyboard.
            $code = strtoupper(preg_replace('/\s+/', '', (string) $request->passcode));
            PortalEvent::record(PortalEvent::CODE_TRIED, $request->ip(), substr($code, 0, 32));
            $failed = function (string $reason, string $message) use ($request, $code) {
                PortalEvent::record(PortalEvent::CODE_FAILED, $request->ip(), substr($code, 0, 32), ['reason' => $reason]);

                return redirect()->route('portal.index')->with('error', $message);
            };

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
            // route('portal.index'), never back(). A phone's sign-in window sends
            // no Referer, so back() falls through to '/' — which redirects to the
            // staff login page. The error flash was being consumed there and
            // never shown, so a guest who mistyped their code just saw the form
            // reset with no explanation at all.
            if (! $voucher) {
                return $failed('no_match', __('That code doesn\'t match any voucher — double-check it against your receipt.'));
            }

            if (! $voucher->is_used && ($expiredOn = $voucher->unusedExpiresAt()) && $expiredOn->isPast()) {
                return $failed('expired_unused', __('This code expired on :date. Please ask for a new one at the counter.', ['date' => $expiredOn->format('M j, Y')]));
            }

            [$ip, $mac] = $this->resolveTrustedIdentity($request, $opnsense);

            if ($this->isMacBanned($mac)) {
                Log::warning("Portal authenticate: rejected banned device {$mac} ({$ip}).");

                return $failed('banned', __('This device has been blocked from network access. Please see staff for assistance.'));
            }

            // A redeemed voucher is not automatically a spent one. The guest
            // paid for a span of time, not for a single connection, and the
            // browser tab holding their session is trivially lost — the phone
            // sleeps, the captive window is closed, they switch to mobile data
            // and back. Refusing the code outright in those cases charged them
            // twice for time they already owned.
            //
            // Re-entry is what the MAC binding is FOR: the voucher is tied to
            // the device that redeemed it, so honouring the code again is safe
            // precisely because a different device cannot use it.
            if ($voucher->is_used) {
                $secondsRemaining = $this->secondsRemainingOn($voucher);

                if (! $secondsRemaining) {
                    return $failed('used_up', __('This code has already been used and its time has run out.'));
                }

                // Redeemed against no device at all — nothing to match on, so
                // there is no honest way to hand it to whoever is asking. Kept
                // separate from the "another device" branch below because
                // claiming a specific rival device exists would be a guess.
                if (empty($voucher->mac_address_hash) && empty($voucher->ip_address)) {
                    return $failed('used', __('This code has already been used.'));
                }

                if (! $this->voucherBelongsTo($voucher, $ip, $mac)) {
                    Log::warning("Portal authenticate: {$mac} ({$ip}) tried to reuse voucher {$voucher->code} bound to another device.");

                    return $failed('other_device', __('This code is already in use on another device.'));
                }

                // Same device, time still on the clock. Re-point the voucher at
                // the address it is on now — DHCP may well have moved it since
                // the first redemption, and every downstream check (the RFC 8908
                // API, EnforceSessionLimits, the sessions page) matches on the
                // recorded IP.
                $voucher->update(['ip_address' => $ip, 'mac_address' => $mac ?: $voucher->mac_address]);

                return redirect()->route('portal.success');
            }

            // Claim the voucher for this device, but do NOT open the firewall
            // yet — that is what activate() does.
            //
            // Authorizing here is what made the portal appear to close itself
            // the instant a guest typed a valid code: the phone's captive
            // assistant probes for connectivity constantly, and the moment that
            // probe succeeds the OS destroys the window. The success page, which
            // is the only place we tell the guest how to watch their remaining
            // time, was racing that teardown and losing. With no internet yet,
            // the assistant stays open and the guest reads the page in their own
            // time before tapping through.
            $voucher->update([
                'is_used' => true,
                'used_at' => now(),
                'ip_address' => $ip,
                'mac_address' => $mac,
            ]);

            return redirect()->route('portal.success');
        });
    }

    /**
     * Open the firewall for a device that has already redeemed a voucher.
     *
     * Split out of authenticate() so the guest, not the OS, decides when their
     * sign-in window goes away. Safe to call more than once: a voucher that is
     * already activated with a live session short-circuits, so a double tap or
     * a retry can't double-charge anyone's time.
     */
    public function activate(Request $request, OpnSenseService $opnsense, TrafficShapingService $shaping)
    {
        [$ip, $mac] = $this->resolveTrustedIdentity($request, $opnsense);

        if ($this->isMacBanned($mac)) {
            Log::warning("Portal activate: rejected banned device {$mac} ({$ip}).");

            return redirect()->route('portal.index')->with('error', __('This device has been blocked from network access. Please see staff for assistance.'));
        }

        $voucher = $this->activeVoucherFor($ip, $mac);

        if (! $this->secondsRemainingOn($voucher)) {
            return redirect()->route('portal.index')->with('error', __('Your session has expired. Please enter a new voucher.'));
        }

        if (! $this->grantAccess($voucher, $ip, $opnsense, $shaping)) {
            return redirect()->route('portal.success')->with('error', __("We couldn't switch on your Wi-Fi. Please try again or ask our staff."));
        }

        // Android is the one platform where the sign-in window can ask the OS
        // to hand a URL to the real browser, via the intent: scheme. It is
        // best-effort — whether the WebView honours it depends on the build —
        // so the attempt lives on a tiny page that falls back to the ordinary
        // probe redirect if nothing happens. See handoff().
        if ($this->isAndroidAssistant($request)) {
            return redirect()->route('portal.handoff');
        }

        return redirect()->away($this->captiveHandoffUrl($request));
    }

    /**
     * Authorize a redeemed voucher's device on OPNsense and apply its speed
     * tier. The single place the firewall is opened, shared by activate() and
     * index()'s recovery path so the two can't drift.
     */
    private function grantAccess(Voucher $voucher, string $ip, OpnSenseService $opnsense, TrafficShapingService $shaping): bool
    {
        if (! $opnsense->authorizeDevice($ip, $voucher->code)) {
            return false;
        }

        $voucher->update(['activated_at' => now(), 'disconnected_at' => null]);
        $shaping->assignTier($voucher, $ip, $opnsense);
        PortalEvent::record(PortalEvent::CONNECTED, $ip, $voucher->code);

        return true;
    }

    // Handle e-wallet receipt uploads
    // Same reasoning as verifyPayment() — the receipt-OCR-to-reference-number
    // flow it fed only existed to support the now-removed GCash tab.
    public function uploadReceipt(Request $request)
    {
        $message = 'This location only accepts cash. Please pay at the counter.';

        return $request->wantsJson()
            ? response()->json(['success' => false, 'message' => $message], 422)
            : redirect()->route('portal.index')->with('error', $message);
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

        // Worked examples are retrieved per message rather than baked into the
        // system prompt, because which past answer is relevant depends entirely
        // on what was just asked — see LessonLibrary::exemplarsFor(). Appended
        // to the system turn so it keeps the same trust level as the rest of the
        // approved guidance, rather than arriving as user-role text.
        $messages = [['role' => 'system', 'content' => $ai->buildGuestSystemPrompt().app(LessonLibrary::class)->exemplarBlockFor('guest', $request->message)]];
        foreach ($conversations->slidingWindow($request->history ?? [], 20) as $msg) {
            if (empty($msg['content'])) {
                continue;
            }
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $request->message];

        [$ip, $mac] = $this->resolveTrustedIdentity($request, $opnsense);
        $context = ['ip' => $ip, 'mac' => $mac];

        // The shared agent-chat widget always reads an SSE stream, so this
        // must stream too — a plain JSON body is never matched by its
        // `data: ...\n\n` parser and the reply silently never arrives. No
        // conversation history: guests have no account to key it off.
        return $responder->stream(
            $messages,
            ToolRegistry::AUDIENCE_GUEST,
            null,
            $context,
            $request->message,
            "I'm serving other guests right now — the Menu and Connect tabs have what you need.",
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
     * The page a guest lands on straight after redeeming a code.
     *
     * This is the one moment the portal has the guest's full attention with
     * the sign-in window still guaranteed to be alive — the firewall has not
     * been opened yet, so the OS has no reason to tear it down. It therefore
     * has to carry everything they need: how long they bought, and where to
     * come back to watch it tick down.
     */
    public function success(Request $request, OpnSenseService $opnsense)
    {
        [$ip, $mac] = $this->resolveTrustedIdentity($request, $opnsense);

        $voucher = $this->activeVoucherFor($ip, $mac);
        $secondsRemaining = $this->secondsRemainingOn($voucher);

        // Reached without a redeemed voucher (a stale bookmark, a back button
        // after expiry) — there is nothing to activate, so send them to the
        // code entry form rather than showing a success page that lies.
        if (! $secondsRemaining) {
            return redirect()->route('portal.index');
        }

        return view('portal.success', [
            // Plain HTTP on purpose: this link exists to make the phone's
            // captive-network assistant notice working internet and dismiss
            // itself. An HTTPS target defeats that (the assistant can't complete
            // the handshake pre-validation on some stacks), and an HSTS-preloaded
            // host would silently upgrade and do the same. Configurable so the
            // shop isn't permanently tied to a third-party domain.
            'browseUrl' => $this->browseUrl(),
            // Scannable route back to this page. The sign-in window is destroyed
            // by the OS the moment the device goes online and cannot hand a URL
            // to the real browser, and this Kea build cannot advertise DHCP
            // option 114 for the native remaining-time display (see
            // docs/INFRASTRUCTURE.md), so a code the guest can scan — from their
            // voucher slip, or from a companion device — is the only route left
            // that involves no typing.
            'portalQr' => app(QrCodeService::class)->svg(route('portal.index'), 132),
            'durationMinutes' => $voucher->duration_minutes,
            'expiresAt' => $voucher->used_at->copy()->addMinutes($voucher->duration_minutes),
            'alreadyActive' => $voucher->activated_at !== null,
            'safariUrl' => $this->safariUrl($request),
        ]);
    }
}
