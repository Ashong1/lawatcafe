# Changelog

Versioning scheme: [docs/VERSIONING.md](docs/VERSIONING.md).

Minor versions for 1.0–1.8 were assigned retrospectively when the scheme was
adopted at build 122, and tagged at the commit where each line of work finished.
The build numbers in the commit subjects are original and continuous; nothing in
the history was rewritten.

---

## 1.11.0 — One-click site blocking
*builds 127–135*

- Guests get a 10-minutes-left warning: a centered dialog plus a haptic
  buzz (`navigator.vibrate`, silently skipped where unsupported) fires once
  from the status page's existing countdown timer. Deliberately best-effort
  — it only reaches a guest who still has the status page open, since a
  real OS push notification needs a secure (HTTPS) context and a registered
  subscription, and this portal is deliberately HTTP-only (see
  docs/INFRASTRUCTURE.md). Still worth having: the countdown card is small
  and easy to stop noticing once a guest is absorbed in something else.

- **The actual fix for "only one device can connect at a time."** Every
  guest voucher redemption authorized its device on OPNsense under the
  exact same hardcoded username (`config('services.opnsense.guest_user')`,
  `laravel_guest`) — so OPNsense's captive portal daemon enforced a
  single-live-session limit across the *entire shop's guest traffic*, not
  per device. The earlier `concurrentlogins` zone setting (build 124) was a
  red herring for this specific symptom: it stayed correctly saved at `0`
  throughout, and even a full service restart didn't change the one-at-a-
  time behavior, because the real constraint was never that setting at all.
  Verified live: giving two devices distinct `user` values in the same
  `session/connect` call let both hold sessions simultaneously immediately,
  no service restart needed. `OpnSenseService::authorizeDevice()` now
  derives a unique identity per voucher+IP (`guest_{code}_{ip}`) instead of
  sharing one — `session/connect` doesn't validate this against a real
  OPNsense account, so no user-management API is needed. `guest_user` is
  now unused (kept in config for backward compatibility, not read anywhere).

- `network:keepalive-guests` now writes a heartbeat
  (`keepalive_guests_last_run`), surfaced through `GetScheduledJobHealthTool`
  alongside the other scheduled jobs — it produces no other artifact on a
  normal run, so without this there was no way to tell "running fine" from
  "the cron entry silently stopped."
- Found and fixed live (no code involved) a second, deeper cause behind the
  concurrent-login problem the earlier `concurrentlogins` fix (build 124)
  didn't fully solve: OPNsense's captive portal daemon was still enforcing
  the *old* single-session limit at runtime even after `concurrentlogins`
  was saved as `0` and the zone was reconfigured — `service/reconfigure`
  wasn't enough to clear it. A full `service/restart` was required. Still
  being monitored — session behavior between two real devices remained
  inconsistent immediately after the restart, so this may not be the full
  story yet.

- New `network:keepalive-guests` command (every minute, looping internally
  for ~55s so the ~25s shortest drop observed live stays covered) pings
  every authenticated guest device every few seconds. Verified live: an idle
  guest device that previously lost its session in as little as ~25 seconds
  held it for 5+ minutes straight once pinged regularly. This is a
  mitigation, not a fix — the actual cause of guest sessions vanishing with
  zero application-side trigger (confirmed: no `disconnectDevice()` call
  anywhere in this codebase fires in the windows observed) is still
  unidentified, most likely the access point itself or an OPNsense-internal
  liveness/state-timeout mechanism outside this app's API visibility.
- Bandwidth caps raised: free tier 3↓/1.5↑ → 5↓/3↑ Mbit, premium 10↓/2.5↑ →
  15↓/6↑ Mbit (`bw_free_down`/`bw_free_up`/`bw_premium_down`/`bw_premium_up`
  settings, pushed live via the pipe UUIDs `shaper:provision` already
  manages). The free tier's 1.5 Mbit upload cap was a plausible contributor
  to guest reports of images failing to send and video apps reading as
  "unstable" — both symptoms consistent with a slow/interrupted upload
  rather than a hard block. `shaper:provision --apply` itself still fails
  past the pipe-update step on this OPNsense build (a pre-existing,
  documented limitation — its own error message explains why: this build's
  Shaper-table rules only accept "any" for source/destination, so they can
  never match a tier alias; real per-tier steering runs through a
  separately-configured filter rule instead), so all four pipes were pushed
  directly via `OpnSenseService::upsertShaperPipe()` + `reconfigureShaper()`
  rather than through that command.

