# Features

Everything the system does, grouped by area and by the screen it lives on.
Each section names who can use it: **guest**, **staff**, **admin** (the café
owner) or **super admin** (the system administrator). Higher roles include
the lower ones, except that the super admin has no access to the register.

For how each piece works inside, follow the links to the other guides.

- [Register (POS)](#register-pos)
- [Kitchen display (KDS)](#kitchen-display-kds)
- [Shifts, cash and end of day](#shifts-cash-and-end-of-day)
- [Inventory and purchasing](#inventory-and-purchasing)
- [Dashboards and reports](#dashboards-and-reports)
- [Guest Wi-Fi (captive portal)](#guest-wi-fi-captive-portal)
- [Wi-Fi codes and plans](#wi-fi-codes-and-plans)
- [Network administration](#network-administration)
- [Barista AI](#barista-ai)
- [Accounts and settings](#accounts-and-settings)
- [Notifications and alerts](#notifications-and-alerts)
- [Working without internet](#working-without-internet)
- [Android app](#android-app)

---

## Register (POS)

**Staff, admin** · `/pos` · details in [POS_FLOW.md](POS_FLOW.md)

- **Menu** by category, with search. Each card shows price and stock state:
  *Out of stock* when an ingredient can't cover one more serving, *Low stock*
  when an ingredient is at or under its threshold.
- **Hot or iced** is asked for coffee, tea and signature drinks; the two are
  separate cart lines.
- **Item notes** ("oat milk, less sugar"): tap the item's name in the cart.
- **Dine in / Take away**, **Senior/PWD 20% discount**, cash tendered and
  change. Cash only.
- **Stock check while ordering**: the cart won't let you add a serving the
  ingredients can't cover, counting what is already in the cart. After each
  sale the screen subtracts what was sold, so the next order is checked
  against what is actually left.
- **Wi-Fi codes** are sold like menu items, in the prices and durations set
  on the Wi-Fi Plans page. A **free Wi-Fi code** is added automatically once
  an order reaches the owner's minimum spend, and a progress bar in the cart
  shows how far off it is.
- **"Say to customer"**: after an item is added, the cart shows a sentence
  the cashier can say, in **English and Tagalog**, offering something that
  goes with it (food with a drink, a drink with food, or what customers most
  often buy together). One tap adds it.
  - **Free Wi-Fi nudge**: when the order is just under the free Wi-Fi
    minimum, the suggestion becomes the cheapest in-stock item that gets it
    there, e.g. *"Add a Waffle for ₱90 and you get 1 hour of free Wi-Fi!"*.
    It allows for the senior/PWD discount and disappears once the order
    qualifies.
  - The sentence appears instantly. The AI writes a more natural version in
    the background, and that pair uses it from then on.
- **Place Order** can't be sent twice by a double tap. If it fails, it says
  why (session expired, server error, no connection) and that nothing was
  charged.
- **Order placed** screen with the change due and any Wi-Fi codes to hand
  over. **Printing** is withheld until the register is BIR-registered (see
  [Accounts and settings](#accounts-and-settings)).
- **Order history** (`/pos/history`): past orders, filters, and voids.
- **Cash in/out** button for pay-ins and pay-outs during the shift.
- Works on a tablet held either way: the cart is a side panel on wide
  screens and a slide-up sheet on narrow ones.

### Voids

- **Admin**: voids a sale straight away.
- **Staff**: requests a void with a reason. The sale doesn't change until an
  admin approves; admins get a notification. Only one pending request per
  sale.
- A void does not put the ingredients back in stock (a known limitation).

## Kitchen display (KDS)

**Staff, admin** · `/kds`

- Every open order as a card, oldest first, split into **Barista / Drinks**
  and **Kitchen / Food**, with notes and hot/iced shown.
- Tick items off one at a time, or move the whole order through
  *pending → preparing → completed*. The order completes itself when its
  last item is ticked.
- The card turns amber at 5 minutes and red at 10, and shows **"Waiting too
  long"** once past the reminder time.
- **Recall** brings any of the last 10 completed orders back.
- Refreshes every 30 seconds.

### Waiting-order reminders

**Staff, admin** · on every screen

- When an order hasn't been marked done within the reminder time (default
  **1 minute**, set in Settings → Store Settings), every staff and admin screen chimes,
  vibrates on tablets, and shows the order: number, how long it has waited,
  dine in or take away, and the items. A button opens the kitchen display.
- Repeats every 3 minutes for an order still not done. Several late orders
  are grouped into one reminder.
- Never covers a dialog someone is using, such as a checkout error or cash
  in/out; it tries again on the next check.
- Wi-Fi-only sales (nothing to make) and orders more than 6 hours old never
  trigger it.

## Shifts, cash and end of day

**Staff, admin** · details in [POS_FLOW.md](POS_FLOW.md)

- **Open a shift** with a starting float before the register unlocks. One
  open shift per person.
- **Pay-ins and pay-outs** with a reason.
- **End shift**: the closing report shows expected cash (float + cash
  sales + pay-ins − pay-outs), and the cashier enters the counted amount.
- **Shortage audit**: if the drawer is short, Barista AI writes a summary of
  the shift. The cashier and the admins get it by email and as a
  notification. A balanced or over drawer sends nothing.
- **Z-reads** (`/finance/z-reads`, admin): every closed shift with its sales
  breakdown, recalculated from the sales themselves.
- **Sales report and CSV export** (`/sales`, admin).
- **Staff hub** (`/staff-dashboard`): shift status, the owner's notice board,
  the "86" list (what is out of stock), and Barista AI findings meant for
  staff.

## Inventory and purchasing

**Admin** (deliveries: staff too) · `/inventory/...`

- **Ingredients** with a base unit and an optional packaging unit (add stock
  as "2 boxes", track it in grams), a low-stock threshold, and a status.
- **Products** with a price, category and recipe (how much of each
  ingredient one serving uses).
- **Categories** with an icon, colour and order. *Food* or *drink* drives
  the register's pairing suggestions. Barista AI can suggest a description
  and icon.
- **Stock history** (`/inventory/logs`): every change with the reason
  (sale, restock, wastage, wastage removed) and who made it.
- **Wastage** log. Deleting an entry puts the stock back.
- **Suppliers** with contacts, Viber, email and delivery days.
- **Purchase orders**: drafted by Barista AI from low stock and past
  deliveries, or by hand. *Send* emails the supplier. The email is queued and
  goes out even if the internet is down at the time.
- **Delivery receiving** (staff): record what arrived. A delivery that
  matches a sent purchase order (ingredient, quantity, supplier) is confirmed
  and added to stock automatically; anything else waits for an admin.
- **Low-stock alert**: one notification to the admins when an ingredient
  crosses its threshold during a sale.

## Dashboards and reports

- **Admin dashboard** (`/dashboard`): today's revenue and cash, expected
  revenue, guests online, voucher stock, low stock, top sellers, menu mix,
  revenue split, recent orders and vouchers, live network throughput, and
  Barista AI's daily brief with what it has noticed. Live figures refresh in
  place.
- **Super admin dashboard**: the system itself, covering application host,
  AI provider health (healthy or circuit open), captive portal posture,
  infrastructure nodes, accounts and agent findings.
- **Analytics** (`/admin/analytics`): 7-day revenue and transaction charts,
  performance by category, and a 7-day AI revenue forecast with confidence
  and a growth tip. The forecast is prepared every 3 hours, so the page
  never waits for it.
- **Sign-in Report** (`/network/portal-report`): see [Network administration](#network-administration).

## Guest Wi-Fi (captive portal)

**Guest** · `wifi.lawatkape.lab` · details in [CAPTIVE_PORTAL.md](CAPTIVE_PORTAL.md)

- Joining the Wi-Fi opens the sign-in page automatically (the phone's
  "Sign in to network" window).
- **English / Filipino** switch on every page, remembered per phone.
- **Enter the code** from the slip or receipt, or **scan the QR** on the slip,
  which fills the code in. Codes are 5 characters with no look-alike letters
  (no 0/O, 1/I/L). An "I agree to the Wi-Fi rules" box is required.
- **Wrong codes** say why: used on another phone, expired, unused for too
  long, or not found. Guessing is capped at 30 tries an hour per device.
- **Connected** screen, then the **status page**: time left (a live
  countdown), data used both ways, the plan and its speed, what Premium
  would give a free guest, the menu, and the chat helper.
- **10-minutes-left warning** with a vibration, while the status page is
  open.
- **Time's up** screen with the code and when it ended, **Need more time?**
  (alerts staff and admins), and the menu.
- **Re-entering the same code** on the same phone works until it expires; the
  clock never restarts. If the connection drops while time is left, the
  phone reconnects by itself. A staff disconnect is not undone by that.
- **Log out** asks first.
- **Open in browser**: the sign-in window can hand over to Chrome, Brave,
  Firefox, Samsung Internet, Edge, Opera or the phone's own browser. Some
  phones (Xiaomi, Huawei) need this.
- **Guest chat**: quick answers (where's my code, opening hours, Wi-Fi
  prices, how to reconnect) come from settings without AI; anything else goes
  to Barista AI, which can only see this guest's own code and session.
- **Notices**: "sign-in is having trouble" when the firewall can't be
  reached, and "our internet provider is down, your code still works" during
  an outage.
- The pages load nothing from outside the shop, because a guest who hasn't
  signed in can't reach the internet.

## Wi-Fi codes and plans

**Admin** (selling at the till: staff too)

- **Wi-Fi Prices** (`/network/plans`): price → duration pairs sold at the
  register (default ₱20 = 1 hour, ₱50 = 3 hours, ₱100 = whole day), the
  free Wi-Fi minimum spend and its duration, and the Wi-Fi network name
  printed on slips.
- **Wi-Fi Codes** (`/network/vouchers`, staff can view): generate batches by
  plan (Free or Premium) and duration (admin), filter available or used,
  print one slip or a whole batch, and delete codes in bulk (admin).
- **Slips** carry the code, a QR that fills it in, a "scan to join the Wi-Fi"
  QR, numbered steps in English and Filipino, and a use-by date.
- **Unused codes expire** after a set number of days (default 60; 0 means
  never).

## Network administration

**Admin**. Staff can also open Who's Online and Network Status, and
disconnect a guest or add time.

- **Who's Online** (`/network/sessions`): everything on the network,
  split into guests online, shop equipment, devices waiting to sign in, and
  unknown devices. Each guest shows their code, plan, time left and data
  used.
  - **Find a device** by IP, MAC, name or voucher code, with a plain
    summary of what it is.
  - One-tap actions: **Disconnect** and **add +30 min / +1 hour** (staff
    and admin); **Block**, **Trust** and **change plan** Free/Premium
    (admin). Shop equipment and protected addresses can never be blocked or
    disconnected.
- **Trusted devices** (`/network/trusted-devices`): every device the router
  knows, with name, IP, MAC, maker, and *connected* / *fixed address* tags.
  Search, then **Allow**, which shows the IP and MAC to check first.
  Trusting allow-lists the MAC, plus the IP when the address is fixed and
  outside the guest range. Guest-range addresses are refused, and shop
  equipment can only be removed by the super admin.
- **Blocked devices** (`/network/blocklist`): banned devices with the reason,
  and unblock. Blocking also disconnects the device.
- **Blocked Websites** (`/network/site-blocking`): through Pi-hole. Preset
  groups (social media, streaming and gaming, adult content, piracy) with a
  toggle per site, a ready-made adult-site list, and custom domains.
- **Adult-site alerts**: admins are notified when a guest device looks up a
  likely adult site.
- **Network Status** (`/network/health`): checked every minute, covering
  internet (latency and loss), firewall, DNS (Pi-hole), DHCP pool usage, the
  login page, each piece of equipment, bandwidth and unknown devices. It
  shows 24-hour charts, the top data users and the most blocked lookups,
  with **Check now**, and links each problem to the page that fixes it.
  Admins are alerted only when a check changes state.
- **Wi-Fi Speed** (`/network/traffic`): live throughput, the **plan
  speeds** as the firewall is actually running them (editable; applied to the
  firewall before being saved), and the **fair-use ceiling**, a per-device
  cap for everything not on a plan, with an on/off switch. An **adaptive
  ceiling** lets Barista AI move it between owner-set limits as the shop
  gets busy or quiet.
- **Sign-in Report** (`/network/portal-report`): how many guests got from
  opening the page to getting online, busiest hours, wrong codes by reason,
  more-time requests, and guests cut off early with how long they had been
  idle.
- **Fixed addresses** (super admin, System Administration → Network Config): DHCP reservations
  on the firewall, so a device always gets the same IP.

## Barista AI

Details in [AI_AGENT.md](AI_AGENT.md).

- **Chat** on every screen (the round button, draggable; it keeps clear of
  the register's cart). Each role gets its own instructions and tools.
  Replies stream in as they are written.
- **Photo attach**: send a photo (a delivery note, a screen) for the AI to
  read.
- **Conversation history** (`/ai/conversations`) for staff and admins. Guest
  chats are not stored.
- **Actions**: tools that only read run at once. Tools that change something
  are proposed and wait for approval in the chat or on **AI Actions**
  (`/admin/ai/actions`). Every call is recorded.
- **Scheduled analysis** every 15 minutes (`/ai/analysis-history`): looks
  across sales and the network, reports findings to the right people, and
  may propose actions.
- **Insights** (`/admin/ai/insights`): AI commentary on trends.
- **Learning** (`/admin/ai/lessons`): lessons drawn from ratings,
  corrections and conversations wait for the owner's approval before the AI
  uses them, and can be withdrawn later.
- **Capability gaps**: when the AI says it can't do something, that is
  reviewed hourly. It may learn how, point to the right page, or record a
  request for a new tool.
- **Thumbs up/down and corrections** on replies feed the learning loop.

## Accounts and settings

- **Accounts** (`/accounts`): an admin adds, edits and removes staff
  accounts; the super admin can also create admin accounts. The super admin
  account itself is never listed or editable here.
- **Profile** (`/profile`, everyone): name, email, password.
- **Idle sign-out**: a session left idle is signed out.
- **Settings → Store Settings** (admin): opening and closing time, receipt header,
  waiting-order reminder time.
- **Settings → Barista AI Settings** (admin; testing and model changes: super
  admin): OpenRouter key, the model list and its order, status per model,
  daily allowance.
- **System Administration → Network Config** (super admin): firewall zone, fixed addresses,
  infrastructure addresses (never counted as guests), and a link to Trusted
  Devices.
- **System Administration → Agent Permissions** (super admin): permission tier per AI tool, within
  the limits each tool allows.
- **Receipt printing** (super admin): off until the register is
  BIR-registered.

Every setting and its default is listed in [CONFIGURATION.md](CONFIGURATION.md).

## Notifications and alerts

The bell in the header lists alerts. They can be marked read, dismissed one
by one, or cleared once read.

| Alert | Who gets it | When |
|---|---|---|
| New order | staff | Every sale |
| Inventory warning | admins | An ingredient reaches its low-stock threshold |
| Void requested / approved / rejected | admins / the requester | A staff void request and its outcome |
| Shift shortage | the cashier and admins | A shift closes short (also emailed) |
| A guest wants more time | staff and admins | "Need more time?" on the portal |
| Network check changed | admins | A health check goes wrong or recovers |
| Adult-site lookup | admins | A guest device looks one up |
| Delivery needs review | admins | A delivery doesn't match a sent purchase order |
| Barista AI findings | admins (some also staff) | The 15-minute analysis finds something |
| Fair-use ceiling changed | admins | The adaptive loop moves the ceiling |
| New AI skill or tool request | super admin | The capability-gap review learns something |

Waiting-order reminders are separate: a sound plus an on-screen pop-up, not
a bell notification.

## Working without internet

- The register, kitchen display, Wi-Fi codes, guest sign-in, reports and
  every staff screen keep working, because they all run inside the shop.
- A yellow **"The internet is down"** bar on staff and admin screens says so,
  and what still works.
- Barista AI and the AI features answer at once with "the internet is down"
  instead of waiting out a timeout.
- Emails (purchase orders, shift audits) wait in a queue and send themselves
  when the internet returns, retrying every 5 minutes for up to 3 days.
- Guests see that the provider is down and that their code still works.
- For the router side (OPNsense not waiting for the ISP after a power cut),
  see [OWNER_NETWORK_STEPS.md §3](OWNER_NETWORK_STEPS.md#3-start-the-shop-without-internet-after-a-power-cut-15-minutes).

## Android app

**Staff, admin, super admin** · download on **Profile** · details in [ANDROID_APP.md](ANDROID_APP.md)

- The whole system as an app on an Android phone (7.0 and newer), full
  screen, with the shop's icon.
- Loads the live system over the shop Wi-Fi, so every update to the system
  shows up without reinstalling the app.
- Keeps the screen on, vibrates for order reminders, prints vouchers and
  receipts through Android's print service, saves the sales export to
  Downloads, and offers the camera or gallery for Barista AI photos.
- Off the shop Wi-Fi it shows "Can't reach the shop system" with Try again,
  and a way to change the server address.
