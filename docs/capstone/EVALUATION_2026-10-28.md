# Evaluation feedback — 2026-10-28

Source: two screen recordings of the system evaluation (`2026-10-28 18-44-57.mp4`,
`2026-10-28 20-21-17.mp4`), transcribed locally with whisper.cpp (small model).
Times are `[recording] mm:ss`. Tagalog transcription is rough; meaning below is
reconstructed from context. Evaluators (as heard): first evaluator (R1 00:00–17:04),
Sir Manalo (R1 17:45–53:00), Ma'am Ella (auditor), Ma'am Lyselle (QA) and Sir Eman (R2).

The adviser's instruction (R1 55:03–55:52): the comments are holes the defense panel
can use; improve the system before the defense (last week of November).

Status legend: **Done** = already in the code · **Partial** · **To do** · **Question** = answered live, no change asked.

## POS and cash

| # | Where | Request | Status |
|---|---|---|---|
| 1 | R1 00:00, 16:24; R2 15:15 | Confirmation pop-up before critical actions (orders, inventory changes, Wi-Fi settings) — "no one-click" | Done — orders/logout v1.28.0.217; inventory + network saves v1.29.0.219 |
| 2 | R1 01:41 | Show the expected cash before staff type the count | Done (closing screen shows it) |
| 3 | R2 00:00–01:55 | Owner can tell if the staff's cash count is honest; shortage notifies owner automatically | Partial — shortage email exists; "Use Expected Amount" button lets staff skip counting |
| 4 | R1 40:23–43:18 | Opening cash must be verified by a second person (owner, or the next shift verifies the handover) — "you can't verify your own transaction" | To do |
| 5 | R1 18:20, 52:09 | X-Read (mid-shift) and Z-Read (end of day) reports, standard POS functions | Partial — Z-Reads/Shift Audits page exists; no X-Read |
| 6 | R1 25:10–27:19 | Void should be approved on the spot (owner/admin verifies at the register), not left pending until the owner arrives | To do — reverses the v1 void-approval queue |
| 7 | R1 27:33–28:27 | Return / refund transaction | To do |
| 8 | R1 28:35–31:13 | Sales report by day, date range and month; best sellers per month/season | Done v1.30.0.231 (Reports → Sales) |
| 9 | R1 21:54–25:06 + owner | One person runs the shift: POS and kitchen display in one screen | Done v1.31.0.233 (Orders to make panel on the register) |
| — | R1 31:36–33:59 | Receipts / BIR, Senior & PWD discount, reprint | Question — all present |

## Inventory

| # | Where | Request | Status |
|---|---|---|---|
| 10 | R1 10:07–11:09; R2 29:15–30:20 | Record the date received and the expiration date when restocking (FIFO); system shows what expires today / next week / next month instead of hand-entered wastage | To do |
| 11 | R1 06:47–09:00 | Wastage can't exceed what one opened package holds (error trapping) | Done v1.29.0.221 |
| 12 | R1 44:00–45:15 | Wastage of partial amounts (e.g. 50 ml spilled), not whole servings | Done — wastage is entered in ml/g already |
| 13 | R1 11:17–13:05, 34:10–40:00 | Receive Deliveries: enter the PO reference number → checklist of expected items; add the supplier's own DR/SI number field; pick the supplier instead of retyping | To do — staff currently type supplier and items by hand |
| 14 | R2 31:50–33:14 | Activity Log grouped by category (security vs inventory), date filter; stock / daily / weekly reports | Partial — stock report done v1.30.0.232; Activity Log grouping to do |

## Wi-Fi and network

| # | Where | Request | Status |
|---|---|---|---|
| 15 | R1 02:54–06:22 | Show the actual measured internet speed next to the plan-speed settings, an estimate of how many users fit, and keep bandwidth reserved for the shop | Done v1.31.0.234 (Your internet line card) |
| 16 | R1 13:09–15:25 | One shareable code with a user limit instead of one code per person (database efficiency) | To do — reverses one-code-per-device |
| 17 | R2 20:18–21:08 | Wrong voucher code: block automatically after 3 tries | Done v1.29.0.220 (5-minute block) |
| — | R1 50:35 | "Unnamed device" in the device list | Question — dual-NIC ports; label them |

## Accounts and security

| # | Where | Request | Status |
|---|---|---|---|
| 18 | R2 10:05–11:28 | "Archive" users, not "Remove"; restore later | Done v1.29.0.222 (never hard-deleted now) |
| 19 | R2 18:35–19:08 | Lock sign-in after 3 wrong tries, not 5 | Done v1.29.0.218 |
| 20 | R2 24:07–25:04 | AI pending-actions icon looks like the chatbot; use a robot-with-cog icon | Done v1.29.0.223 |
| 21 | R1 45:58–49:34 | Backups more often than nightly (hourly or every 30 min while open), auto-delete the oldest | Done v1.29.0.224 (hourly, keep 7 days) |
| 22 | owner | Show OpenRouter balance and daily free-request limit | Done v1.29.0.223 |
| — | R2 02:34–06:13, 05:05, 07:15, 22:46, 25:17, 26:13 | Single point of failure / nmap, HTTPS, offline use, cron frequency, token limits | Question |
