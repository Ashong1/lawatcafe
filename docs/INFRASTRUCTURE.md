# Infrastructure

This document describes the actual deployment topology this project runs on — the Proxmox virtualization layer, the network/firewall stack, and where each piece of the application actually lives. Everything here was verified directly from the running systems (not guessed) unless explicitly marked otherwise, so this can be treated as the accurate reference for the group.

## At a glance

```mermaid
flowchart TB
    internet["Internet / upstream"]
    opn["OPNsense — 192.168.2.251<br/>Router · Firewall · Captive Portal · Kea DHCP"]
    npm["Nginx Proxy Manager — 192.168.2.5<br/>(openresty) reverse proxy / TLS"]
    app["Web-Hosting — 192.168.2.100<br/>Laravel app: nginx + PHP 8.2-FPM + MariaDB"]
    pihole["Pi-hole — 192.168.2.4<br/>DNS sinkhole for guest Wi-Fi clients"]
    guests["Guest Wi-Fi clients<br/>(phones/laptops, randomized MACs)"]

    internet <--> opn
    opn --> npm
    npm --> app
    opn -. DHCP + DNS .-> guests
    guests -. captive portal auth .-> app
    opn -. DNS for guest zone .-> pihole
```

All four boxes above are Proxmox VE guests on the same `192.168.2.0/24` LAN — confirmed by each one's NIC having a `bc:24:11:xx:xx:xx` MAC prefix, which is Proxmox's vendor OUI for virtual NICs, and by `/etc/hosts`/`/etc/resolv.conf` on the app box carrying Proxmox-injected `# --- BEGIN PVE ---` blocks.

## Hosts

| Host | IP | Role | Confirmed via |
|---|---|---|---|
| **Web-Hosting** | 192.168.2.100 | This Laravel application (nginx + PHP-FPM + MariaDB) | This shell — see below |
| **OPNsense** | 192.168.2.251 | Router, firewall, captive portal, Kea DHCP for the LAN | `Server: OPNsense` response header; is this box's default gateway; `.env` `OPNSENSE_*` config |
| **Nginx Proxy Manager (NPM)** | 192.168.2.5 | Reverse proxy / TLS termination in front of the app | `Server: openresty` response header (NPM is openresty-based) |
| **Pi-hole** | 192.168.2.4 | DNS ad-blocking sinkhole | `/admin/` path redirects to `/admin/login`, with Pi-hole's exact security header set (`X-DNS-Prefetch-Control`, strict CSP, etc.) |
| **Proxmox host itself** | *not determined* | Hypervisor for all of the above | Inferred from ZFS `rpool/data/subvol-100-disk-0` mount and Proxmox-injected config — the host's own management IP/specs weren't visible from inside this container. Whoever has Proxmox console access should fill this in. |

**Important**: `192.168.2.100`'s own DNS (`/etc/resolv.conf`) points at public DNS (`8.8.8.8`, `1.1.1.1`), not Pi-hole — Pi-hole is only in the path for guest Wi-Fi clients (via OPNsense's DHCP/DNS options for that zone), not for the app server itself.

## The application server (Web-Hosting, .100)

- **OS**: Ubuntu 24.04 LTS, Proxmox LXC container (unprivileged), container ID 100
- **Resources**: 4 vCPU, 5 GB RAM, 20 GB disk on ZFS (`rpool`)
- **Web server**: nginx 1.24, one vhost (`/etc/nginx/sites-enabled/lawatcafe`) on port 80, proxying PHP through `php8.2-fpm`'s Unix socket
- **PHP-FPM**: `pm = dynamic`, `pm.max_children = 20` (raised from the Ubuntu default of 5 — see the PHP-FPM pool history below)
- **Database**: MariaDB 10.11, local to this box (`lawat_db`)
- **Scheduler**: `* * * * * www-data cd /var/www/lawatcafe && php artisan schedule:run` via `/etc/cron.d/laravel-lawatcafe-schedule`, driving `agent:analyze` (every 15 min) and `network:enforce-sessions` (every minute) — see `routes/console.php`
- **Mail**: outbound email goes through Resend's API (`MAIL_MAILER=resend`), not the local Postfix install — Postfix is running (`postfix@-.service`) but is the stock Ubuntu default, not actually used by the app

## OPNsense (.251)

Acts as the LAN's router/firewall/DHCP server and the captive-portal enforcement point. The app talks to it entirely over its REST API (key/secret auth, credentials in `.env`, never in git). What `app/Services/OpnSenseService.php` actually drives on it:

- **Captive portal**: authorizing a device's session (`authorizeDevice`), disconnecting a session (`disconnectDevice`), listing active sessions (`listSessions`), reconfiguring the captive portal zone (`reconfigureCaptivePortal`)
- **DHCP (Kea)**: adding/updating/deleting static reservations (`addKeaReservation`, `updateKeaReservation`, `deleteKeaReservation`), reading leases (`getDhcpLeases`) — device hostnames are read from Kea leases specifically, not ARP, because ARP's hostname field is almost always empty
- **Firewall aliases**: a MAC block alias for banned devices (`addMacToBlockAlias`/`removeMacFromBlockAlias`), per-tier IP aliases for voucher speed tiers (`addIpToTierAlias`/`removeIpFromTierAlias`), and an "allowed addresses" allow-list (infrastructure/staff devices that should never be treated as guests)
- **Traffic shaping**: per-tier dummynet-style pipes (`upsertShaperPipe`, `reconfigureShaper`), driving the voucher speed tiers
- **Monitoring**: gateway status and interface stats (`getGatewayStatus`, `getInterfaceStats`) surfaced on the admin network dashboard

