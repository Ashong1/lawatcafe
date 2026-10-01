# Configuration

Two kinds of configuration:

1. **`.env`**: connections and secrets, read at boot. Change it, then run
   `sudo -u www-data php artisan config:cache` in production.
2. **Settings**: values the owner changes day to day, stored as key/value
   rows in the `settings` table (`App\Models\Setting`). They take effect
   immediately. Most have a page; a few advanced ones are changed from the
   command line.

A setting that has never been saved uses the default shown here; the
default lives in the code that reads it.

- [.env reference](#env-reference)
- [Owner settings by page](#owner-settings-by-page)
- [Advanced settings (no page)](#advanced-settings-no-page)
- [Fixed values in config/services.php](#fixed-values-in-configservicesphp)
- [Changing a setting from the command line](#changing-a-setting-from-the-command-line)

---

## .env reference

`.env.example` has every entry below with a comment. Never commit `.env`;
it is git-ignored.

### Application

| Variable | Default | Notes |
|---|---|---|
| `APP_NAME` | `Lawa't Kape` | Shown in titles and emails |
| `APP_ENV` | `local` | `production` on the live server |
| `APP_KEY` | (none) | `php artisan key:generate`. **Also encrypts MAC addresses and AI audit records at rest; changing it makes those unreadable.** |
| `APP_DEBUG` | `true` | Must be `false` in production |
| `APP_URL` | `http://localhost` | The staff address, e.g. `http://lawatkape.lab` |
| `APP_TIMEZONE` | `Asia/Manila` | Sales days, shifts, reports and schedules use this |
| `APP_LOCALE` | `en` | Staff screens. The guest portal has its own English/Filipino switch |

### Database, sessions, cache, queue

| Variable | Default | Notes |
|---|---|---|
| `DB_CONNECTION` | `sqlite` | Production: `mysql` (MariaDB 10.11) |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | (none) | For MySQL/MariaDB |
| `SESSION_DRIVER` | `database` | Production runs `file` |
| `SESSION_LIFETIME` | `120` | Minutes |
| `CACHE_STORE` | `database` | Production runs `file`. Firewall and AI data is cached for seconds to minutes |
| `QUEUE_CONNECTION` | `database` | The scheduler drains it every minute; no separate worker is needed |

### Email

| Variable | Default | Notes |
|---|---|---|
| `MAIL_MAILER` | `log` | `log` writes emails to `storage/logs/laravel.log`; `resend` sends them |
| `RESEND_API_KEY` | (none) | From resend.com |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | `hello@example.com` | Sender of purchase orders and shift audits |

Purchase-order and shift-audit emails are queued and retried every 5
minutes for 3 days, so a send during an internet outage isn't lost.

### Barista AI

| Variable | Default | Notes |
|---|---|---|
| `OPENROUTER_API_KEY` | (none) | https://openrouter.ai/keys. Can also be set on Settings → AI Providers, which wins over `.env`. Without a key the AI features say they are unavailable; everything else works |

### OPNsense

| Variable | Default | Notes |
|---|---|---|
| `OPNSENSE_API_URL` | `https://192.168.2.251` | The firewall's web address from the app server |
| `OPNSENSE_IP` | `192.168.2.251` | Its LAN address, always treated as infrastructure |
| `OPNSENSE_API_KEY`, `OPNSENSE_API_SECRET` | (none) | System → Access → Users → API keys |
| `OPNSENSE_ZONE` | `0` | Captive portal zone number |
| `OPNSENSE_VERIFY_TLS` | `false` | Same-LAN firewalls usually have a self-signed certificate |
| `OPNSENSE_BLOCK_ALIAS` | `guest_blocklist` | A MAC alias that a block rule on the guest interface uses. The app manages its members, not the alias or the rule |
| `OPNSENSE_TIER_ALIAS_FREE`, `OPNSENSE_TIER_ALIAS_PREMIUM` | `lawatcafe_free_tier`, `lawatcafe_premium_tier` | Speed-plan groups; members are kept in step with who is signed in |
| `OPNSENSE_GUEST_PASS` | (none) | **Required for guest sign-in.** Sent with each guest's own sign-in identity (`guest_<code>_<ip>`); without it the app refuses to sign guests in rather than use a default |
| `OPNSENSE_GUEST_USER` | `laravel_guest` | No longer read: guests no longer share one identity, which had capped the whole shop at one online guest |

### Pi-hole

| Variable | Default | Notes |
|---|---|---|
| `PIHOLE_URL` | `http://192.168.2.4` | Pi-hole v6 |
| `PIHOLE_APP_PASSWORD` | (none) | Pi-hole → Settings → Web interface / API → app password. Needed for site blocking and adult-site alerts |

### Guest portal

| Variable | Default | Notes |
|---|---|---|
| `PORTAL_HOST` | `wifi.lawatkape.lab` | Printed on slips and shown to guests |
| `PORTAL_IP` | `192.168.2.100` | Used where a guest's phone can't resolve `.lab` names |

### Seeded accounts

| Variable | Default | Notes |
|---|---|---|
| `SEED_SUPER_ADMIN_EMAIL`, `SEED_ADMIN_EMAIL`, `SEED_STAFF_EMAIL` | `superadmin@example.com`, `admin@example.com`, `staff@example.com` | Created by `php artisan db:seed` |
| `SEED_*_PASSWORD` | (generated) | Empty means a random password, printed once by the seeder |

---

## Owner settings by page

### Wi-Fi Plans (`/network/plans`, admin)

| Key | Default | Meaning |
|---|---|---|
| `voucher_durations` | `{"20":60,"50":180,"100":1440}` | Price (₱) → minutes, sold at the register |
| `free_wifi_min_amount` | `200` | Minimum order (₱, after discount) that earns a free code. `0` turns the promo off |
| `free_wifi_duration` | `60` | Minutes on the free code |
| `wifi_ssid` | (empty) | Network name, used for the "scan to join" QR on slips |
| `voucher_unused_expiry_days` | `60` | An unused code stops working after this many days; `0` = never |

### Settings → Store (`/settings/store`, admin)

| Key | Default | Meaning |
|---|---|---|
| `store_open_time`, `store_close_time` | `08:00`, `22:00` | Opening hours, also used in guest chat answers |
| `receipt_header` | `Thank you for visiting Lawa't Kape!` | Top line of printed receipts |
| `order_wait_alert_minutes` | `1` | Remind staff when an order hasn't been marked done after this long (1–60) |
| `pos_receipt_printing_enabled` | `0` | Printed receipts. **Super admin only**; leave off until BIR-registered |

### Traffic Shaping (`/network/traffic`, admin)

| Key | Default | Meaning |
|---|---|---|
| `bw_free_down`, `bw_free_up` | `2`, `1` | Free plan Mbps. The page shows what the firewall is actually running and saves only after the firewall accepts the change |
| `bw_premium_down`, `bw_premium_up` | `10`, `5` | Premium plan Mbps |
| `bw_fair_use_mbps` | `20` | Per-device ceiling for devices not on a plan |
| `bw_fair_use_enabled` | `1` | The ceiling's on/off switch. While off, the AI and the adaptive loop leave it alone |
| `bw_adaptive_enabled` | `0` | Let the system move the ceiling with how busy the shop is |
| `bw_adaptive_min`, `bw_adaptive_max` | `5`, `20` | The limits it may move between (Mbps) |

### Settings → Network (`/settings/network`, super admin)

| Key | Default | Meaning |
|---|---|---|
| `opnsense_zone` | `0` | Captive portal zone |
| `network_infrastructure_ips` | Shop servers, router, access point | Never counted as guests, never disconnected. The OPNsense IP is always added |
| `network_ignored_ips` | the firewall and access point (`192.168.2.251,192.168.2.1`); the page pre-fills the firewall, app server, NPM and Pi-hole | Left out of guest lists, ghost-device detection and session enforcement |

Fixed addresses (DHCP reservations) and trusted devices (the captive portal
allow-list) are stored on the firewall itself, not in settings.

### Settings → AI Providers (`/settings/ai-providers`, admin; tests and model changes: super admin)

| Key | Default | Meaning |
|---|---|---|
| `openrouter_api_key` | (empty) | Overrides `OPENROUTER_API_KEY` |
| per-provider model list | the built-in list | Which models to try, in order |

### Settings → Agent (`/settings/agent`, super admin)

| Key | Default | Meaning |
|---|---|---|
| `agent_tool_permissions` | `{}` | Per-tool override of the permission tier. The most sensitive tools can't be loosened from here. See [AI_AGENT.md](AI_AGENT.md#permission-tiers) |

---

## Advanced settings (no page)

Change these from the command line (below).

| Key | Default | Meaning |
|---|---|---|
| `shift_notes` | `Welcome to your shift! …` | The notice board on the staff hub |
| `category_pairings` | `{}` | JSON map of category → categories to suggest at the register, used when there is no purchase history yet |
| `portal_browse_url` | (the portal itself) | Where a guest lands after connecting. Must be reachable before sign-in |
| `network_vip_ips` | (empty) | Addresses the adult-site watcher ignores |
| `fast_path_timeout_seconds` | `7` | Per-model wait for interactive AI chat |
| `fast_path_model_limit` | `2` | How many models interactive chat tries before giving up |
| `agent_conversation_budget_seconds` | `60` | Ceiling on one AI conversation turn, including tool calls |
| `ai_circuit_failure_threshold` | `3` | Failures before a provider is rested |
| `ai_circuit_cooldown_minutes` | `5` | How long it is rested |
| `ai_learning_auto_apply` | `0` | `1` would apply learned lessons without review. Leave at `0`: the review step guards against prompt injection |
| `correlation_divergence_threshold` | `50` | Percent gap between Wi-Fi redemptions and sales that counts as a finding |
| `correlation_repeat_mac_threshold` | `5` | Codes used by one device in the window that counts as abuse |
| `correlation_repeat_mac_window_hours` | `24` | That window |

---

## Fixed values in config/services.php

Deliberately not editable from a page:

- **`services.opnsense.protected_ips`**: addresses that can never be
  disconnected or blocked, even if every setting is wrong (Pi-hole, NPM, the
  app server, the access point, the firewall, and the administrator's laptop
  at `.99`).
- **`services.network.labels`**: readable names for infrastructure
  addresses ("Firewall (OPNsense)", "Wi-Fi access point", …), used on the
  health page, Trusted Devices and in AI answers.

---

## Changing a setting from the command line

```bash
cd /var/www/lawatcafe
sudo -u www-data php artisan tinker --execute="App\Models\Setting::set('shift_notes', 'Inventory count at 3pm today.');"
```

`Setting::set()` clears that key's cache, so the change shows at once. Read
one back with `App\Models\Setting::get('shift_notes')`.
