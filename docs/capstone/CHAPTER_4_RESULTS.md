# Chapter 4 — Results and Discussion

Chapter 4 presents what was built, objective by objective, then the testing
and evaluation results. This guide gives the facts and measurements that are
already established, the screenshots to take, and the tables to fill in once
the survey is done. **Do not fill the evaluation tables with estimates.** Use
the actual survey answers.

Suggested structure:

1. The system (per specific objective, with screenshots and discussion)
2. Testing results
3. Evaluation results (ISO/IEC 25010)
4. Discussion of issues found and how they were resolved

---

## 4.1 Taking the screenshots

Take them from the live system on the shop network (`http://192.168.2.100`).
For phone screenshots, use the Android app or a phone browser. Sign in with
the right role for each page. **Never show real passwords, API keys, real
customer devices or a real GCash QR.** Blur them or use sample data.

| Figure | Page (path) | Role | What to show |
|---|---|---|---|
| Login | `/login` | — | Username-or-email sign-in |
| Owner dashboard | `/dashboard` | admin | Sales, guests online, voucher stock, network status, Barista AI brief |
| Staff dashboard | `/staff-dashboard` | staff | Open Register, Wi-Fi, kitchen queue, shift |
| Register | `/pos` | staff | Product cards with photos, cart, payment options |
| E-wallet payment | `/pos` → choose GCash | staff | QR screen with amount and reference number |
| Order placed | `/pos` after checkout | staff | Change / reference, Wi-Fi code, Kitchen Slip button |
| Kitchen slip | `/pos/kitchen-slip/{id}` | staff | Printed kitchen copy, no prices |
| Kitchen display | `/kds` | staff | Orders waiting, waiting-time reminder |
| Orders | `/pos/history` | staff | Order list, void request |
| Shift closing | Register ⋮ → End Shift | staff | Cash, e-wallet line, expected vs counted |
| End of Day | `/finance/z-reads` | admin | Shift list with short/over |
| Sales reports | `/sales` | admin | Revenue, trend, CSV download |
| Products | `/inventory/products` | admin | Product with photo, recipe tab |
| Ingredients | `/inventory/ingredients` | admin | Stock levels, low-stock |
| Purchase orders | `/inventory/purchase-orders` | admin | AI-drafted order |
| Wi-Fi codes | `/network/vouchers` | admin | Code list, Make Codes, print slips |
| Wi-Fi prices | `/network/plans` | admin | Plans and free Wi-Fi rule |
| Who's online | `/network/sessions` | admin | Guests, shop equipment, Block / Trust buttons |
| Wi-Fi speed | `/network/traffic` | admin | Plan speeds, fair-use ceiling |
| Trusted devices | `/network/trusted-devices` | admin | Device list by name/IP/MAC |
| Blocked websites | `/network/site-blocking` | admin | Preset categories toggled |
| Network status | `/network/health` | admin | Internet, firewall, DNS filter, portal, equipment |
| Sign-in report | `/network/portal-report` | admin | Portal events, drops |
| Barista AI chat | any page → chat button | admin | A question and an action it performed |
| AI approvals | `/admin/ai/actions` | admin | Waiting for your OK, Approve / Decline |
| What it noticed | `/ai/analysis-history` | admin | Cross-domain findings |
| Sales forecast | `/admin/analytics` | admin | Next 7 days |
| Staff accounts | `/accounts` | admin | Invite, waiting-to-set-password status |
| Store settings | `/settings/store` | admin | Hours, reminder, e-wallet QR setup |
| Guest portal | `/portal` (from a guest phone) | guest | Code entry, English/Filipino |
| Guest status | after sign-in | guest | Time left, menu, chat |
| Guest menu | `/portal/menu` | guest | Menu with product photos |
| Android app | shop phone | staff | App icon, register full screen |

Below each figure: one or two sentences on what the user does there and which
objective it meets.

---

## 4.2 Results per objective

Use the specific objectives from Chapter 1. For each one: *what was built*,
*figure(s)*, *how it works* (short), *evidence it works*. The facts below are
measured or verified. Add your own observations from the shop.

### Objective 1 — Register, kitchen and payments
- Register with categories, search, product photos, variants (hot/iced),
  notes, dine-in/take-away, Senior/PWD discount, cash or e-wallet.
- Prices, discount and stock are re-checked on the server at checkout; a
  tampered total or discount is refused (`PosCheckoutTest`).
- E-wallet sales need a reference number and are recorded at the exact total
  (`EwalletPaymentTest`). Kitchen slips print without prices (`KitchenSlipTest`).
- Orders waiting longer than the set time (default 1 minute) trigger a
  reminder on staff screens.
- Phone photos are shrunk on the device before upload: a 2.5 MB photo
  became about 102 KB in testing.

### Objective 2 — Inventory by recipe
- Each product has a recipe; a sale deducts each ingredient and logs it.
  Low-stock items trigger notifications, and Barista AI can draft a purchase
  order.
- Packaging units let stock be added as "2 boxes" while tracked in grams/ml.

### Objective 3 — Shifts and end of day
- Starting cash → cash in/out → closing count. Expected cash counts cash
  sales only; e-wallet money appears on its own line, so a shift isn't marked
  short for money that went to the shop's account.
- A shortage emails the owner with an AI-written summary.

### Objective 4 — Wi-Fi codes and captive portal
- Codes are sold at the register or made in batches, with a QR on printed
  slips. Free Wi-Fi is given above a set order amount.
