# Captive Portal

This is the guest-facing half of the network domain — see
`docs/INFRASTRUCTURE.md` for the actual network topology (OPNsense routing
guest devices here) and `docs/ARCHITECTURE.md` for how it connects to the
POS/AI domains.

## Identity: no guest account, ever

A guest is never a `users` row — they're identified purely by the IP/MAC
OPNsense reports for their device, resolved via
`CaptivePortalController::resolveTrustedIdentity()` against the live ARP
table / Kea DHCP leases (`OpnSenseService`), not a Laravel auth session.
Every guest-facing endpoint (`routes/web.php`'s `portal.*` group) is
CSRF-exempt by design — guests land here via a cross-origin redirect from
OPNsense's captive-portal zone, so there's no session/CSRF token to have —
and rate-limited instead (`throttle:voucher-auth`, `throttle:portal-chat`,
etc., configured in `AppServiceProvider::boot()`) as the only thing standing
between these endpoints and automated abuse.

## The redeem → status → disconnect flow

1. **`GET /` (`CaptivePortalController::index()`)** — OPNsense redirects a
   newly-connected device here with `clientIp`/`clientMac`/`zone` query
   params (stashed into session). The controller checks whether this
   IP/MAC already has a live OPNsense session *and* a still-valid voucher
   redemption behind it:
   - No active session (or an expired one — auto-disconnected right here) →
     shows the passcode entry form (`portal.index` view).
   - Active session + still-valid voucher → skips straight to the status
     page, no re-entry needed.
2. **`POST /portal/authenticate`** — validates the voucher code
   (`is_used = false`), rejects a banned MAC outright, then calls
   `OpnSenseService::authorizeDevice($ip, $code)` — a real API call against
   the live firewall. Only on a successful firewall authorization does the
   voucher get marked `is_used = true` (with `ip_address`/`mac_address`
   recorded) and `TrafficShapingService::assignTier()` apply the voucher's
   bandwidth tier (`free`/`premium`) via an OPNsense alias. This whole
   sequence runs inside a DB transaction with the voucher row
   `lockForUpdate()`'d, so two simultaneous submissions of the same code
   can't both succeed.
3. **Status page (`portal.status` view)** — shows a live countdown to
   `used_at + duration_minutes`. A 60-second `<meta refresh>` re-syncs
   against the server and is what actually catches real expiry (redirecting
   back to the passcode screen); the JS countdown between refreshes is
   cosmetic only, not authoritative.
4. **`POST /portal/disconnect`** — before calling
   `OpnSenseService::disconnectDevice()`, verifies the requesting IP/MAC
   actually **owns** the session ID it's asking to disconnect (cross-checked
   against OPNsense's live session list) — otherwise any guest could
   disconnect any other guest's session by guessing/incrementing a session
   ID. `TrafficShapingService::releaseIp()` cleans up the bandwidth-tier
   alias afterward.

There is **no separate "top-up" endpoint** that extends an already-active
session — a guest whose time runs out gets redirected back to the plain
passcode form and redeems a fresh code like anyone else. (The portal's
embedded AI chat mentions "extending your session" conversationally in its
greeting, but the guest AI tool audience — `docs/AI_AGENT.md` — has no tool
that can authorize a device; that's not currently a real mechanism, just
chat copy.)

## Guest AI chat

The status page embeds the same Barista AI chat component used elsewhere,
scoped to the `guest` tool audience (`lookupVoucher`, `checkMySession` only
— see `docs/AI_AGENT.md`). Guest chat is deliberately **not** persisted to
`ai_conversations` — ephemeral and session-scoped by design, unlike
staff/admin chat history.

## Admin-side network management

`VoucherController` (admin/staff) covers everything the guest flow doesn't:
batch-generating vouchers, changing a live session's bandwidth tier
(`setTier`), the sessions dashboard (`sessions()` — merges OPNsense's ARP
table, Kea DHCP leases, and the app's own session/allow-list knowledge, plus
`GhostDeviceDetectionService` for devices on the LAN the portal never
logged at all), and force-disconnecting a device (`kick()`, no ownership
check needed since only staff+ can reach it). `BlocklistController` handles
banning/unbanning a MAC outright — `BlocklistService::blockAndKick()` unifies
what used to be two disconnected flows (DB ban only, live disconnect only)
into one.