- Guests no longer have to re-type a still-valid voucher after losing their
  Wi-Fi session for a reason outside their control (an AP/network-layer
  disconnect — see the concurrentlogins entry above; live testing showed
  the underlying network-layer cause is broader than that single fix).
  The portal's auto-recovery, previously restricted to a redemption that was
  *never* activated (so a guest's own deliberate Disconnect couldn't be
  silently undone), now recovers any voucher with time remaining, activated
  or not: the safety bar is time remaining and not being banned, not
  activation history — a stale voucher already fails the time check, and an
  impostor riding the guest's IP can't match it at all since recovery keys
  off the MAC-address blind index, not the IP. This is a deliberate reversal
  of a prior product decision (see the updated test's docblock in
  CaptivePortalActivationTest), not a bug fix in the usual sense.

- Fixed a live OPNsense misconfiguration causing real guest disconnects: the
  captive portal zone's `concurrentlogins` was `1` (while `idletimeout` and
  `hardtimeout` were both `0`, i.e. no limit) — and every guest authenticates
  through the same shared identity (`OpnSenseService::authorizeDevice()`
  posts one configured guest_user for every voucher, not a per-guest
  account), so only one guest could ever be connected to the Wi-Fi at once.
  A second device authorizing (or a stale session still holding the slot)
  would force OPNsense to boot whoever else was using it — reported as
  "the portal disconnects and then can't reconnect, even before entering a
  voucher." Fixed live by setting `concurrentlogins` to `0`; documented in
  docs/INFRASTRUCTURE.md since it's a live config fact this app doesn't
  manage in any reconcile loop, not a code change.

- A second full-codebase review (resources/views/, routes/, config/) found 5
  more real bugs, fixed here: the dashboard's AI Insights modal had a stray
  `</div>` closing its results container early, so Strategic Advice and
  Hot/Cold Items rendered unconditionally outside the loading/error guard;
  the Wi-Fi Plans "Add New Tier" button called a method (`openModalForAdd`)
  that doesn't exist — only `openAddModal` does, so the button silently did
  nothing; the admin sidebar's "Verification Logs" link 404'd, left over from
  the e-wallet feature's removal; the Products page's edit button re-queried
  a product's ingredients with `->load()` even though the controller already
  eager-loads them, issuing one redundant query per product per page load;
  and the void-transaction button's `formSubmitting` flag never flipped
  because `Element.submit()` (used by the confirm-dialog callback) doesn't
  fire a `submit` event — fixed by setting the flag directly in the callback.
  routes/ and config/ were read in full and found clean. One finding was
  investigated and reverted: a "dead" cookie-persistence mechanism in
  layouts/staff.blade.php turned out to be intentional (confirmed by
  SidebarMenuStateTest and its docblock — staff has no visual dropdowns yet
  but the sticky-state plumbing is deliberately kept in parity with admin's).

- A high-effort code review of the new site-blocking feature (build 127)
  found 4 real bugs, fixed here: `upsertDomain()` fabricated an "Added via
  Lawa't Kape admin" comment onto any toggle of a domain that genuinely had
  no comment, instead of only on real creation; domains read back from
  Pi-hole weren't lowercased, so a mixed-case entry added outside this app
  could silently duplicate; `toggle()` skipped the domain-format validation
  `store()` enforced on the same underlying write path; and the "Pi-hole not
  configured" banner checked `!== null` while the service itself checks
  `empty()`, so an empty-string app password showed as configured. A 5th
  finding (an extra Pi-hole round-trip per toggle, needed to decide
  PUT-vs-POST and preserve the existing comment) was left as-is — an
  inherent cost of treating Pi-hole as the sole source of truth rather than
  duplicating state locally, and negligible on this LAN (~15ms).

- New admin page (Network > Site Blocking) backed by Pi-hole's DNS
  blacklist: a curated preset catalog (social media, streaming/gaming,
  adult content, piracy) with a toggle per site — block or unblock in one
  click, no typing — plus a custom-domain list for anything else, with the
  same toggle and a separate permanent-remove action. New `PiholeService`
  talks to Pi-hole v6's session-based API (POST /api/auth exchanges an app
  password for a short-lived sid+csrf pair — nothing like the old v5
  static-key API), re-authenticating automatically on a 401. The
  previously-stored `PIHOLE_API_KEY` was a dead v5 token; replaced with a
  fresh v6 app password (`PIHOLE_APP_PASSWORD`).

