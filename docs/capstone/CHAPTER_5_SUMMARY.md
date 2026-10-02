# Chapter 5 — Summary, Conclusions and Recommendations

Chapter 5 restates the study and draws conclusions from Chapter 4's results.
The conclusions **must follow from the evaluation numbers**, so the drafts
below have blanks for them. Write this chapter after Chapter 4 is final.

---

## 5.1 Summary

One paragraph per item, in past tense:

1. **The problem:** a small café ran sales and paid guest Wi-Fi as separate,
   manual jobs, managed by non-technical people (Chapter 1).
2. **What was done:** the researchers developed *Lawa't Kape POS and Captive
   Portal*, a Laravel 12 web system and Android app that joins a point of sale,
   captive-portal network administration (OPNsense, Pi-hole) and an AI agent
   with permission-controlled tools. It was built iteratively from May to
   October 2026 (197 builds) and deployed on the shop's own server.
3. **How it was tested:** 1,111 automated tests (all passing), end-to-end
   checks on the live system, and user acceptance testing with the owner and
   staff **[Fill in dates]**.
4. **How it was evaluated:** ISO/IEC 25010 survey of **[N]** respondents
   (owner, staff, IT experts, customers), using the weighted mean.

### Summary of findings

One finding per specific objective, each with its number:

| Objective | Finding |
|---|---|
| 1 Register, kitchen, payments | **[e.g. Functional suitability WM = __ (Excellent)]** |
| 2 Inventory | |
| 3 Shifts / end of day | |
| 4 Wi-Fi codes and portal | |
| 5 Network management | |
| 6 Monitoring / offline | |
| 7 AI agent | |
| 8 Security | |
| Overall software quality | Grand mean **[ ]** — **[interpretation]** |

---

## 5.2 Conclusions

Draft. Keep only what the results support:

1. The integrated system met its objectives: it records sales and payments,
   manages inventory by recipe, and controls guest Wi-Fi access from one
   application, as shown by the 1,111 passing functional tests and a
   functional-suitability rating of **[ ]**.
2. Joining POS and network data in one system made checks possible that
   separate systems can't make, such as comparing Wi-Fi code use with sales.
3. An AI agent can safely perform real network and business actions when
   each action has a permission tier, sensitive actions need human approval,
   and everything is recorded in an audit trail.
4. Non-technical users could manage the network through plain-language pages
   and the AI (usability WM **[ ]**).
5. The self-hosted design kept the shop running during internet outages
   (reliability WM **[ ]**).
6. Overall, respondents rated the system **[interpretation]** (grand mean
   **[ ]**) under ISO/IEC 25010.

---

## 5.3 Recommendations

These come from the system's real, documented limits (Chapter 1
delimitations) and open items. Order them by value to the client:

**For the shop (client)**
1. Register the POS with the BIR, then turn on customer receipt printing
   (super admin switch in Store Settings).
2. Move to a GCash/Maya **merchant** account with payment notifications, so
   e-wallet payments are confirmed automatically instead of by reference
   number.
3. Keep a backup of the server and of the Android app's signing key
   (`/opt/lawatkape-android`). Without the key, the app can't be updated.
4. Use a paid AI plan if the free daily limit (about 50 requests) is reached
   often.
5. Put the servers on a UPS so short power cuts don't restart the network.

**For future developers / researchers**
1. A VPN (e.g. WireGuard on OPNsense) so the owner can check the shop from home.
2. Push notifications (Firebase) so waiting-order and network alerts reach a
   locked phone.
3. An iOS app, or a progressive web app for iPhones.
4. Multi-branch support (several shops, one owner account).
5. Loyalty points tied to Wi-Fi sign-in (the portal already knows returning
   devices).
6. Hardware integration: cash drawer, barcode scanner, kitchen printer
   auto-print.
7. Speed plans enforced by plan group instead of per device IP, where the
   firewall version allows it.
8. A longer evaluation (several months of real sales) to measure business
   impact: Wi-Fi revenue, stock-outs avoided, cash shortages.
