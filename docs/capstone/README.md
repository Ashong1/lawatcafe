# Capstone documentation kit (Chapters 1–5)

This folder is for the **documentation team** writing the capstone paper for
*Lawa't Kape POS and Captive Portal with Barista AI*. It turns the technical
documentation into material organised by chapter. Each file states facts that
are true of the built system and marks with **[Fill in]** what only the group
can supply (school details, interviews, survey results).

## Start here

| Chapter | File | What's in it |
|---|---|---|
| 1 — The Problem and Its Background | [CHAPTER_1_INTRODUCTION.md](CHAPTER_1_INTRODUCTION.md) | Background, statement of the problem, general and specific objectives (each with its evidence), significance, scope and **real** delimitations, definition of terms |
| 2 — Review of Related Literature | [CHAPTER_2_RRL.md](CHAPTER_2_RRL.md) | 20 topics with search keywords, systems to compare, draft synthesis (research gap), conceptual framework (IPO diagram). No references are invented: you find and cite the real ones |
| 3 — Methodology | [CHAPTER_3_METHODOLOGY.md](CHAPTER_3_METHODOLOGY.md) | Research design, iterative development (with commit/build evidence), tools and hardware, functional and non-functional requirements, architecture, use cases, context diagram, flowcharts, testing, ISO/IEC 25010 questionnaire, weighted-mean formula, ethics |
| 4 — Results and Discussion | [CHAPTER_4_RESULTS.md](CHAPTER_4_RESULTS.md) | Screenshot list (page, role, what to show), results per objective with measured numbers, test results table, survey result tables to fill, issues found and resolved |
| 5 — Summary, Conclusions, Recommendations | [CHAPTER_5_SUMMARY.md](CHAPTER_5_SUMMARY.md) | Summary and findings templates, draft conclusions, recommendations based on the system's actual limits |

Generated from the live system (regenerate rather than edit by hand):

| File | Use for |
|---|---|
| [DATA_DICTIONARY.md](DATA_DICTIONARY.md) | Chapter 3 database design / appendix: all 30 tables, every column, type, key |
| [ERD.md](ERD.md) | Chapter 3 entity relationship diagrams (POS/inventory, network, AI) |
| [TEST_INVENTORY.md](TEST_INVENTORY.md) | Chapter 3 testing and Chapter 4 results: 1,111 tests by module, all passing |

## Key facts at a glance

As of **v1.25.0.197, 2026-10-02**:

| | |
|---|---|
| Development period | 2026-05-08 to present, 301 commits, 197 builds |
| Stack | Laravel 12.58, PHP 8.2, MariaDB 10.11, Tailwind CSS 3, Alpine.js 3, Vite 7 |
| Network | OPNsense 25.7 (portal, Kea DHCP, shaper), Pi-hole v6, Nginx Proxy Manager, Proxmox VE |
| AI | OpenRouter (free models, automatic fallback between models) |
| Clients | Web (desktop/tablet/phone) and Android app |
| Roles | Staff, Admin (owner), Super admin (system administrator), Guest |
| AI tools | 32 (super admin) · 26 (owner) · 15 (staff) · 2 (guest) |
| Database | 30 application tables, 32 foreign keys |
| Web routes | 181 |
| Automated tests | 1,111 tests, 3,821 assertions, 0 failures |
| Scheduled jobs | Network health, session enforcement, guest keepalive, adult-site watch (every minute); shaper reconcile/adapt (5 min); AI analysis (15 min); AI learning and capability gaps (hourly); forecast warm-up (3 h) |

## Rules for the paper

1. **Don't invent sources or results.** Chapter 2 needs real citations, and
   Chapter 4 needs the real survey answers. The guides leave blanks for these
   on purpose.
2. **Screenshots:** no real passwords, API keys, customer devices or the
   shop's real GCash QR. Use sample data or blur them.
3. **Numbers change** as the system is updated. Before printing, check the
   version in the system footer or `composer.json`. To refresh the test count,
   run `php artisan test` and update [TEST_INVENTORY.md](TEST_INVENTORY.md).
4. **Diagrams:** every diagram is Mermaid. Paste a code block into
   https://mermaid.live and export as PNG or SVG.

## Deeper technical sources

| Topic | Document |
|---|---|
| How the three parts fit together (the thesis) | [../ARCHITECTURE.md](../ARCHITECTURE.md) |
| Every feature by module | [../FEATURES.md](../FEATURES.md) |
| Register, kitchen, shifts, voids | [../POS_FLOW.md](../POS_FLOW.md) |
| Guest Wi-Fi sign-in, step by step | [../CAPTIVE_PORTAL.md](../CAPTIVE_PORTAL.md) |
| Barista AI: tools, permissions, audit, learning | [../AI_AGENT.md](../AI_AGENT.md) |
| Servers, IPs, firewall, bandwidth | [../INFRASTRUCTURE.md](../INFRASTRUCTURE.md) |
| Database design notes | [../DATABASE.md](../DATABASE.md) |
| Testing approach | [../TESTING.md](../TESTING.md) |
| Security review findings | [../AUDIT_FINDINGS.md](../AUDIT_FINDINGS.md) |
| Android app | [../ANDROID_APP.md](../ANDROID_APP.md) |
| Installing / running the system | [../OPERATIONS.md](../OPERATIONS.md), [../CONFIGURATION.md](../CONFIGURATION.md) |
| Version history | [../../CHANGELOG.md](../../CHANGELOG.md) |