## 1.10.0 — OpenRouter-only, network-first Barista AI
*build 126*

Adviser-requested revisions for the capstone defense (BSInfoTech network
administration): the AI stack now runs on a single provider, and its
identity was rewritten to lead with network administration rather than POS.

- **One AI provider.** Gemini and Groq are removed entirely — code, config,
  admin UI, routes, and the model catalogs. Barista AI now runs on
  OpenRouter alone, but keeps the existing resilience machinery (circuit
  breaker, per-model health tracking, healthy-models-first reordering, the
  fast-path budget) collapsed onto OpenRouter's own model list, so a single
  bad model still fails over to another rather than failing the whole
  request.
- **Network administration is the lead identity.** The admin and staff
  system prompts were rewritten so Wi-Fi sessions, bandwidth tiers, device
  access, and the captive portal's security posture are the assistant's
  primary responsibility, with cafe/POS support explicitly secondary — not
  removed, just reprioritized. Each tool tier's enumeration order was
  reshuffled the same way (network tools first, POS tools after), on the
  theory that tool order shapes what the model reaches for on an ambiguous
  request. No tool moved tiers and no POS capability was removed.

## 1.9.0 — Barista AI learns from owner conversations
*builds 123–125*

- Fixed test coverage that build 124's protected-infrastructure guard left
  broken: mocks of `OpnSenseService` in `EnforceSessionLimitsTest`,
  `BlocklistServiceTest`, and `WriteToolsGlueTest` didn't know about the new
  `protectedIps()`/`ipForSession()` calls. Also added tests that actually
  exercise the guard's real behavior (a protected IP with a matching expired
  voucher, and `blockAndKick()` refusing a session that resolves to one) —
  the original commit only had a live smoke test, not suite coverage.

- A protected-infrastructure guard (`OpnSenseService::isProtectedIp()`) now
  sits in front of every device-disconnect path: session enforcement, the
  guest portal's own expired-session check, and the Barista AI's `blockDevice`
  tool. It combines OPNsense's captive-portal allow-list, a static config
  fallback, and the existing infrastructure-IP setting, so no single source
  going stale removes the protection. Closes a real gap where a protected IP
  matching a used voucher row could still fall through to the expiration
  check, and where the AI tool's model-supplied session ID had no
  infrastructure-awareness at all.
- The learning loop now mines the admin and owner (super_admin) conversations
  themselves, not just thumbs and corrections. A settled transcript is fed to
  the distiller as evidence, so the assistant can generalise a lesson from what
  the owner actually asked and how it answered — even when nobody clicked a
  rating. This closes the gap that left the loop leaning almost entirely on the
  guest portal, where the rating volume was. Mining is safe here where it would
  not be for guest chat: these are authenticated, trusted users, and every
  conclusion still passes the same human review gate before it reaches a prompt.
- Owner (super_admin) infrastructure/diagnostic conversations now distil into
  their own `super_admin` lesson bucket, kept out of the plain admin prompt. The
  owner's prompt is a superset — it keeps the cafe-management lessons and adds
  the infra ones on top — but an infrastructure conclusion can never leak into a
  shop admin's instructions.
- Conversations are drawn from once (`ai_conversations.mined_at`) and only after
  they have gone quiet, so a lesson is never distilled from half an exchange, and
  an AI-stack outage leaves the transcript unread for the next run rather than
  silently consuming it.

---

## 1.8.0 — Mobile shell, adaptive shaping, streaming fixes
*builds 113–121 · `aafb9d8`*

- Adaptive fair-use ceiling: the agent lowers the per-device cap as guests share
  the line and raises it when the shop is quiet, learning the connection speed
  and the busy hours from its own throughput samples. Bounded by owner-set
  min/max, with a deadband and cooldown so the shaper is not reloaded on noise.