## Nginx Proxy Manager (.5)

Sits in front of the app as the actual internet/LAN-facing entry point — this is what memory across the team should call "real traffic," as opposed to hitting the app box directly. It's openresty-based (confirmed by its `Server` header) and forwards to `192.168.2.100:80`. TLS termination and any public-facing domain routing happen here, not on the app box itself.

**Staff/admin only.** NPM unconditionally 301s `http://lawatkape.lab/*` to HTTPS, and that HTTPS listener serves a certificate for `CN = pve02.local` issued by the Proxmox CA — wrong hostname *and* an untrusted issuer. Staff click through it; guests cannot (see below).

## The two hostnames

| Hostname | Resolves to | Path | Who uses it |
|---|---|---|---|
| `lawatkape.lab` | 192.168.2.5 (NPM) | 301 → HTTPS, then proxied to the app | Admin and staff |
| `wifi.lawatkape.lab` | 192.168.2.100 (app box) | Plain HTTP, straight to nginx | Guests, via the captive portal |

The guest portal deliberately **bypasses NPM and stays on plain HTTP**. A captive-network assistant (the mini-browser Android/iOS pops up on joining Wi-Fi) will not let a user click through a certificate warning — it gives up and dumps them into the system browser, which is what made the auto-redirect look broken. `.lab` is a private TLD, so no public CA can ever issue a valid certificate for it; the portal cannot be fixed *with* a certificate, only by not needing one.

This works because Laravel builds its URLs from the request host, so the app self-references correctly on either hostname with no `APP_URL` change. Verified: `/captive-portal-api` returns `user-portal-url: http://wifi.lawatkape.lab/portal` when reached on that host.

Portal pages must therefore load **zero external assets** — a guest reaching them has not been let onto the internet yet, so any off-box request hangs until timeout and reads to the captive-network assistant as "still no connectivity." Fonts are self-hosted and background textures are pure CSS for exactly this reason. The one external link left on `/portal/success` is a user-clicked "browse the web" anchor, which only fires *after* the guest is authorised.

## Pi-hole (.4)

DNS sinkhole for ad-blocking, and what Kea hands LAN clients as their DNS server (`option_data.domain_name_servers = 192.168.2.4`). Confirmed the app server itself does *not* use it for its own DNS resolution — `/etc/resolv.conf` here points at public DNS.

**Pi-hole forwards `.lab` upstream to OPNsense's Unbound.** So local DNS records for this lab can be created through the OPNsense API (Unbound host overrides) and guests will resolve them, without touching Pi-hole's own UI. That is how `wifi.lawatkape.lab` is published. Pi-hole additionally holds a few of its own local records (e.g. `lawatkape.lab` → .5) that take precedence over the forward.

Note: the `PIHOLE_API_KEY` in `.env` is a stale Pi-hole v5 token. Pi-hole is now v6, whose API moved to `/api` with different auth, so that key returns `session.valid: false`. Harmless — nothing in the app calls Pi-hole — but it cannot be used to automate anything.

## Where things live

- **In this git repo**: everything under `app/`, `resources/`, `database/`, `routes/`, config templates (`.env.example`) — the actual Laravel application.
- **Outside git, on the app box**: `/etc/nginx/sites-enabled/lawatcafe`, `/etc/php/8.2/fpm/pool.d/www.conf`, the real `.env` (secrets), and the cron entry. Changes to these need to be made directly on the box (or backed up/tracked separately) — they are not deployed from this repo.
- **Outside git, on OPNsense**: firewall rules, aliases, Kea DHCP config, captive portal zone config, the `Lawat_Redirect` portal template, and Unbound host overrides — managed partly through this app's API calls, partly through OPNsense's own web UI directly.
- **Outside git, on NPM/Pi-hole**: their own web UIs — this repo has no automation touching either of them.

### Captive portal redirect chain (all outside git)

1. Guest joins the Wi-Fi; OPNsense's captive portal intercepts their first HTTP request.
2. It serves the **`Lawat_Redirect` template** (zone `f57052d4-…`, template `8df7dd38-…`), a one-page `<meta http-equiv="refresh">` to `http://wifi.lawatkape.lab/portal` plus a manual link for clients that ignore meta-refresh. Template content is stored base64-encoded **ZIP** in the API — decode, edit, re-zip, re-encode to change it (`/api/captiveportal/service/search_templates` to read, `save_template` to write, then `reconfigure`).
3. `wifi.lawatkape.lab` resolves via Unbound to 192.168.2.100, where nginx's `server_name` accepts it and serves the Laravel app over plain HTTP.

**Known gap — DHCP option 114.** RFC 8908 lets a client learn the portal URL from DHCP option 114 (`v4-captive-portal`) instead of relying on the interception above, and the app already serves the matching `/captive-portal-api` endpoint. This OPNsense build's Kea model only exposes a fixed option list (`domain_name_servers`, `routers`, `ntp_servers`, `domain_search`, …) with no way to set an arbitrary option, via API or UI. The redirect works without it; option 114 would just make it faster and more reliable on modern clients.

**Testing caveat**: 192.168.2.100 is in the captive portal's allow-list, so the guest experience can never be reproduced from the app box — it always bypasses the portal. Test from an actual phone on the guest Wi-Fi.

## Security notes

- OPNsense API key/secret, the Resend API key, and AI provider keys all live in `.env` only — never commit real values to `.env.example` or anywhere in the repo.
- `.env`'s `OPNSENSE_GUEST_USER`/`OPNSENSE_GUEST_PASS` are the credentials the app itself uses to talk to OPNsense's guest-facing API surface — rotate these if this document or `.env` is ever shared outside the group.
