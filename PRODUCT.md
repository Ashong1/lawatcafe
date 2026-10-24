# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

The Android app (`android/`, docs/ANDROID_APP.md) is a WebView over the same pages, so it is still `web`.

## Users

The owner asked that no group be favored over the others. Every screen is designed for whoever actually uses it:

- **Owner (`admin`)**: runs the cafe and its guest Wi-Fi and is not technical. Checks sales, stock, who is online and network health, often on the shop phone. Needs plain language and no jargon or internal names.
- **Staff / cashier (`staff`)**: works the register, kitchen display, shifts and cash count behind a counter during a rush. Needs speed and big targets for one hand.
- **Guests**: cafe customers on their own phones (often low-end Android) using the captive Wi-Fi portal in English or Filipino. They have **no internet before signing in**.
- **System administrator (`super_admin`)**: the developer or maintainer. Sees technical screens (jobs, AI models, system tools) and cannot use the register.

## Product Purpose

One system for a small coffee shop that joins the point of sale, the paid guest Wi-Fi (captive portal, speed plans, device and site blocking, network health) and an AI agent ("Barista AI") that can act through permission-checked tools across both. It succeeds when non-technical people can run sales and Wi-Fi without a technician.

## Positioning

POS data and network data live in one system, so the AI agent can reason across both. For example, it can notice that Wi-Fi codes are being used faster than sales explain. A standalone POS or standalone hotspot cannot do this. Following the advisers' revisions (September 2026), network administration is the primary focus and the POS is the business context it serves.

## Operating Context

- A small cafe in the Philippines. Cash and e-wallet (GCash, Maya, QR Ph via a static shop QR) payments. Wi-Fi codes are sold at the register.
- Laravel 12 server on Proxmox controlling an OPNsense firewall (captive portal, DHCP, shaping) and Pi-hole. AI runs through OpenRouter on free models with a daily cap.
- Runs offline when the internet is down. The portal is HTTP-only.
- This is a BSIT capstone, so the system is demonstrated to a panel. Screens must look credible and finished.

## Capabilities and Constraints

- Portal pages load **zero external assets** (no CDNs or web fonts from the internet).
- Printed receipts stay off until BIR registration. A kitchen slip still prints.
- The minimum text size is 12px (`UiReadabilityFloorTest`). Touch targets are 44px on coarse pointers.
- Owner vocabulary is kept consistent (see project memory "plain-language owner UX pass"). Domain words such as Z-Read, the 86 list and the Premium tier name are real vocabulary, not slop.
- SweetAlert2 handles all alerts and confirmations (AGENTS.md).
- Stack: Blade + Tailwind + Alpine and Vite.

## Brand Commitments

- Name: **Lawa't Kape**. AI assistant: **Barista AI**.
- The current typeface stays (the owner confirmed this on 2026-10-24).

## Evidence on Hand

Real system documentation lives in `docs/` and the capstone chapters in `docs/capstone/`. There are no customer testimonials, usage statistics or survey results yet. Do not invent them.

## Product Principles

1. Say what the thing does in the reader's words. No buzzwords, code names or decorative emoji.
2. Network first: Wi-Fi health and guest access are the headline, and sales are the context.
3. Fast under pressure: the register and kitchen screens favor speed over decoration.
4. Works with nothing: offline, no internet before sign-in, cheap phones.
5. Finished, not flashy: credible for a panel demo, and precise rather than decorated.

## Accessibility & Inclusion

Users are not technical. The interface is bilingual on the portal (English/Filipino), readable at arm's length, and usable one-handed on a phone.