- The guest page works in English or Filipino and loads no files from the
  internet (guests have none yet).
- One code is bound to one device. It can be re-entered until it expires,
  the timer never restarts, and the guest is warned 10 minutes before the
  end.
- Discussion point: an early version let only **one** guest online at a time
  shop-wide, because every guest shared one firewall login. Fixed in v1.11.1
  by giving each code its own identity.

### Objective 5 — Network management for non-technical users
- Who's Online shows guests and shop equipment with **Disconnect / Block /
  Trust** buttons. Shop equipment can't be blocked by mistake (protected
  infrastructure guard).
- Speed plans were verified with a speed test: **5 Mbps (free)** and
  **15 Mbps (premium)** caps applied per device.
- Website blocking by category in one tap (Pi-hole); adult-site lookups
  alert the owner.
- Trusted Devices lists devices by name, IP and MAC so the owner can pick
  which ones skip the sign-in page.

### Objective 6 — Monitoring and offline operation
- Health checks every minute (internet, firewall, DNS filter, portal,
  equipment); alerts only when a state changes.
- Tested with the internet cable unplugged: OPNsense boots and serves DHCP,
  and the POS, KDS and portal keep working; AI features pause and say why.

### Objective 7 — Barista AI agent
- 32 tools for the system administrator, 26 for the owner, 15 for staff, 2 for
  guests. Each tool has a tier: runs at once, needs the user's confirmation, or
  needs the owner's approval. Every action is recorded in the audit trail.
- Scheduled cross-domain analysis every 15 minutes, e.g. *more Wi-Fi codes
  used than sales would explain*.
- Learning loop: feedback → lessons proposed hourly → only approved lessons
  are used (this also guards against prompt injection).
- Performance: the AI insights panel went from **8.7 s to 0.019 s** on first
  load after background pre-computation.
- Limit to discuss: free models have a daily cap (about 50 requests); the
  system detects it and stops retrying until the reset.

### Objective 8 — Security and privacy
- Three roles plus guests. Accounts are made by invite only: the person
  chooses their own password from an emailed link that works once, within
  3 days.
- MAC addresses and AI audit data are encrypted at rest. A blind-index hash
  allows lookups without decrypting.
- Removed staff with sales history are deactivated rather than deleted, so
  reports keep their names.

---

## 4.3 Testing results

Generated numbers: [TEST_INVENTORY.md](TEST_INVENTORY.md).

| Module | Tests | Passed | Result |
|---|---|---|---|
| Point of sale | 142 | 142 | 100% |
| Inventory | 90 | 90 | 100% |
| Network and captive portal | 414 | 414 | 100% |
| AI agent | 230 | 230 | 100% |
| Accounts, security and roles | 96 | 96 | 100% |
| User interface and accessibility | 93 | 93 | 100% |
| Other | 46 | 46 | 100% |
| **Total** | **1,111** | **1,111** | **100%** |

(v1.25.0.197, 3,821 assertions.) Explain that these are automated
functional tests run before every release, not a one-time check.

---

## 4.4 Evaluation results (fill in from the survey)

One table per characteristic and respondent group, then a summary table.

| Statement | WM | Interpretation |
|---|---|---|
| The register records orders, discounts and payments correctly. | **[ ]** | **[ ]** |
| … | | |
| **Average** | **[ ]** | **[ ]** |

Summary:

| ISO/IEC 25010 characteristic | Owner | Staff | IT experts | Customers | Overall WM | Interpretation |
|---|---|---|---|---|---|---|
| Functional suitability | | | | — | | |
| Performance efficiency | | | | | | |
| Compatibility | | | | — | | |
| Usability | | | | | | |
| Reliability | | | | | | |
| Security | | | | — | | |
| Maintainability | — | — | | — | | |
| Portability | — | — | | — | | |
| **Grand mean** | | | | | | |

Under each table, write 2–3 sentences: the highest and lowest items and why,
backed by respondent comments.

---

## 4.5 Issues found and resolved (discussion material)

Real problems found during development and testing. Panels often ask about
these, because they show the testing worked. Details in
[CHANGELOG.md](../../CHANGELOG.md) and [AUDIT_FINDINGS.md](../AUDIT_FINDINGS.md).

| Issue | Cause | Resolution |
|---|---|---|
| Only one guest could be online at a time | All guests shared one firewall user | Each code gets its own identity (v1.11.1) |
| Guest pages loaded slowly under load | PHP-FPM allowed only 5 workers | Raised to 20 (server tuning) |
| Every page re-downloaded about 570 KB | Missing cache headers for static files | nginx cache headers |
| Trusted phone kept getting disconnected | Enforcement matched a reused guest IP | Match by device, skip trusted devices (v1.19.1) |
| Owner's laptop on Wi-Fi still saw the sign-in page | Only the wired adapter was allowed | Wi-Fi adapter reserved and allowed (Trusted Devices page added) |
| Register unusable on a phone | Wide category bar pushed products off-screen | Layout fix + phone screenshot checks (v1.21.0) |
| Removing a staff member failed or deleted records | Database rules on sales/shifts | Deactivate instead of delete (v1.22.0) |
| Invite links would all have shown "expired" | Email escaped the link's `&` | Plain-text output + regression test (v1.22.0) |
| Dashboard counted network equipment as guests | No list of infrastructure IPs | Infrastructure list + one definition of "guest online" |
| AI chat cut replies short | Timeout mismatch and a display bug | Both fixed (v1.0.0.120 and later) |
