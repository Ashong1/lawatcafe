# Chapter 1 — The Problem and Its Background

Material for writing Chapter 1. Everything stated as fact here is true of the
built system (v1.25.0, October 2026) and can be checked in the code or the
other docs. Parts marked **[Fill in]** need information only the group has
(school, client interview, dates).

Suggested structure, as most BSIT capstone templates use it:

1. Introduction / Background of the Study
2. Statement of the Problem
3. Objectives of the Study (general and specific)
4. Significance of the Study
5. Scope and Delimitations
6. Definition of Terms

---

## 1.1 Background of the study

### The setting

Lawa't Kape is a small coffee shop that sells drinks and food and offers
**paid guest Wi-Fi**. Before this project, those were two unrelated jobs:

- Orders, cash, stock and staff shifts were handled at a register (or on paper).
- The Wi-Fi was a router with a password, or vouchers managed by hand on a
  separate network device. Nobody could easily tell whether Wi-Fi codes
  matched sales, who was online, or whether a device was abusing the
  connection.

**[Fill in]** from the client interview: how the shop operated before, how
many staff, typical daily customers, what the owner found hardest.

### What the project built

A single web system, **Lawa't Kape POS and Captive Portal**, that joins three
parts:

| Part | What it does | Reference |
|---|---|---|
| **Point of sale (POS)** | Register, kitchen display, shifts and cash, end-of-day (Z-read), inventory with recipes, suppliers and purchase orders, sales reports, cash and e-wallet (GCash, Maya, QR Ph) payments | [POS_FLOW.md](../POS_FLOW.md), [FEATURES.md](../FEATURES.md) |
| **Network administration and captive portal** | Guest Wi-Fi sign-in with codes sold at the register, speed plans, who's online, blocking devices and websites, trusted devices, network health monitoring, offline operation | [CAPTIVE_PORTAL.md](../CAPTIVE_PORTAL.md), [INFRASTRUCTURE.md](../INFRASTRUCTURE.md) |
| **AI agent ("Barista AI")** | A chat assistant that can **act** through permission-checked tools (32 for the system administrator, 26 for the owner, 15 for staff, 2 for guests), plus scheduled analysis that looks across sales, Wi-Fi and stock together | [AI_AGENT.md](../AI_AGENT.md) |

