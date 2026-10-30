# Operations

Installing, deploying and looking after the live system. For the network it
sits in, see [INFRASTRUCTURE.md](INFRASTRUCTURE.md); for every setting, see
[CONFIGURATION.md](CONFIGURATION.md).

- [The live server](#the-live-server)
- [Ground rules](#ground-rules)
- [Installing from scratch](#installing-from-scratch)
- [Deploying an update](#deploying-an-update)
- [Background jobs](#background-jobs)
- [Backups](#backups)
- [Monitoring](#monitoring)
- [Working on the live network safely](#working-on-the-live-network-safely)
- [Troubleshooting](#troubleshooting)

---

## The live server

| Part | Value |
|---|---|
| Host | Ubuntu container on Proxmox, `192.168.2.100` |
| App path | `/var/www/lawatcafe`, owned by `www-data` |
| Web server | nginx 1.24, one server block for `lawatkape.lab` and `wifi.lawatkape.lab` on port 80, root `public/` |
| PHP | PHP 8.2 FPM, pool `www` as `www-data`, `pm = dynamic`, `max_children = 20` |
| Database | MariaDB 10.11, database `lawat_db` on `127.0.0.1` |
| Staff access | `https://lawatkape.lab` through Nginx Proxy Manager (`192.168.2.5`) |
| Guest access | `http://wifi.lawatkape.lab` straight to this server, plain HTTP (see [CAPTIVE_PORTAL.md](CAPTIVE_PORTAL.md)) |
| Scheduler | `/etc/cron.d/laravel-lawatcafe-schedule`, every minute as `www-data` |

nginx sends the built assets (`/build/…`, content-hashed file names) with a
one-year `immutable` cache header and images and fonts with one week. Without
those headers every page re-downloaded about 570 KB.

The FPM pool is `dynamic` with up to 20 workers. At the old setting of 5 the
guest portal stalled whenever a few guests and the every-minute jobs overlapped.

## Ground rules

1. **Run Artisan as `www-data`, never as root:**
   `sudo -u www-data php artisan …`. A root-run command once wrote cache files
   that PHP-FPM couldn't overwrite, and the app answered every request with a
   500 error.
2. **Migrations are additive only.** Never `migrate:fresh`, `migrate:reset`,
   `db:wipe`, `DROP` or `TRUNCATE` on this database. It holds real sales,
   shifts and vouchers.
3. **`npm` runs as root here**, so fix ownership after every build:
   `chown -R www-data:www-data public/build`.
4. **Clear the config cache before running tests.** A cached production
   config makes the tests point at the real database; the suite detects this
   and refuses to start.

## Installing from scratch

On Ubuntu 24.04 (or similar), as root:

```bash
apt install nginx mariadb-server php8.2-fpm php8.2-{mysql,mbstring,xml,curl,zip,bcmath,intl,gd} \
            composer nodejs npm dnsutils iputils-ping

git clone https://github.com/Ashong1/lawatcafe.git /var/www/lawatcafe
cd /var/www/lawatcafe
chown -R www-data:www-data .

sudo -u www-data composer install --no-dev --optimize-autoloader
npm ci && npm run build && chown -R www-data:www-data public/build

sudo -u www-data cp .env.example .env
sudo -u www-data php artisan key:generate
# edit .env: APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, OPNSENSE_*, PIHOLE_*, OPENROUTER_API_KEY, mail
```

`dnsutils` (`dig`) and `ping` are used by the network health checks.

Create the database and user in MariaDB, then:

```bash
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --force     # prints the accounts it creates
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

**nginx**: one server block with `root /var/www/lawatcafe/public`,
`try_files $uri $uri/ /index.php?$query_string`, PHP to
`unix:/var/run/php/php8.2-fpm.sock`, both host names on port 80, and the
cache headers above. Staff HTTPS is handled by Nginx Proxy Manager in front.

**Scheduler** (`/etc/cron.d/laravel-lawatcafe-schedule`):

```
* * * * * www-data cd /var/www/lawatcafe && php artisan schedule:run >> /dev/null 2>&1
```

**Network side**: OPNsense API key and captive portal zone, the aliases and
shaper setup (`php artisan shaper:provision` reports what is missing), and a
Pi-hole app password. See [INFRASTRUCTURE.md](INFRASTRUCTURE.md) and
[OWNER_NETWORK_STEPS.md](OWNER_NETWORK_STEPS.md).

## Deploying an update

```bash
cd /var/www/lawatcafe
sudo -u www-data git pull
sudo -u www-data composer install --no-dev --optimize-autoloader
npm ci && npm run build && chown -R www-data:www-data public/build   # if front-end files changed
sudo -u www-data php artisan migrate --force                         # additive migrations only
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:clear
```

Check the version in the sidebar footer matches `composer.json`, then open
Wi-Fi & Network → Network Status.

To go back: `git checkout <previous tag>` and repeat the steps. Migrations
are additive, so older code runs fine against a newer schema.

## Background jobs

All of them run from the scheduler (`routes/console.php`); the table is in
the [README](../README.md#background-jobs). Points worth knowing:

- **There is no long-running queue worker.** `queue:work --stop-when-empty`
  runs every minute and exits. Queued mail and AI background work therefore
  wait up to a minute.
- **Failed jobs** go to `failed_jobs`. Mail jobs keep retrying for 3 days
  before landing there. Inspect them with `sudo -u www-data php artisan
  queue:failed`.
- **Job health**: jobs with nothing else to show for a normal run write a
  heartbeat, and super admins can ask Barista AI "are the scheduled jobs
  running?" (`getScheduledJobHealth`).
- Network jobs (`enforce-sessions`, `keepalive-guests`, `health`) talk to the
  live firewall every minute. `withoutOverlapping()` stops a slow firewall
  from piling runs up.

## Backups

Back up these things:

1. **The database.** Done automatically every hour by
   `backup:database`, which writes a gzipped dump to `storage/app/backups/`
   and keeps the last 168 (one week). Each run, success or failure, appears on
   **Settings → Security**, which lists them with **Make a backup now**, **Download**
   and **Restore**. From the command line: `sudo -u www-data php artisan backup:database`. These
   copies live on the same server, so copy the newest one somewhere else
   (USB drive, another machine) every week.
2. **`.env`, especially `APP_KEY`.** MAC addresses and AI audit records
   are encrypted with it, and a restored database is unreadable without the
   same key.
3. **`storage/app`**, for uploaded files.
4. **`/opt/lawatkape-android/`**, the Android app's signing key. Without it,
   an app update can't be installed over the existing app on the phones (see
   [ANDROID_APP.md](ANDROID_APP.md#the-signing-key)).

The firewall, Pi-hole and Nginx Proxy Manager have their own backups (the
OPNsense config export, Pi-hole Teleporter, Proxmox snapshots). The app
doesn't hold their configuration.

## Restart and shut down

The **power icon** in the top bar (owner and system admin; password each
time; logged in the Activity Log):

| Button | What happens | How it comes back |
|---|---|---|
| Restart web app | Caches rebuilt, queue restarted, PHP-FPM reloaded | By itself in seconds |
| Shut down web app | `artisan down`: staff see `errors/503`; the guest portal, network jobs, queue and backups keep running | **Turn back on** banner for whoever shut it down, or `sudo -u www-data php artisan up` |
| Restart server | `systemctl reboot` | By itself in about a minute |
| Shut down server | `systemctl poweroff` (also needs SHUT DOWN typed) | Only from Proxmox |

The server buttons (and the PHP reload) need this sudoers line, installed
once by root; until then the power menu marks them as needing setup:

```
www-data ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.2-fpm, /usr/bin/systemctl reboot, /usr/bin/systemctl poweroff
```
in `/etc/sudoers.d/lawatkape-power` (mode 440; check with `visudo -c`).

## Restoring a backup

**Settings → Security → Restore** (owner password required). It first makes a
backup of the current data, so a restore can be undone, then loads the
chosen one, runs migrations and clears the cache. The restore is logged.
Do it when the shop is closed.

By hand, if the app itself won't start:

```bash
gunzip -c storage/app/backups/lawat_db_<date>.sql.gz | mysql lawat_db
```

Restore onto a server with the **same `APP_KEY`** in `.env`, or encrypted
columns cannot be read.

## Incident response

What to do when something goes wrong. Note what you did and when, and keep
the note with the Activity Log entries it refers to.

**Who:** the **Data Protection Officer is the owner of Lawa't Kape**
(named in the guest privacy notice, reached at the counter). The developer
is the technical contact: the owner asks them to look up, export or delete a
guest's records and to investigate incidents. A personal-data breach that
puts people at real risk must be reported to the National Privacy
Commission and to the affected people within 72 hours of knowing about it.

| Situation | How you notice | First steps |
|---|---|---|
| Someone may know a staff or owner password | Activity Log shows a sign-in at an odd time, or from an unknown device | Settings → Staff Accounts → remove the account (signs them out at once), then invite them again so they set a new password. Check the Activity Log and Orders for what that account did. |
| Someone is guessing passwords | Activity Log: many "Wrong username or password" entries, or "Locked out" | Lockout already stops them after 5 tries. Note the device address and block it on Wi-Fi & Network → Find a device if it is on the shop network. |
| A guest device is abusing the Wi-Fi | Network Status alerts, Who's Online, adult-site alerts | Block the device (Wi-Fi & Network → Who's Online → Block). Blocks are logged with a reason. |
| Data lost or damaged | Missing sales or stock, errors on pages | Stop using the register, take a fresh `backup:database` of the current state, then restore the last good backup (above). Re-enter sales made after it from the paper record. |
| Server or firewall down | Network Status, the yellow offline bar | See *Troubleshooting* below. The register keeps working offline. |
| Secret leaked (`.env`, API keys) | Found in a public place | Rotate the key at the provider (OpenRouter, Resend, OPNsense, Pi-hole), update `.env`, `config:cache`. **Do not** change `APP_KEY` without re-encrypting. |

Afterwards: write down what happened, what fixed it, and what would stop it
happening again.

## Monitoring

- **Wi-Fi & Network → Network Status** shows the minute-by-minute checks with 24-hour
  history; admins are notified when a check changes state.
- **Super admin dashboard** shows the app host, AI provider health, captive
  portal posture and infrastructure.
- **Settings → Security**: sign-ins, sign-outs, wrong-password tries,
  lockouts, account changes and backups, with time, device address and browser.
- **Logs**: `storage/logs/laravel.log` for the app,
  `/var/log/nginx/error.log` and `/var/log/php8.2-fpm.log` for the server.
  Super admins can ask Barista AI for recent errors
  (`getRecentSystemErrors`).
- **The yellow bar** on staff screens appears when the internet check fails.

## Working on the live network safely

The app controls the shop's real firewall. Lessons learned the hard way:

- **Never loop over guessed OPNsense API paths.** A discovery loop that tried
  paths ending in `stop`, `start` and `restart` stopped the live captive
  portal. Read endpoints (`get`, `search`, `status`) only, one at a time, and
  check [the OPNsense notes in INFRASTRUCTURE.md](INFRASTRUCTURE.md) first.
- **Don't test voucher redemption against the live firewall.** It really
  signs a device in. The tests mock `OpnSenseService` for this.
- **Smoke-test the register with a throwaway account** created and deleted
  in the same session, and never on a real open shift.
- **This server is on the captive portal's allow-list**, so the guest
  experience can't be reproduced from here. Use a phone on the guest Wi-Fi.

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Every page is a 500 error mentioning "Permission denied" in `storage/` | Artisan or a script ran as root | `chown -R www-data:www-data storage bootstrap/cache public/build`; run Artisan as `www-data` |
| A new page or button gives "Route not defined" | Stale route cache | `sudo -u www-data php artisan route:clear && sudo -u www-data php artisan route:cache` |
| `.env` changes have no effect | Config is cached | `sudo -u www-data php artisan config:cache` |
| Front-end change doesn't show | Assets not rebuilt, or wrong owner | `npm run build && chown -R www-data:www-data public/build`, then hard-refresh |
| Tests: "pointed at mysql, not sqlite" | Cached production config | `sudo -u www-data php artisan config:clear`, run tests, then `config:cache` again |
| Barista AI: "has used up today's free AI allowance" | OpenRouter's free daily cap (50 requests a day without credit) | Wait for the reset time it gives, or add $5 credit (raises it to 1,000 a day) |
| Barista AI: "the internet is down" | The minute check can't reach the internet | Check the ISP router; everything else keeps working |
| Emails not arriving | No internet, or Resend key wrong | `queue:failed`; check `MAIL_MAILER` and `RESEND_API_KEY`; queued mail retries for 3 days |
| Network pages: "can't reach the firewall" | OPNsense down, or API credentials wrong | Wi-Fi & Network → Network Status; check `OPNSENSE_API_URL`/`KEY`/`SECRET` |
| Guests can't get online after a power cut | OPNsense waits for the ISP before starting DHCP, or Proxmox didn't start the VM | [OWNER_NETWORK_STEPS.md §3](OWNER_NETWORK_STEPS.md#3-start-the-shop-without-internet-after-a-power-cut-15-minutes) |
| Only one guest can be online at a time | Guests sharing one sign-in identity, or `concurrentlogins` set on the zone | Fixed in 1.11 (per-guest identity); check the zone's concurrent logins is 0 |
| Guest gets "Connected" but the sign-in window stays open | The phone's assistant needs a real outside URL to probe | See [CAPTIVE_PORTAL.md](CAPTIVE_PORTAL.md) on the browser handoff |
| A trusted staff device gets the sign-in page | Trusted by MAC only and its address changed, or it uses a private (random) Wi-Fi address | Give it a fixed address, turn off private address on the phone, trust it again |
| Site blocking page: "Pi-hole not configured" | `PIHOLE_APP_PASSWORD` empty or wrong | Create a new app password in Pi-hole |
| Order reminders keep chiming | Orders aren't being marked done on the kitchen display | Mark them done; the reminder time is in Settings → Store Settings |
| Locked out of an account | | `sudo -u www-data php artisan user:reset-password someone@example.com` |

## Remote access (Settings → Security)

The **Remote access** card turns a Cloudflare Tunnel on and off: systemd
service `lawatkape-remote` (`cloudflared ... run lawatkape-remote`, config in
`/root/.cloudflared/config.yml`), reachable at `https://lawatkape-remote.aserotech.com`
(`REMOTE_ACCESS_URL`). Off by default; it stays on until someone turns it off.
Owners and super admins can switch it; every switch is in the Activity Log
and the other owners get an alert. www-data may only start/stop that one
service (enable/disable --now, so it survives a restart), via `/etc/sudoers.d/lawatkape-remote`:

    www-data ALL=(root) NOPASSWD: /usr/bin/systemctl enable --now lawatkape-remote, /usr/bin/systemctl disable --now lawatkape-remote