- The fair-use ceiling became editable from the Traffic Shaping page; the
  per-tier voucher rate form was removed, having never been enforceable on this
  gateway.
- Admin and staff shells work on a phone: the sidebar became an off-canvas
  drawer below `lg`, and the collapse toggle actually collapses it.
- Notifications can be dismissed individually or cleared once read.
- Barista AI replies no longer truncate mid-sentence — a client abort shorter
  than the server's budget, and a reactivity bug that left the finished reply
  unpainted.
- Skeleton placeholders for content that has not arrived yet.

## 1.7.0 — Traffic shaping
*builds 103–112 · `f8233c8`*

- A 20 Mbit per-device fair-use ceiling, provisioned on the live gateway and
  masked per IP so it is a ceiling per device rather than a shared total.
- Free vs premium shaping via firewall rules, after establishing that Shaper
  rules on this build cannot match an alias.
- Tier-alias membership reconciled before any rule passes traffic on it.
- Fractional Mbps rates written in Kbit rather than rounded.

## 1.6.0 — The assistant grows up
*builds 93–102 · `c1ddd53`*

- Experiential learning loop: conversations and feedback distilled into lessons,
  gated behind approval, injected into later prompts.
- Barista AI gained read-only system tools for super_admin.
- Printed receipts withheld until the POS is BIR-registered.
- POS suggestions became the line the cashier says, pairing drinks with food.
- Scannable QR so guests reach their remaining time without typing a URL.
- Guest portal mobile layout and an Open in Browser handoff.

## 1.5.0 — Counting the right things
*builds 80–92 · `6e52990`*

- The dashboard counted network presence rather than paying customers; guest
  counts now come from one definition.
- Admin and super_admin dashboards split by what each account actually does.
- Vouchers re-enterable on the same device until they expire.
- Portal window no longer closes before guests see their session time.
- AI insights stopped blocking the first login of the hour.

## 1.4.0 — Captive portal infrastructure
*builds 68–79 · `4ae1954`*

- Guests enter at a real hostname; the portal shows the live menu and the
  native session time instead of dumping them on an external site.
- Portal pages load zero external assets — a pre-auth guest cannot reach them.
- Post-payment redirect stopped fighting the phone's sign-in assistant.
- Accessibility and voucher-entry robustness pass.
- A setting's default was being cached and becoming everyone's value.
- The allow-list could never be edited past one entry.

## 1.3.0 — Deep audit
*builds 43–67 · `ca0de8f`*

- Static analysis tooling (phpstan/larastan, pint).
- Test coverage from ~300 to 500+, across blocklist, products, wastage,
  suppliers, analytics, agent tools and admin settings.
- Encryption at rest for MAC addresses and AI audit payloads, with a blind
  index for searchability.
- Ghost device detection and stale-session reaping.
- Cash-only: the e-wallet flow removed entirely.
- Full documentation coverage.

## 1.2.0 — Alive pass
*builds 17–42 · `21f7c86`*

- Loading and pending states across shift, order history, KDS, network and
  purchase-order actions.
- Width-based progress bars converted to transforms.
- POS cart line-item transitions, tweened dashboard figures, guest chat typing
  indicator.
- Login page interactivity; portal countdown, success and menu animations.
- Infrastructure documentation (Proxmox, OPNsense, NPM, Pi-hole).

## 1.1.0 — Assistant history and staff workflows
*builds 1–16 · `9208398`*

- Barista AI conversation history for admin and staff.
- AI-generated category descriptions and icon suggestions.
- Device hostnames from Kea DHCP leases rather than ARP.
- Guest counts no longer inflated by infrastructure and allow-listed devices.
- Staff-facing delivery receiving with auto-confirm against purchase orders.
- POS void-approval queue and AI shift-shortage audit.
- Prompt-injection hardening across all three chat endpoints.

## 1.0.0 — First tagged release
*build 0 · `c1f380c`*

- Static IP assignment and captive portal allow-list.
- Voucher redemption timeout fix.
- Versioning moved into `composer.json` and the sidebar footer.

## Before 1.0.0

The initial capstone build: OPNsense integration, captive portal, POS with shift
management, inventory with packaging units, KDS, supplier and purchase-order
flows, the AI agent tool system, and the admin/staff shells. Roughly 100 commits,
untagged.
