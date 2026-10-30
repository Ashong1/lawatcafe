# Changelog

Versioning scheme: [docs/VERSIONING.md](docs/VERSIONING.md).

Minor versions for 1.0–1.8 were assigned retrospectively when the scheme was
adopted at build 122, and tagged at the commit where each line of work finished.
The build numbers in the commit subjects are original and continuous; nothing in
the history was rewritten.

---

## 1.49.2 — Gemini and Groq really answer; smaller on phones
*build 272*

- Fixed: any chat where Barista AI used a tool was refused by Gemini and
  Groq (400) and fell back to OpenRouter, using up its daily requests and
  leaving Groq "circuit open". Tool calls now go back in the standard
  format, and Gemini gets back the thought signature it needs (or Google's
  placeholder for another provider's call). Checked live: a tool-using chat
  answered from Gemini in 5 s.
- Phones: card padding, big numbers and gaps step down a size everywhere
  in the staff/owner app (not the guest portal); the system dashboard's
  stat tiles sit two per row.

## 1.49.1 — Phone fixes; Android app 1.2
*build 271*

- **Android app 1.2** (Profile → Get the app; installs over 1.1): a
  **Connect** choice of Automatic / In the shop / From anywhere. Automatic
  checks in about a second whether the shop server answers and otherwise
  uses the remote link — outside the shop the old app timed out on the shop
  address ("Webpage not available").
- On phones, the notifications, power and approvals menus span the screen
  under the header instead of running off the left edge.

## 1.49.0 — Visit log
*build 270*

- **Security → Who opened the system**: every visit to the system, once per
  browser visit — when, who (once signed in), from **the shop** or **the
  remote link**, the kind of device, and the first page opened. Tabs for
  From anywhere / In the shop, and a count of remote visits this week. The
  super admin also sees the IP address and browser. Guest Wi-Fi portal
  visits aren't counted. Kept 90 days.
- `lk-migrate` on the server: backs up, then applies new database changes.

## 1.48.1 — Error pages in the shop's style
*build 269*

- Plain error pages instead of a bare "500 | SERVER ERROR": not found,
  no access, page expired, too many tries, and something went wrong, each
  with Go back and Home. They load nothing from outside (guests may see them
  before they're online) and need no database, so they still show when it fails.
- Fixed: the Security page showed a server error for a few minutes because it
  read the new visit log before its table existed. The visit log returns once
  the table is created.

## 1.48.0 — Owner sees the simple version
*build 267*

- The owner (admin) no longer sees technical configuration; the super admin
  does. Blocked on the server too, not just hidden:
  - **Barista AI Settings**: owner sees "Barista AI is connected", the usage
    and the key fields. Model lists, health, Test and Scan are super admin.
  - **Wi-Fi Speed**: owner sees the internet test (**Check again**) and the
    plan speeds, with Barista AI's suggestion to **Confirm** when the plans
    don't fit. Shop reserve, plan Mbps, per-device limit and the automatic
    limit's tuning are super admin.
  - Backup **Restore**, **server** restart/shut down, adding a device by
    address and blocking one by MAC: super admin. The owner keeps Download,
    Restart web app, and one-tap Trust/Block from device rows.
- Raw IP and MAC addresses are hidden from the owner (Who's Online, Trusted
  Devices, Blocked Devices, Network Status, Security log). Devices go by name.
- Plainer words on What It Learned (Rule / How-to / Example answer; who it's
  for), and the dashboard title.

## 1.47.0 — Scan for more AI models
*build 266*

- **Scan for more models** on each provider lists every chat model the key
  can use that isn't in the route yet, then qualifies each one in the
  background: it must call a tool when asked something only a tool can
  answer. Passing models get an **Add** button; failures fold under
  "Show N that failed".
- Barista AI Settings uses the width: usage and keys side by side, provider
  cards in two or three columns.

## 1.46.1 — Test models in the background
*build 265*

- **Test models** (per provider) and **Test all models** buttons for the
  owner. Each model is tested by its own short request, three at a time,
  with a live spinner per row and its response time when done; the rest of
  the system stays usable meanwhile (the old Test Now blocked the page).
- Model lists checked live: Gemini now uses gemini-3.5-flash and
  gemini-flash-lite-latest (gemini-flash-latest hung); Groq uses
  gpt-oss-120b, qwen3.8-27b and gpt-oss-20b (Llama 3.3 was retired).
- Gemini's and Groq's test, swap and reset buttons now work (their routes
  only allowed OpenRouter).

## 1.46.0 — Gemini, Groq and OpenRouter routing
*build 264*

- Barista AI routes across **Google Gemini → Groq → OpenRouter**: if one
  provider fails or runs out, the next answers. Providers without a key are
  skipped, so with only OpenRouter it works exactly as before.
- **Settings → Barista AI Settings** takes a key for each provider. Keys are
  stored encrypted and never shown back; a blank field keeps the saved key.
  Providers without a key no longer show a green "Healthy".
- With a Gemini or Groq key, background learning no longer waits on
  OpenRouter's daily allowance (those providers are used first).
- The POS "say to customer" line uses the first available provider.
- Docs: OpenRouter's free limit rises to 1,000 a day after $10 of credit
  (was noted as $5).

## 1.45.5 — Say when learning is paused
*build 263*

- Barista AI → What It Learned now says when learning is waiting on the
  daily free AI allowance ("Paused: 14 free AI requests left today …
  resumes after the 8:00 AM reset") instead of promising the next run will
  study it. Learning holds off below 15 left so chats keep working.
- "Once an hour" wording updated to every 30 minutes.

## 1.45.4 — The AI knows how it learns
*build 262*

- Asked to "study what you can't do", the assistant questioned the request
  and listed tools it actually has (such as sending purchase orders) as
  things it couldn't do. It now knows that every "I can't" is studied every
  30 minutes and becomes a lesson under Barista AI → What It Learned. When
  asked, it agrees, lists what it can't do yet, and checks its tools before
  saying it can't.

## 1.45.3 — Learning every 30 minutes
*build 261*

- The AI looks for new lessons to propose for review every 30 minutes
  (was hourly), both from conversations and from things it couldn't do.
- The lessons page's "waiting to be studied" count now includes staff chats.

## 1.45.2 — The AI studies staff chats too
*build 260*

- The hourly learning step already read every owner/admin conversation,
  rated or not. Staff conversations were saved but never read; now they are
  studied the same way, and any lesson from them applies to staff. Guest
  Wi-Fi chats stay out: they're anonymous and never saved.

## 1.45.1 — One results card, bigger chat on PCs
*build 259*

- When the AI runs several checks for one answer, they now share **one**
  green card with a single **View more**, instead of a card and link each.
- On a laptop or PC the chat window is taller (up to the screen's height,
  max 820 px) and a little wider (440 px). Phones keep the compact size.

## 1.45.0 — Smoother Barista AI replies
*build 258*

- **Replies flow in** at a steady pace instead of pausing and then appearing
  all at once (the model often sends the whole answer in one burst after
  thinking).
- **Action results are folded**: the green "Checked the network status" card
  shows just its title; **View more** opens the full details.
- **Line speed vs. use**: the network check now gives the line speed from the
  last speed test *and* what is in use right now, labelled separately. Before,
  only the in-use number (e.g. 0.11 Mbps on a quiet line) was reported, and
  the AI called it the bandwidth.

## 1.44.2 — Sidebar and register fixes
*builds 256–257*

- **Collapsed sidebar**: clicking a group icon (Inventory, Wi-Fi & Network,
  Barista AI, Settings, System) now opens the sidebar on that group; before,
  it did nothing. Labels and the version line hide at once when collapsing
  instead of spilling out of the narrow rail, and open groups fold away
  without leaving a gap.
- **Register**: a cash order can't be placed until the cash received is typed
  (at least the total). An **Exact** button fills in the total for exact
  payments.
- Sidebar labels no longer flash inside the collapsed rail for a moment while
  a page loads.

## 1.44.0 — Remote access
*builds 254–255*

- **Settings → Security → Remote access**: the owner or super admin can turn
  on a secure Cloudflare link to the system (lawatkape-remote.aserotech.com)
  to use it from anywhere, with no router changes, and turn it off again. Off
  by default; stays on until turned off. Each switch asks first, is logged,
  and alerts the other owners.
- **Only the owner and admins can sign in through the remote link**; staff
  are told to sign in at the shop (a phone's MAC address isn't visible over
  the internet, so the account's role is the check).

## 1.43.4 — Minor fixes from the review
*build 253*

- "Test now" answers at once and measures in the background (a slow line
  could time the page out).
- A refund that brings an order below the free Wi-Fi minimum cancels its
  unused free codes.
- No "drawer differs" alert when another shift is still open.
- Best sellers and sales by category say "(at menu price)".
- Exporting a year of orders works in batches.
- Security page: newest 24 backups, hourly wording; hourly backups only log
  failures (manual ones still log); last backup read from the backups.
- Escape on an "Are you sure?" no longer closes the form underneath.

## 1.43.3 — Fixes from the pre-defense review (network, inventory)
*build 252*

- The daily speed test tries **once a day**, even if it fails (a failure was
  retried every 15 minutes — hundreds of MB of prepaid data).
- Barista AI lowers a plan only after **two slow days in a row**, never below
  2 Mbps, and Premium always stays faster than Free.
- Automatic adult-site blocking only blocks a site whose **own name** is
  adult — never a shared host like github.io, blogspot.com or a CDN (those
  only alert).
- "Missing" in Check a delivery now saves (the greyed-out amount wasn't sent).
- One purchase order can't be matched by two lines of one delivery.
- A supplier search that matches several suppliers asks which one.
- Expired write-off only works on stock that is actually past its date; a
  recount isn't treated as a new batch.
- "Are you sure?" keeps which button was pressed and asks again next time.

## 1.43.2 — Fixes from the pre-defense review (money)
*build 251*

- Refunds pay back what the customer actually paid (after Senior/PWD
  discount), an item can't be refunded twice, Wi-Fi codes can't be refunded,
  and a Z-Read day can't be refunded.
- Approving a staff request re-checks the order (and locks it), and a request
  only shows as approved if it went through. The owner can't refund directly
  while a request for that order is waiting. Owner refunds are now logged in
  the same queue.
- A partly refunded order can't be voided (it booked a false shortage).
- Cash refunds need an open drawer and say whose drawer paid.
- No shift can start after the day's Z-Read; two Z-Reads at once give a clear
  message.
- CSV exports can't run spreadsheet formulas from names.

## 1.43.0 — Wi-Fi codes per order; ask before every change
*builds 249–250*

- One Wi-Fi code per phone stays. When an order reaches the free Wi-Fi
  minimum, the cart has **"Free Wi-Fi codes – 1 +"** so a group gets one each
  (up to 10). "Place this order?" now spells out the codes to be made
  ("3 Wi-Fi codes · 2 free Wi-Fi codes (60 mins)").
- The register refuses a zero or impossibly large quantity, and more than 20
  Wi-Fi codes in one order, with a plain message, and makes nothing.
- **"Are you sure?" on the rest of the one-click actions**: making Wi-Fi
  codes, blocking a device or site, trusting a device, recording deliveries,
  saving categories, suppliers, store settings, Wi-Fi prices, Barista AI
  permissions, the AI key and staff accounts.

## 1.42.0 — Orders by day, week, month or year; export
*build 248*

- **Orders** has a "Show" choice (Daily, Weekly, Monthly, Yearly) next to the
  date, a count for the period, and **Export CSV** of exactly what's listed
  (order no., date and time, type, items, payment, discount, total, refunded,
  status, cashier). Rows show the date when more than one day is listed.

## 1.41.0 — Pick suppliers from a list; a tidier register
*builds 246–247*

- **Supplier dropdown** on Receive Supplies (owner) and Receive Delivery
  (staff): tap a saved supplier instead of typing it; "Someone else…" for a
  new one. A delivery opened from a PO keeps its supplier.
- Wastage reasons include **Spoiled (napanis)**.
- **Register toolbar is one row**: search, then Orders, Cash and X-Read as
  icon buttons (with labels on hover), then End Shift. Who's Online and
  Download moved off the register (sidebar and Reports have them; the phone
  menu keeps them). The menu cards size to the space they have, and the
  Orders to make column shows from 1536 px wide (a button below that).

## 1.40.0 — Register and Kitchen Display are one screen
*builds 244–245*

- **The Register shows Menu | Orders to make | Current order side by side**
  (from desktop width), so the one person on duty takes and makes orders on
  the same screen. On phones and small tablets the "Orders to make" button
  opens the same list.
- **Kitchen Display is gone from the sidebar**; its old address opens the
  Register. The staff dashboard's kitchen queue and the waiting-order
  reminder point to the Register.
- **Non-VAT everywhere:** the cart and the receipt no longer show a 12% VAT
  line ("VAT-exempt" instead), and the receipt's placeholder address and
  TIN are gone.

## 1.39.0 — Check a delivery by PO number
*build 243*

- **Check a delivery** on Receive Delivery (owner and staff): type the PO
  number from the supplier's receipt, or the supplier's name, to get that
  supplier's open orders as a checklist. Change "Received" when less came,
  tick "Missing" when an item didn't come. Short items hold the delivery for
  the owner with a note ("Short: Milk 800 of 1,000 ml"); missing items stay
  on order and the owner is told. The owner reaches it from Deliveries.

## 1.38.0 — Activity Log by kind and date
*build 242*

- **Activity Log** (Settings → Security) filters by kind (Sign-ins, Accounts,
  Backups, System) and by date range, and points to Stock History for stock
  changes, so security and inventory records aren't mixed up.

## 1.37.0 — Expiration dates
*build 241*

- **Restock asks for the expiration date** (optional, from the package).
- **Expiring soon** on Ingredients: stock still on hand that expires within
  30 days, grouped Already expired / This week / This month, with the date it
  was received. Stock is used oldest first, so what's left is worked out from
  the newest deliveries. **Write off** logs the expired amount as wastage.

## 1.36.0 — Receive deliveries by purchase order
*build 240*

- Every sent purchase order has a **PO number** (PO-00042), shown on Purchase
  Orders, in the supplier's email ("please put it on your delivery receipt")
  and on Receive Delivery.
- **Receive** on a pending order opens the form already filled in (supplier,
  ingredient, quantity ordered, cost); staff only fix what really arrived.
  Matching is exact to that PO; a short delivery still waits for the owner.
- "Supplier's receipt / DR no." replaces "Invoice no."; typing a supplier by
  hand suggests the saved suppliers.

## 1.35.0 — Refunds
*build 239*

- **Refund items** on Order History: pick items and quantities and give a
  reason. The owner's refund goes through at once; staff's waits in the same
  approval queue as voids. A cash refund comes out of the open shift as a
  cash-out, so the expected drawer stays right. Ingredients are not put back
  in stock (the item was already made).
- Refunds show on the Sales Report (taken off net sales), the X-Read and the
  Z-Read.

## 1.34.0 — Cash handover
*build 238*

- **Start Shift shows the last closing count** (amount, who, when). If the
  drawer doesn't match it, the opener must say why and the owner is told,
  so no one verifies their own cash count alone.
- **Closing has no "Use Expected Amount" button**: the drawer has to be
  counted. The expected amount is still shown.

## 1.33.0 — X-Read and Z-Read
*builds 236–*

- **X-Read** on the register (and in the phone menu): this shift's totals so
  far — orders, gross, Senior/PWD and other discounts, net, VAT-exempt sales,
  voids, cash and e-wallet, cash in/out, expected drawer — printable on the
  80 mm receipt printer. Nothing is closed or reset.
- The closing report, End of Day and X-Read now share one calculation.
- **Z-Read** on End of Day: "Take Z-Read for today" once every shift is
  closed. Numbered Z #0001, #0002… without gaps, with the running grand
  total, first and last order, and the day's totals; printable. After it,
  that day's orders can't be voided.

## 1.32.0 — Barista AI runs the Wi-Fi speeds and blocks adult sites
*build 235*

- **Speeds are AI-managed by default.** Automatic speed is on for new
  installs; Barista AI may change the Free and Premium plan speeds without
  asking (Settings → Barista AI can make it ask first again); after the daily
  internet test it lowers any plan set faster than the line can give guests,
  and tells the admins. Off switch: "Let Barista AI lower a plan" on
  Wi-Fi Speed.
- **Adult sites are blocked on the spot.** When a guest opens one that isn't
  on the block list, Barista AI blocks it for everyone and tells the admins.
  Off switch: "Barista AI blocks new ones" on Site Blocking.
- Blocking devices, disconnecting guests and making Wi-Fi codes still need
  an admin's approval.

## 1.31.0 — Orders to make, on the register; real internet speed
*builds 233–234*

- **Orders to make** button on the register (with a count) opens a side
  panel of waiting orders with **Start** and **Done**. One person takes and
  makes orders on the same screen; no switching to the Kitchen Display. The
  list refreshes every 15 seconds and right after each order. The Kitchen
  Display page stays for shops with a second screen.
- **Your internet line** on Wi-Fi Speed: the speed the internet provider is
  really giving (download and upload), measured once a day after opening
  (about 13 MB, for the prepaid plan) and with **Test now**; what's in use
  right now; and advice: Mbps per guest at the busiest time after keeping
  some for the shop, how many guests each plan fits, and a warning when a
  plan is set faster than the line.

## 1.30.0 — Reports
*builds 230–*

- **Reports → Sales** (owner): pick Daily, Weekly, Monthly or Yearly and
  any date. Net sales with the change from the previous period, orders and
  average, cash vs e-wallet, discounts, voided orders, a chart and table by
  hour/day/month, best sellers, slow movers and sales by category. Print or
  download as CSV.
- The Sales page totals no longer count voided orders.
- **Reports → Stock**: for the same periods, each ingredient's start,
  received, used in sales, wasted, adjusted and end amounts; delivery and
  wastage cost; most used, most wasted (with reasons) and what's running
  low. Print or download as CSV.
- Stock History's person filter now lists staff too.
## 1.29.0 — Evaluation fixes (Oct 28 evaluation)
*builds 218–*

Feedback list: [docs/capstone/EVALUATION_2026-10-28.md](docs/capstone/EVALUATION_2026-10-28.md).

- **Sign-in locks after 3 wrong passwords** (was 5), as the evaluators asked.
- **"Save this?" before inventory and network changes:** ingredients,
  restocks, products, wastage, reserved addresses and the automatic speed
  setting now ask first (plan speeds and fair use already did).
- **Wi-Fi code typed wrong 3 times** from one device blocks that device for
  5 minutes; a code that exists resets the count.
- **Wastage entry capped** at one opened package (e.g. one 1,000 ml box)
  and at the stock on hand; the ingredient list shows each package size.
- **Accounts are archived, never deleted.** "Remove" is now "Archive",
  and an account with no sales is archived too, so it can always be restored.
- **AI Usage card** on Barista AI Settings: OpenRouter credit balance and
  today's free AI requests (e.g. "13 of 50"), with a warning when the
  balance is below zero.
- **Pending AI actions** icon is a robot with a cog, so it isn't mistaken
  for the chat button.
- **Backups every hour** while the server is on (was once at 11:30 PM,
  which never ran on nights the server was already off); the last 7 days
  are kept.
- **Archived accounts get no alerts or emails** (shift shortages, low stock,
  network, deliveries).
- **Profile no longer has "Delete Account"**: only the owner archives
  accounts, so no account (or its sales) can disappear.
- Fixes from the batch review: the AI Usage card reads a key saved on the
  page and doesn't stall offline; restoring the oldest backup no longer
  deletes it first; only the shop's proxy may pass on a client's address,
  so the per-device limits can't be dodged with a faked header.

## 1.28.0 — Confirmations and sign-in lockout
*build 217*

- **"Place this order?"** before every checkout, showing items, total,
  payment and change.
- **"Log out?"** before signing out (owner and staff).
- **Sign-in page** says how many tries are left after a wrong password,
  and counts down live to when sign-in unlocks after the 5th.

## 1.27.0 — Barista AI: plan speeds, no repeat proposals
*build 216*

- The assistant can change the free and premium plans' download and upload
  speeds (needs approval).
- It no longer re-proposes an action it already asked for when you send
  something else.
- It knows pack sizes (a box of milk, a sack of beans), so it stops asking.

## 1.26.0 — Security: activity log, backups, HTTPS
*builds 201–215*

- **Settings → Security** (owner only): sign-ins, sign-outs,
  wrong-password tries, lockouts, password resets, account changes and
  backups, with time, device address and browser. Entries can't be edited.
- **Nightly database backup** at 23:30, last 14 kept; each run is logged.
  **Settings → Security** makes, downloads and restores them (owner
  password; a safety backup is made before every restore).
- **Power icon in the top bar** (owner and system admin, password
  each time, all logged): restart or shut down the web app (staff see a
  "back soon" page, guest Wi-Fi keeps working, the owner keeps a Turn back
  on button), restart or shut down the server (needs a one-time sudoers
  line; shutting down also needs SHUT DOWN typed).
- **Profile** (click your name): the Android app, plus a **Windows** setup
  wizard and a **Linux** (.AppImage) app that open the system in their own window,
  or install it straight from Chrome, Edge or Brave.
- Staff use **https://lawatkape.aserotech.com** with a trusted Let's Encrypt
  certificate (renews itself). Cookies are HTTPS-only there and session data
  is encrypted. `lawatkape.lab`, the bare IP and the guest portal still work
  on http.
- The app checks the firewall's identity (pinned key) and talks to Pi-hole
  over HTTPS. Extra browser security headers.
- **Android app 1.1** opens the HTTPS address and falls back to the local one
  on its own when it can't be reached. The build now really sets the version
  number, so updates install over the old app.
- **Privacy notice** on the guest Wi-Fi portal (English and Filipino), linked
  beside the Wi-Fi rules above Connect, as the Data Privacy Act requires.
- Incident-response plan and restore steps in OPERATIONS.md; cybersecurity
  checklist in docs/capstone/SECURITY_CHECKLIST.md.

## 1.25.1 — Whole-system design pass and the real menu
*builds 199–200*

- Every screen polished for phones: 44px buttons, readable contrast, plain
  wording (Who's Online, Wi-Fi code), and several small bugs fixed (blank
  staff notice board, "Change ₱0.00" when cash was short, squashed portal
  time card, emails showing "&amp;").
- The register hides category tabs with nothing on sale.

## 1.25.0 — Kitchen order slip
*build 197*

- After an order, **Kitchen Slip** prints the kitchen's copy: order number,
  dine in or take away, each item with its quantity and note. It has no
  prices or totals and says "Kitchen copy — not a receipt", so it prints
  while customer receipts stay off until BIR registration.
- Reprint any order's slip from **Orders** (chef-hat button). In the Android
  app the slip opens in place with **Back to register**.

## 1.24.0 — E-wallet QR payments
*build 196*

- **Store Settings → E-wallet payments (QR)**: upload the shop's GCash,
  Maya or QR Ph (InstaPay) receive QR and the account name.
- **Register**: Payment now offers Cash plus each wallet that is set up.
  Picking one shows the QR full screen with the amount; the cashier types the
  reference number from the customer's "sent" screen before the order goes
  through.
- The reference number shows in Orders, Sales Reports and the sales CSV.
  The end-of-shift report lists e-wallet money on its own line, outside the
  drawer count.

## 1.23.0 — Product photos
*build 195*

- Products can have a **photo**: in Product Menu → Add or Edit, tap
  **Add a photo** to take one with the camera or pick one from the gallery.
- Photos show on the **register** (product cards and the cart), in the
  **product list**, and on the **guest Wi-Fi menu**. Products without a
  photo keep their category icon.
- Phone photos are shrunk on the phone to about 100 KB before uploading, so
  saving is quick and storage stays small. Replacing or removing a photo, or
  deleting the product, deletes the old file.

## 1.22.0 — Adding staff by email invite
*build 194*

- **Adding staff** asks for a name, a username (suggested from the name) and
  an email. The person gets an email with a link to choose their own
  password and is signed in; the owner never types or knows it. Links last
  3 days and work once.
- Staff sign in with their **username or email**. "Forgot password?" accepts
  either and sends the same kind of link.
- The staff list shows who is still **waiting to set a password**, with
  **Resend** and **Copy link** (to send by text when the email can't go out).
- A form with a mistake keeps what was typed instead of clearing it.
- **Removing** someone who has sales or shifts on record used to fail with a
  server error, and for someone with only cash movements, wastage or
  deliveries it deleted those records with them. They are now switched off
  instead: they can't sign in, their name stays on the reports, and
  **Removed → Restore** brings them back.

## 1.21.1 — Less crowded on a phone
*build 193*

- Page titles on a phone drop the script logo (it is in the menu) and sit
  closer to the content; cards use less padding and less space between them.
- The header shows a round initial instead of a cut-off name.
- Register: Orders, Who's Online, Download, Cash in or out and End Shift
  moved into one **⋮** button beside the search, so the menu starts on the
  first screen. The extra frame around the menu is gone on phones.
- Dashboard: four short shortcuts (Register, Wi-Fi Codes, Supplies, Today's
  Sales) and at most three Barista AI notes on a phone.
- Wi-Fi Codes: search and status on one row; Make Codes comes first.
- Who's Online: shorter equipment labels; MAC and maker show from tablet size.
- Smaller Barista AI chat button on phones.

## 1.21.0 — Phone-sized screens for the Android app
*build 192*

- Register on a phone: products sit two to a row and fit the screen. A wide
  category bar used to stretch the page so the right-hand items and their
  + buttons were cut off. Tapping anywhere on a product card adds it, and the
  stock badges sit on the photo.
- The chat button moves above the phone cart, so it no longer covers the
  total or Place Order.
- Lists (Wi-Fi codes, orders, sales, inventory, staff, who's online and
  more) turn into stacked cards on narrow screens. On a phone they used to be
  wide tables that needed sideways scrolling.
- Buttons, menu items, quantity +/- and checkboxes are at least 44px tall on
  touch screens, the size a thumb can hit reliably. Amount fields open the
  number keypad.
- Dashboard shortcuts and figures sit two to a row on a phone.
- Wi-Fi items read "1 Hour Wi-Fi" / "30 Minutes Wi-Fi" in carts and receipts.
- New developer tool `.claude/skills/mobile-screenshot-audit`: screenshots any
  page as the app sees it at phone size and reports cut-off content, small
  tap targets and tiny text.

## 1.20.3 — Finance, inventory, accounts and settings in plain words
*build 191*

- End of Day (Z-reads): "Declared" is now "Counted" and "Variance" is
  "Short / over", with a plain explanation of what the page shows.
- Ingredients, Stock History, Staff Accounts, Store Settings and the
  purchase-order buttons use everyday wording; Store Settings no longer
  describes things it doesn't contain.
- The guides use the new page names.

## 1.20.2 — Dashboard and Barista AI pages in plain words
*build 190*

- Dashboard: the "Revenue split" against E-wallet (no longer taken) is now a
  single "Cash taken" figure. The firewall's gateway names are replaced by
  "Internet connected" / "Internet down". Buttons read Open Register, Make
  Wi-Fi Codes and Download Today's Sales. The sales outlook uses plain
  headings: The next 7 days, Expected sales, How sure it is, Watch out for,
  Suggestions, Selling well, Selling slowly.
- Sales Forecast: plain headings. The AI model name shows only to the system
  administrator.
- Fixed: Actions & Approvals said "Tried an action that doesn't exist" for
  four real Barista AI tools (network status, device lookup, top data users,
  website filter) that were missing from its list. A test now checks that
  every tool has a label.
- What It Noticed and What It Learned explain themselves in plain words.

## 1.20.1 — Wi-Fi pages in plain words
*build 189*

- **Who's Online**: devices show their real names (App server, Pi-hole, the
  owner's laptop, the phone's own name) instead of "Unknown Device", and MAC
  addresses read AA:BB:CC:DD:EE:FF. Trusted phones and laptops are labelled
  "Trusted device", separately from the shop's equipment. The internet
  provider's router and the firewall's outside address no longer appear as
  guests or unknown devices. Idle devices read "idle", not "0 bps".
- **Network Status**: checks are named Internet, Router, Website filter,
  Wi-Fi addresses, Wi-Fi sign-in page, Shop equipment and Internet use, and
  their messages say what is wrong in plain words. The list of heaviest users
  shows device names.
- **Wi-Fi Speed**: "Fair-use ceiling" is now "Speed limit per device" and
  "Adaptive ceiling" is "Automatic speed limit", with plain explanations.
- **Wi-Fi Prices**: plain names. Fixed: the first paid price showed Free-plan
  speeds, though every code sold at the register runs at Premium speed.
- **Wi-Fi Codes** and **Blocked Devices**: plain buttons and confirmations
  ("Make Codes", "Clear Used Codes", "Block Device"), and the hand-entry form
  says where to find a phone's MAC address.

## 1.20.0 — Plain-language menus for the owner and staff
*build 188*

- The admin menu puts daily work first (Dashboard, Register, Kitchen Display,
  Orders, End of Day), then Inventory, Wi-Fi & Network, a new **Barista AI**
  group, and Settings. The four AI pages used to sit at the top.
- Plain names throughout: Who's Online (Active Sessions), Wi-Fi Codes
  (Vouchers), Wi-Fi Prices (Wi-Fi Plans), Wi-Fi Speed (Traffic Shaping),
  Blocked Devices, Blocked Websites, Sign-in Report, Network Status, Stock
  History (Audit Logs), End of Day (Z-Reads), Sales Forecast, Actions &
  Approvals, What It Noticed, What It Learned, Store Settings, Barista AI
  Settings. Page headings match.
- The staff menu uses the same names.
- Barista AI points people to pages by these names, and now knows the Register,
  Kitchen Display, Network Status, Trusted Devices and Sign-in Report too.
- The Wi-Fi Speed page no longer sends the owner to a settings page only the
  super admin can open.

## 1.19.1 — Trusted phones stay online
*build 187*

- Fixed: a phone trusted on Trusted Devices was disconnected within a minute
  if its guest-range address had been used earlier by another guest's code.
  The expiry job matched that old code by IP, saw a different device, and
  disconnected the trusted phone as code sharing. It now leaves alone every
  session the firewall opened from its trusted list, and every trusted MAC.
  The guest portal page follows the same rule.
- A trusted device that is on the network without a session (it was off when
  trusted, or was cut off) now gets it back by itself: the job reloads the
  captive portal, at most every 10 minutes. Tapping Trust again on a trusted
  device does the same at once.
- Active Sessions shows trusted devices with the shop's equipment instead of
  under "Waiting to sign in" with Trust and Block buttons.

## 1.19.0 — Android app
*build 186*

- **Lawa't Kape for Android** (`android/`): the whole system as an app on
  the shop phone, downloaded from the Profile page by signed-in staff. It
  runs full screen and keeps the screen on. It vibrates for order reminders,
  prints vouchers and receipts through Android's print service, saves the
  sales export to Downloads, and offers the camera or gallery for Barista AI
  photos. Off the shop Wi-Fi it shows a "can't reach" screen with Try again.
  It loads the live system, so updates need no reinstall.
- Uses the server's own address over plain HTTP, allowed only for the shop's
  addresses. The staff HTTPS name's certificate isn't trusted by phones.
- Receipt and voucher-batch pages print inside the app, and batch print
  opens in place instead of a new tab.
- Built with `android/build.sh` (SDK tools, no Gradle). The signing key is
  kept outside the public repository. See docs/ANDROID_APP.md.

## 1.18.1 — Documentation, safer seeding, adaptive ceiling fix
*build 185*

- The README and guides were rewritten to match the system as it is: new
  docs/FEATURES.md (every screen by role), docs/CONFIGURATION.md (every
  `.env` variable and owner setting with defaults) and docs/OPERATIONS.md
  (install, deploy, jobs, backups, troubleshooting). The AI, portal, POS,
  database, architecture and testing guides were brought up to date (they
  still described Gemini/Groq, 17 tools and the old portal flow).
- `.env.example` now lists every setting the app reads (Pi-hole, portal
  host, speed-plan aliases, TLS, timezone, seeded accounts).
- No passwords in the repository: the seeder takes accounts from `.env` or
  generates and prints a password once, and the July data migration no
  longer contains one. A fresh `migrate --seed` also no longer fails on a
  duplicate admin account.
- Fixed: the adaptive fair-use ceiling's tool declared a permission tier the
  system didn't recognise, so every change it decided on waited for an
  admin instead of applying by itself. A test now checks every tool's tier.

## 1.18.0 — Waiting-order reminders
*build 184*

- When an order hasn't been marked done within 1 minute, every admin and
  staff screen (register, kitchen display, dashboards) chimes and shows it:
  "Order #1234 has been waiting 2 mins · Take away · 2× Latte, 1× Waffle",
  with a button to open the kitchen display. It repeats every 3 minutes until
  the order is done, and waits rather than covering a dialog in use.
- The owner can change the 1 minute in Settings → Store.
- The kitchen display marks those orders "Waiting too long".
- Wi-Fi-only sales and orders left open from earlier in the day never trigger
  it. New index on sales (status, created_at) keeps the check cheap.

## 1.17.0 — "Say to customer" in English and Tagalog, with a free Wi-Fi nudge
*build 183*

- The register's "Say to customer" line now comes in English and Tagalog;
  the cashier says whichever fits the customer.
- When an order is just short of the owner's free Wi-Fi minimum, the
  suggestion becomes one item that gets it there, e.g. "Add a Waffle for ₱90
  and you get 1 hour of free Wi-Fi!" / "Dagdag po kayo ng Waffle (₱90), may
  libre na po kayong 1 oras na Wi-Fi!". It picks the cheapest in-stock item
  that is enough, preferring the kind that goes with the order (food with a
  drink), and allows for the senior/PWD discount. It uses the minimum and
  free time set in Settings, and goes away once the order qualifies.
- The AI-written line no longer holds up the register: the fixed sentence
  shows at once, and the AI's version (which the free models take up to 20
  seconds to write) is prepared in the background and used from then on.

## 1.16.1 — Faster register; chat button and notifications no longer in the way
*build 182*

- Register: each menu card draws one icon instead of one for every category;
  no blur or endless pulse on out-of-stock and low-stock cards; the phone-size
  cart sheet is only built while open, so each tap updates one cart, not two.
- Register: Place Order can't be sent twice by a double tap, and says why
  when it fails (expired session, server error, no connection) instead of
  doing nothing. Stock on screen goes down after each sale, so the next order
  is checked against what is left.
- Register: the AI "say to customer" line is remembered per pair of items, so
  only the first add of a pairing waits on the AI. A suggestion no longer
  carries into the next order or gets replaced by a slower, older one.
- Low-stock alerts are sent once, when an ingredient reaches its threshold,
  not again on every later sale.
- The Barista AI button keeps clear of the cart and its Place Order button
  (and the phone-size View Cart bar) until you drag it somewhere yourself.
- The notifications and profile dropdowns open over dashboard cards instead of
  behind them.

## 1.16.0 — Keeps working when the internet is down
*build 181*

- A yellow "The internet is down" bar on admin and staff screens while the
  every-minute network check finds no internet, saying what still works.
- Barista AI and the other AI features stop at once with a plain "the
  internet is down" message instead of waiting out a timeout. Closing a shift
  no longer stalls on the AI summary.
- Purchase-order and shift-audit emails are queued and retried every 5 minutes
  for up to 3 days, so an email sent during an outage goes out once the
  internet is back. The scheduler now runs the queue every minute.
- The guest Wi-Fi page tells guests the provider is down and that their code
  still works (English and Filipino).
- Owner steps for starting the shop without internet after a power cut
  (OPNsense WAN timing, Proxmox start order): docs/OWNER_NETWORK_STEPS.md §3.

## 1.15.1 — Fair-use ceiling on/off switch
*build 180*

- The fair-use ceiling can be turned off and back on from the Traffic page.
  Turning it off disables only its own rules on the gateway; guests keep
  their plan speeds.
- While it is off, Barista AI and the adaptive loop leave it alone and can't
  turn it back on by themselves.

## 1.15.0 — Trusted devices, picked from a list
*build 179*

- New Network → Trusted Devices page for the admin and super admin: every
  device on the network with its name, IP and MAC, and a one-tap Allow that
  shows the IP and MAC to check before it trusts the device.
- Trusting a device with a fixed address now allow-lists that address as well
  as its MAC, so it keeps skipping the Wi-Fi login after its address changes.
- Guest-range addresses can't be allow-listed, because the next guest could
  get the same one. Shop equipment can only be removed by the super admin.

## 1.14.2 — Traffic page shows the plan speeds
*build 178*

- The Traffic page now shows each plan's speed as the gateway is running it
  (Free 5/3, Premium 15/6 Mbps), with how many devices are on it, and lets you
  change them. Changes apply to the gateway first and are only saved if it
  accepts them.
- The fair-use ceiling no longer says "In Force" when it is switched off on the
  gateway.

## 1.14.1 — Plan speed caps actually enforced
*build 177*

- Free and Premium speed caps were saved but never applied: this gateway
  only shapes through Shaper rules, and those can't name an alias. On
  OPNsense 25.7 their source/destination take a list of addresses, so each
  plan's rules now carry its members' IPs, re-synced on connect, release,
  plan change and the 5-minute reconcile. A plan with nobody on it has its
  rules switched off.
- Shop equipment is never put in a plan group (192.168.2.99 was in the free
  group).

## 1.14.0 — Portal: time's up, add time, quick answers, report
*builds 175–176*

- The "I agree to the Wi-Fi rules" checkbox is back (required), above the
  Connect button with a larger tap target.

- "Your Wi-Fi time is up" screen (code and end time, Need more time?, menu)
  instead of a bare Connect page when a code has run out.
- Staff and admins add +30 min / +1 hr to a guest's code from Active
  Sessions or Find a device; the "Need more time?" alert opens straight on
  that guest. Time already run out counts from now.
- Guest chat quick questions (where's my code, opening hours, Wi-Fi prices,
  how to reconnect) answered from settings with no AI call.
- Voucher slips: numbered steps in English and Filipino, a "scan to join the
  Wi-Fi" QR (Wi-Fi Plans → network name), and a use-by date.
- Unused codes stop working after a set number of days (default 60, 0 =
  never).
- The chat widget's script moved from inline Blade into the bundle
  (resources/js/agent-chat.js): portal pages went from 78 KB to 37 KB.
- Status page: data used counts downloads and uploads; shows the plan and
  speed, and what Premium would give a free guest.
- Portal report (Network → Portal Report): sign-in funnel, busiest hours,
  wrong codes by reason, more-time requests, and guests cut off early with
  their idle time (recorded by the minute-by-minute network check).

## 1.13.0 — Captive portal for everyone
*build 174*

- English / Filipino switch on every portal page (remembered per phone).
- Larger, sentence-case text; no terms checkbox ("By connecting you agree
  to our Wi-Fi rules" instead).
- Voucher slips: each QR carries its own code, so scanning fills it in; the
  printed address is wifi.lawatkape.lab (it used to print the staff host).
- Status page: the phone's name instead of the firewall login name; "Need
  more time?" alerts staff and admins; logging out asks first and is no
  longer a big red button.
- Portal loads about half as much: its own CSS/JS bundle (54 KB CSS vs
  104 KB, no axios) and a 69 KB background instead of 387 KB.
- A warning on the sign-in page when the health check can't reach the
  firewall.
- New codes are 5 characters from an alphabet without look-alikes (no
  0/O, 1/I/L); code guessing is also capped at 30 tries an hour per device.
- Fixed: a staff Disconnect no longer gets undone by auto-reconnect; and
  auto-reconnect now requires the phone the code was used on, not just the
  same IP address.
- docs/OWNER_NETWORK_STEPS.md: forcing guest DNS through Pi-hole, and the
  `.localdomain` Pi-hole record.

## 1.12.1 — Network pages made easier
*builds 168–173*

- "Open in Browser" in the sign-in window now asks which browser (Chrome,
  Brave, Firefox, Samsung Internet, Edge, Opera, or the phone's own) and
  opens that app directly. The status and success pages show
  wifi.lawatkape.lab instead of an IP address.

- Xiaomi's sign-in window no longer gets the automatic browser handoff after
  a code (it opened Xiaomi's own browser, which can't resolve .lab names).
  "Open in browser" links use the portal's IP for the same reason.

- The sign-in window's "Open in Browser" bar opens the portal instead of an
  outside address that is blocked before sign-in.

- Xiaomi's sign-in window is recognised (it omits Android's "; wv)" tag),
  so guests get the "Open in my browser" button there.
- "Connecting…" no longer hangs in a normal browser: after connecting, a
  page still on screen moves on to the guest's time left.

- Portal menu and AI chat can be scrolled back up on phones whose sign-in
  window or browser wraps the page in a pull-to-refresh layer (Xiaomi).
  The chat no longer pulls a guest back to the bottom while a reply streams
  in if they have scrolled up to re-read.

- Active Sessions: **Find a device** by IP, MAC, hostname or voucher code,
  with one-click **Block** and **Trust** buttons on the result and on every
  guest, waiting and unknown-device row. Shop equipment and protected
  addresses can never be blocked from here.
- Plain names throughout: "Guests online", "Shop equipment", "Waiting to
  sign in", "Not signed in — unknown devices", "Blocked devices".
- Network Health links each problem to the page that fixes it, with tips
  for internet and firewall outages.
- Auto-refresh pauses while the tab is hidden.
- The device lookup behind the AI's `lookupDevice` tool is now one shared
  `DeviceLookupService`; fixed per-IP device labels never being read.

## 1.12.0 — Network administration focus
*build 167*

- Network Health page and a `network:health` check every minute (internet,
  firewall, DNS, DHCP pool, login page, equipment, bandwidth, unknown
  devices), with admin alerts only when a check changes state.
- AI network tools (health, device lookup, top bandwidth users, DNS stats)
  and network-first AI prompts.

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
