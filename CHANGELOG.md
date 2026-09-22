# Changelog

Versioning scheme: [docs/VERSIONING.md](docs/VERSIONING.md).

Minor versions for 1.0–1.8 were assigned retrospectively when the scheme was
adopted at build 122, and tagged at the commit where each line of work finished.
The build numbers in the commit subjects are original and continuous; nothing in
the history was rewritten.

---

## 1.11.0 — One-click site blocking
*build 127*

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
