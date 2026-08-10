# Changelog

Versioning scheme: [docs/VERSIONING.md](docs/VERSIONING.md).

Minor versions for 1.0–1.8 were assigned retrospectively when the scheme was
adopted at build 122, and tagged at the commit where each line of work finished.
The build numbers in the commit subjects are original and continuous; nothing in
the history was rewritten.

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
