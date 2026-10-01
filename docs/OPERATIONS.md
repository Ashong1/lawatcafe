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
Network → Health.

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

Back up three things:

1. **The database**:
   `mysqldump --single-transaction lawat_db | gzip > lawat_db_$(date +%F).sql.gz`
2. **`.env`, especially `APP_KEY`.** MAC addresses and AI audit records
   are encrypted with it, and a restored database is unreadable without the
   same key.
3. **`storage/app`**, for uploaded files.

The firewall, Pi-hole and Nginx Proxy Manager have their own backups (the
OPNsense config export, Pi-hole Teleporter, Proxmox snapshots). The app
doesn't hold their configuration.

## Monitoring

- **Network → Health** shows the minute-by-minute checks with 24-hour
  history; admins are notified when a check changes state.
- **Super admin dashboard** shows the app host, AI provider health, captive
  portal posture and infrastructure.
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
| Network pages: "can't reach the firewall" | OPNsense down, or API credentials wrong | Network → Health; check `OPNSENSE_API_URL`/`KEY`/`SECRET` |
| Guests can't get online after a power cut | OPNsense waits for the ISP before starting DHCP, or Proxmox didn't start the VM | [OWNER_NETWORK_STEPS.md §3](OWNER_NETWORK_STEPS.md#3-start-the-shop-without-internet-after-a-power-cut-15-minutes) |
| Only one guest can be online at a time | Guests sharing one sign-in identity, or `concurrentlogins` set on the zone | Fixed in 1.11 (per-guest identity); check the zone's concurrent logins is 0 |
| Guest gets "Connected" but the sign-in window stays open | The phone's assistant needs a real outside URL to probe | See [CAPTIVE_PORTAL.md](CAPTIVE_PORTAL.md) on the browser handoff |
| A trusted staff device gets the sign-in page | Trusted by MAC only and its address changed, or it uses a private (random) Wi-Fi address | Give it a fixed address, turn off private address on the phone, trust it again |
| Site blocking page: "Pi-hole not configured" | `PIHOLE_APP_PASSWORD` empty or wrong | Create a new app password in Pi-hole |
| Order reminders keep chiming | Orders aren't being marked done on the kitchen display | Mark them done; the reminder time is in Settings → Store |
| Locked out of an account | | `sudo -u www-data php artisan user:reset-password someone@example.com` |
