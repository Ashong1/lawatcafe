# Cybersecurity Plan — System Checklist

Self-assessment of Lawa't Kape POS and Captive Portal against the course's
*Final Project — System Checklist and Grading Rubric* (areas A–H). Every
"Yes" points to something that can be shown in the running system or the
code. "Partly" items say what is missing.

| Area | Yes | Partly | No |
|---|:-:|:-:|:-:|
| A. System and Assets | ✔ | | |
| B. Authentication and Access Control | ✔ | | |
| C. Session Management | | ✔ | |
| D. Input Handling and Data Protection | ✔ | | |
| E. Configuration, Components and Network | | ✔ | |
| F. Logging, Backup and Incident Response | ✔ | | |
| G. Standards and Compliance | | ✔ | |
| H. Testing and Evidence | ✔ | | |

---

## A. System and Assets — Yes

- Every server, address and service is listed in
  [INFRASTRUCTURE.md](../INFRASTRUCTURE.md): app server (Laravel on Proxmox),
  OPNsense firewall, Pi-hole DNS filter, Nginx Proxy Manager, DHCP pool.
- Data assets and where they live: [DATA_DICTIONARY.md](DATA_DICTIONARY.md),
  [ERD.md](ERD.md).
- Sensitive assets named: `.env` / `APP_KEY`, API keys (OpenRouter, Resend,
  OPNsense, Pi-hole), the Android signing key, the database
  ([OPERATIONS.md — Backups](../OPERATIONS.md#backups)).

## B. Authentication and Access Control — Yes

- Every staff page requires sign-in. Passwords are stored hashed (bcrypt);
  new passwords need at least 8 characters.
- Accounts are created by invitation only: the person sets their own
  password from an emailed, expiring link. No shared or default passwords
  (the seeded admin password was reset on the live system).
- **Login lockout:** 5 wrong tries locks the name out for a minute
  (`LoginRequest`), and every lockout is logged.
- **Roles (least privilege):** `staff` (register, kitchen, codes), `admin`
  (owner: reports, stock, Wi-Fi, accounts), `super_admin` (technical
  settings; cannot use the register). Enforced on routes by `RoleMiddleware`
  and `DenySuperAdmin`, not only by hiding buttons.
- The AI assistant's tools are permission-checked per role (32 / 26 / 15 / 2
  tools); risky actions wait for owner approval and are audited
  (`ai_action_audits`).
- Removed accounts are signed out on their next request
  (`SignOutRemovedAccounts`). Voids by staff need owner approval.

## C. Session Management — Partly

Done:
- Session ID regenerated at sign-in; session invalidated and CSRF token
  regenerated at sign-out.
- Idle sign-out after 20 minutes (`IdleSessionTimeout`); absolute lifetime 2
  hours.
- Cookies are `HttpOnly` and `SameSite=Lax`; CSRF tokens on every form.

Missing, by design: the cookie is not marked `Secure` (HTTPS-only), because
the shop phones, the Android app and the captive portal reach the server
over plain HTTP on the local network (a guest has no internet, and so no
certificate check, before signing in). Owner and staff access from outside
goes through Nginx Proxy Manager with HTTPS.

## D. Input Handling and Data Protection — Yes

- Every form and API endpoint validates its input server-side (Laravel
  validation), including prices and discounts recomputed on the server at
  checkout so a tampered cart is rejected.
- Database access goes through Eloquent / bound parameters (no SQL built
  from user input). Blade escapes output by default.
- **Encryption at rest:** guest MAC addresses and AI audit payloads are
  encrypted with `APP_KEY`; a keyed hash (blind index) keeps them
  searchable.
- **AI prompt-injection hardening:** chat history roles are validated, the
  guest assistant has only 2 read-only tools, and learned lessons need human
  approval before they reach a prompt.
- Guest portal endpoints are rate-limited (code guessing: 5 a minute and 30
  an hour per device).
- File uploads (product photos, payment QR) are type- and size-checked.

## E. Configuration, Components and Network — Partly

Done:
- `APP_ENV=production`, `APP_DEBUG=false` (no stack traces to users).
- Security headers on every response: `X-Content-Type-Options: nosniff`
  and `X-Frame-Options: SAMEORIGIN` from nginx, `Referrer-Policy` and
  `Permissions-Policy` from the app (`SecurityHeaders`).
- Network: guests are behind the OPNsense captive portal; per-plan speed
  caps; device blocking; Pi-hole website filter with an adult-site list;
  the firewall, server and infrastructure are protected from the
  disconnect/block tools (`isProtectedIp`).
- Dependencies are managed with Composer/npm lock files.

Missing:
- The app does not verify the OPNsense firewall's TLS certificate
  (`OPNSENSE_VERIFY_TLS=false`), because the firewall uses its own
  self-signed certificate on the LAN. The fix is to install that certificate
  on the server and turn verification on.
- No scheduled dependency vulnerability scan (`composer audit`,
  `npm audit`); run them before each release.

## F. Logging, Backup and Incident Response — Yes

- **Activity Log** (Settings → Activity Log, owner only): every sign-in,
  sign-out, wrong-password try (with the name typed, never the password),
  lockout, password reset, account added/changed/removed/restored and
  backup, with time, device address and browser. Entries are append-only.
- Other audit trails: AI actions (`ai_action_audits`), stock movements
  (`inventory_logs`), voids and their approvals, cash counts per shift with
  shortage alerts, guest portal events, network health history, blocked
  devices with reasons.
- **Backups:** automatic nightly database backup at 23:30, last 14 kept;
  shown on the Activity Log; restore steps in
  [OPERATIONS.md](../OPERATIONS.md#restoring-a-backup).
- **Incident response plan:**
  [OPERATIONS.md — Incident response](../OPERATIONS.md#incident-response).

## G. Standards and Compliance — Partly

- **OWASP Top 10 (2021)** coverage: A01 access control (B), A02 crypto (D),
  A03 injection (D), A05 misconfiguration (E), A07 authentication (B, C),
  A09 logging (F).
- **ISO/IEC 25010** is the quality model for the evaluation in Chapter 3.
- **Data Privacy Act of 2012 (RA 10173):** personal data is kept to the
  minimum (guests give no name, phone or email; MAC addresses are
  encrypted; portal events are deleted after 90 days).
  **Missing:** a privacy notice on the guest portal telling guests what is
  collected and why, and a named person responsible for data protection.
- WCAG-style readability rules (12px minimum text, 44px touch targets) are
  enforced by tests.

## H. Testing and Evidence — Yes

- 1,118 automated tests (PHPUnit), all passing; inventory in
  [TEST_INVENTORY.md](TEST_INVENTORY.md). Security-relevant suites include
  `AuthenticationTest`, `ActivityLogTest`, `GuestChatHardeningTest`, the
  role and void-approval tests, and the encryption-at-rest tests.
- Evidence for the defense: the Activity Log page, a backup file in
  `storage/app/backups/`, response headers (`curl -I`), and the test run
  output.