The central idea (the thesis of the project): **because POS and network data
live in one system, an AI agent can work across both**. For example, it can
notice that Wi-Fi codes are being used faster than sales explain, which
neither a standalone POS nor a standalone hotspot could detect. See
[ARCHITECTURE.md — The thesis](../ARCHITECTURE.md#the-thesis).

Per the advisers' revisions (September 2026), **network administration is the
primary focus** and the POS is the business context it serves: the AI's first
responsibility is Wi-Fi, bandwidth, device access and portal security.

### Technology at a glance (for the background paragraph)

Laravel 12 (PHP 8.2) web application with a MariaDB database, running on a
Proxmox server. It controls an **OPNsense** firewall (captive portal, DHCP,
traffic shaping) and a **Pi-hole** DNS filter through their APIs, and uses
**OpenRouter** for the AI models. There is also an Android app for the shop's phone.
Full list in Chapter 3 ([CHAPTER_3_METHODOLOGY.md](CHAPTER_3_METHODOLOGY.md#33-tools-and-technologies)).

---

## 1.2 Statement of the problem

**General problem:** How can a small coffee shop run its sales and its paid
guest Wi-Fi as one system, managed by non-technical people, with an AI agent
that helps run both?

**Specific problems** (each one is answered by a module in Chapter 4). Adjust
the wording to what the client actually reported:

1. Sales, cash and inventory are recorded by hand or in separate tools, so
   stock runs out without warning and cash shortages are hard to trace.
2. Guest Wi-Fi is not tied to purchases: there is no control over who uses
   it, for how long, or how fast, and no record linking Wi-Fi codes to sales.
3. The owner and staff are not technical; managing a firewall, DHCP, blocked
   sites and bandwidth by hand is beyond them.
4. Abuse (a device reusing codes, bandwidth hogging, adult or unwanted sites)
   is noticed late or not at all.
5. The shop must keep working during an internet or power outage, when
   cloud-only tools stop.
6. Customers increasingly pay by GCash or Maya, and those payments must be
   recorded apart from the cash drawer.

---

## 1.3 Objectives of the study

### General objective

To design, develop and evaluate an integrated point-of-sale and captive-portal
network management system for Lawa't Kape, with an AI agent that operates
across both.

### Specific objectives

Each objective lists the evidence that it was met (use in Chapter 4).

| # | Objective: to develop a module that… | Evidence in the system |
|---|---|---|
| 1 | records orders at a register, sends them to a kitchen display, and accepts cash and e-wallet payments | Register, KDS, kitchen slip, e-wallet QR; `PosCheckoutTest`, `EwalletPaymentTest`, `KitchenSlipTest` |
| 2 | manages inventory through recipes, so each sale deducts ingredients and low stock is flagged | Ingredients, recipes, inventory logs, purchase orders; inventory tests |
| 3 | manages cashier shifts, cash in/out and end-of-day reconciliation | Shifts, closing report, Z-reads, shortage audit email |
| 4 | sells Wi-Fi access as codes at the register and admits guests through a captive portal | Vouchers, portal (English/Filipino), OPNsense integration; portal tests |
| 5 | lets non-technical users manage the network: who's online, speed plans, blocking devices and websites, trusted devices | Network pages, Pi-hole site blocking, Trusted Devices; network tests |
| 6 | monitors network health and keeps the shop running without internet | `network:health` every minute, Network Status page, offline mode |
| 7 | provides an AI agent that answers questions and performs permitted actions across POS and network, with approval and audit | Barista AI, tool registry, action audit, approvals, learning loop; AI tests |
| 8 | secures the system by role (staff, owner, system administrator), protects personal data, and keeps an audit trail | Roles, encryption of MAC addresses, audits, invite-only accounts |
| 9 | evaluates the system's software quality using ISO/IEC 25010 | Chapter 3 instrument, Chapter 4 results **[Fill in after survey]** |

---

## 1.4 Significance of the study

- **Coffee shop owner:** one place to see sales, stock, cash and the Wi-Fi.
  The AI handles technical tasks (block a device, make Wi-Fi codes, check
  network health) in plain language. Wi-Fi becomes a sales tool (free Wi-Fi
  above a set order amount) instead of a cost.
- **Staff / baristas:** a fast register on a phone or tablet, a kitchen
  display with waiting-order reminders, and Wi-Fi codes printed or shown
  right after payment.
- **Customers / guests:** Wi-Fi that works on their phone with a code, a
  bilingual sign-in page, a digital menu, and fair speeds.
- **Small businesses in the Philippines:** an example of affordable,
  self-hosted tools (open-source firewall, DNS filter) replacing several paid
  services.
- **The academic community / future researchers:** a documented example of
  an AI agent with tool permissions, human approval and an audit trail, and of
  joining POS data with network data.
- **The researchers:** **[Fill in]**.

---

## 1.5 Scope and delimitations

### Scope (what the system covers)

- **Users and roles:** staff (barista), admin (owner), super admin (system
  administrator, the developers), and guests (Wi-Fi customers, no account).
- **POS:** register; dine-in/take-away; discounts (Senior/PWD 20%); cash,
  GCash, Maya and QR Ph payments with reference numbers; kitchen display;
  kitchen order slip; order history and voids (staff voids need owner
  approval); shifts, cash in/out, closing report, Z-reads; sales reports and
  CSV export; product photos.
- **Inventory:** ingredients with packaging units, recipes, automatic
  deduction, low-stock alerts, wastage, suppliers, AI-drafted purchase orders,
  deliveries.
- **Network:** captive portal with Wi-Fi codes (sold at the register or made in
  batches), plan tiers and speed caps, free Wi-Fi above a minimum order,
  auto-reconnect until a code expires, who's online, disconnect/block/trust,
  website blocking via Pi-hole, adult-site alerts, fixed IP reservations,
  network health checks every minute, offline operation.
- **AI agent:** chat for each audience; tools that act with permission tiers;
  approval for sensitive actions; audit of every action; scheduled
  cross-domain analysis; sales forecast; learning from feedback (approved
  lessons only).
- **Platforms:** web browser (desktop, tablet, phone) and an Android app
  (WebView) for the shop phone.

### Delimitations (what it does not do, and why)

Every item here is a real, deliberate limit of the built system:

| Limit | Reason |
|---|---|
| Works on the shop's network only; no access from home | The server is on the shop LAN; remote access would need a VPN, which is not set up |
| Customer receipts are not printed until the POS is BIR-registered; only kitchen slips print | A machine that issues receipts/invoices must be registered with the BIR |
| E-wallet payments are not confirmed automatically | The shop uses its own receive QR, not a merchant account with an API; the cashier records the reference number |
| No alerts on a locked phone | Push notifications need an internet push service (Firebase) |
| The guest portal runs over plain HTTP | Captive portals must answer before the guest has internet; certificate checks would fail |
| Speed plans (free vs premium) are enforced per device IP on this OPNsense build | Firmware limits; see INFRASTRUCTURE.md |
| AI uses free OpenRouter models with a daily cap (about 50 requests/day) | Cost; the system falls back gracefully when the cap is reached |
| The AI cannot change its own code | Deliberate safety choice; it can only use defined tools |
| One shop, one branch | Multi-branch was not required |
| Android only (no iOS app); iPhones use the browser | The client uses an Android phone |

---

## 1.6 Definition of terms

Define conceptually (general meaning) and operationally (how this study uses
it). Operational meanings below match the system.

| Term | Operational definition |
|---|---|
| **Point of Sale (POS)** | The register module where staff enter orders and take payment. |
| **Captive portal** | The sign-in page a Wi-Fi guest sees before getting internet; here, it accepts a Wi-Fi code. |
| **Wi-Fi code (voucher)** | A short code (e.g. `LAWA-AB12C`) that gives a device internet for a set time. Sold at the register or made in batches. |
| **Plan / tier** | The speed level of a code (free or premium), each with its own speed cap. |
| **OPNsense** | Open-source firewall/router that runs the captive portal, DHCP and traffic shaping; the system controls it through its API. |
| **Pi-hole** | A DNS filter used to block websites for guests. |
| **DHCP / Kea** | The service that gives devices their IP addresses; Kea is OPNsense's DHCP server. |
| **IP address** | A device's address on the network (e.g. 192.168.2.130). |
| **MAC address** | A device's hardware ID on the network; stored encrypted in this system. |
| **Trusted device** | A device (e.g. the owner's laptop) allowed online without a code. |
| **Bandwidth / fair-use ceiling** | The speed limit per device so one guest can't slow everyone. |
| **KDS (Kitchen Display System)** | The screen where orders appear for preparation. |
| **Kitchen order slip** | A printed kitchen copy of an order with no prices; not a receipt. |
| **Shift** | One cashier's working period, from starting cash to closing count. |
| **Z-read / End of Day** | The end-of-shift report comparing expected and counted cash. |
| **Void** | Cancelling a recorded sale; staff voids need owner approval. |
| **E-wallet / QR Ph** | Mobile payment (GCash, Maya); QR Ph is the national QR standard that any bank or e-wallet app can pay. |
| **Reference number** | The transaction number on the customer's e-wallet "sent" screen, recorded as proof of payment. |
| **AI agent (Barista AI)** | The assistant that answers in chat and can perform actions through tools. |
| **Tool (AI tool)** | One action the AI is allowed to perform, e.g. "make Wi-Fi codes" or "block a device". |
| **Permission tier** | Whether a tool runs immediately, needs confirmation, or needs an owner's approval. |
| **Audit trail** | The stored record of every AI action: who asked, inputs, result. |
| **Large Language Model (LLM)** | The kind of AI model (via OpenRouter) that reads and writes text. |
| **OpenRouter** | The service that gives access to several AI models through one API. |
| **Proxmox** | The virtualization platform the servers run on. |
| **WebView app** | The Android app that shows the web system full screen. |
| **Super admin / Admin / Staff** | System administrator (developers) / owner / barista. |
