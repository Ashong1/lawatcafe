# Architecture

## The thesis

This capstone's claim isn't "a POS system" or "a captive portal" — it's that
a single AI agent can genuinely operate across **both** domains at once,
because both domains live in one codebase, one database, and one
permission/audit system. A low-stock ingredient (POS/inventory domain) can
trigger a drafted purchase order (still POS domain) through the exact same
tool-call → permission-tier → confirm/audit pipeline that a repeat-MAC-abuse
signal (network domain) uses to propose blocking a device. The AI isn't a
chatbot that happens to have some tools bolted on; the tool-call
infrastructure (`docs/AI_AGENT.md`) *is* the integration layer between the
two halves of the business.

Concretely: `CrossDomainCorrelationService::run()` (the scheduled
`agent:analyze` pass) reads Wi-Fi voucher redemptions, POS revenue, banned
devices' live session status, and ingredient stock levels in the same
correlation pass — it can only make its central finding
("redemptions up while revenue is flat = possible Wi-Fi-only abuse without
purchases") *because* both domains are one system, not because it's calling
out to two separate applications.

## The three domains

1. **POS** — products, categories, ingredients, sales, shifts, suppliers,
   purchase orders. See `docs/POS_FLOW.md`.
2. **Network / captive portal** — vouchers, banned devices, static IP
   reservations, session/traffic data proxied live from OPNsense. See
   `docs/CAPTIVE_PORTAL.md` and `docs/INFRASTRUCTURE.md` for the actual
   network topology this app sits behind (OPNsense, Nginx Proxy Manager,
   Pi-hole, Proxmox).
3. **AI agent** — the tool registry/permission/audit system that lets an LLM
   act on both of the above. See `docs/AI_AGENT.md`.

## How a request is scoped

Three role tiers (`users.role`: `staff` / `admin` / `super_admin`), enforced
by `RoleMiddleware`. An under-privileged-but-logged-in user hitting a route
above their tier is bounced to `route('dashboard')`, not a bare 403 — only a
genuinely unknown/no role gets `abort(403)`. Three separate Blade layouts
(`layouts/admin.blade.php`, `layouts/staff.blade.php`,
`layouts/guest.blade.php`) exist because each audience sees a materially
different app, not just a different navbar — this mirrors the three-tier
audience split baked into `ToolRegistry` for AI tools (see `docs/AI_AGENT.md`).

Guests (people connecting to the Wi-Fi, not logged-in staff) never touch the
`users` table at all — they're identified purely by IP/MAC, resolved through
`OpnSenseService` against the live ARP table / Kea DHCP leases, not a Laravel
session in the normal sense. This is why the guest AI-tool audience is a
hardcoded, code-only allowlist (`docs/AI_AGENT.md`) rather than anything
database-driven — there's no guest "account" to attach a permission to.

## Where business logic actually lives

Controllers stay thin; business logic lives in `app/Services/`. A few load-bearing
examples: `SaleService` (void logic, shared by both the direct-admin-void
and staff-request-then-admin-approve paths), `IngredientService` (stock
math, including packaging-unit conversion), `OpnSenseService` (every live
call to the firewall — the *only* class that talks to OPNsense's API
directly), `AIService` (provider transport only — no permission/audit
awareness, see `docs/AI_AGENT.md` for why that's split into a separate
orchestrator).

## Caching conventions

Several read-heavy queries are cached with short TTLs and a stale-value
fallback on failure rather than a hard dependency — e.g. `OpnSenseService`'s
live network calls (ARP/DHCP/session data) and `AIService`'s prompt-context
queries (today's revenue, low-stock ingredients, active voucher count — all
30s `Cache::remember`). The `dashboard_stats_today` cache key is forgotten
explicitly on the mutations that would invalidate it (a sale, a stock
change) rather than relying on the TTL alone.

## Encryption at rest

MAC addresses (`vouchers.mac_address`, `banned_devices.mac_address`,
`static_ip_assignments.mac_address`) and AI action-audit payloads are
encrypted at rest (Laravel's `'encrypted'` Eloquent cast). Because encryption
uses a random IV per row, exact-match `WHERE`/`GROUP BY` queries against an
encrypted column don't work directly — a parallel `_hash` column (a
deterministic HMAC "blind index") exists on each of those tables for that
purpose; see `App\Models\Concerns\HasHashedMacAddress` and `docs/DATABASE.md`.
