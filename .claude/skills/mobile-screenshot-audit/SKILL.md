---
name: mobile-screenshot-audit
description: See Lawa't Kape pages the way a phone (and the Android app) shows them — phone-sized screenshots of signed-in admin/staff pages plus automatic checks for sideways scrolling, buttons under 44px, tiny text and small form fields. Use whenever working on the mobile view, the Android app's look, "it looks wrong on my phone", responsive layout, touch targets, or before/after any UI change that should be checked on a small screen — there is no phone or emulator on this server, so this is how to actually look.
---

# Mobile screenshot audit

There is no phone, emulator or browser pane here. This skill gives you eyes:
headless Chromium at a real Android phone size, signed in with a throwaway
account, rendering pages exactly as the Lawa't Kape Android app does
(its WebView user agent and `window.LawatKapeApp` bridge are simulated).

## Run it

```bash
S=.claude/skills/mobile-screenshot-audit/scripts
$S/shoot.sh admin /tmp/claude-0/.../scratchpad/shots "dashboard,pos,kds,network/sessions"
$S/shoot.sh staff  /tmp/claude-0/.../scratchpad/shots-staff "staff-dashboard,pos"
$S/shoot.sh admin  /tmp/.../shots-browser "dashboard" --browser   # as a normal phone browser, not the app
```

- Roles: `admin`, `staff`, `super_admin`. The account (and the open shift the
  register needs) is created and **always deleted**, even on failure.
- Output per page: `<page>.png` (the first screen), then `<page>_2.png`,
  `<page>_3.png`… (the next screens, scrolling the layout's own `<main>`
  scroller, up to 6), plus `report.json`.
- Options: `--width 360 --height 800` for a small phone; `--browser` for the
  normal mobile browser instead of the app; `--eval "<js>"` to run a step
  before the shots (open a dialog, fill the cart). For the register's phone
  cart, for example:
  `--eval "const d=Alpine.\$data(document.querySelector('[x-data=\"posSystem()\"]')); d.products.filter(p=>p.type==='wifi').slice(0,2).forEach(p=>d.addToCart(p)); d.showMobileCart=true"`
- Then **look at the PNGs with Read**. The numbers find problems; only looking
  tells you whether the screen is good.

Needs Playwright + Chromium in `/opt/lawatkape-tools`
(`cd /opt/lawatkape-tools && npm i playwright && npx playwright install --with-deps chromium`).

## What report.json flags

| Field | Problem | Usual fix |
|---|---|---|
| `horizontalScroll` | The page scrolls sideways on a phone | Find the element in `overflowingElements`; add `min-w-0`, wrap, or put tables in `overflow-x-auto` |
| `smallTouchTargets` | Tappable things under 44×44 px (WCAG 2.5.5) | `min-h-[44px]` / padding; icon buttons `w-11 h-11` |
| `tinyText` | Text under 12px | The project's floor is 12px (`text-xs`) — `UiReadabilityFloorTest` |
| `inputsUnder16px` | Form fields under 16px; phones zoom in when they're focused | `text-base` on inputs at small sizes |

Not every small target is a bug: inline text links inside a sentence are
exempt from 2.5.5. Judge each.

## What to look for in the screenshots (one-handed, behind a counter)

1. **First screen**: is the main action visible without scrolling? Is the
   page title eating space a phone can't spare?
2. **Thumb reach**: the main action (Place Order, Make Codes, Save) near the
   bottom, not the top corner.
3. **Nothing covered**: the Barista AI button, the View Cart bar and the
   offline banner must not sit on a button.
4. **Tables**: wide tables either become cards on a phone or scroll inside
   their own box — never the whole page.
5. **Text**: readable at arm's length; no labels truncated into nonsense.
6. **The app**: no "download the app" prompts inside the app; nothing that
   needs a desktop (hover-only actions, right-click).

## After changing something

Re-run the same pages, compare before/after PNGs, and run the full test suite
(`sudo -u www-data php artisan config:clear && sudo -u www-data php artisan test`).
Assets changed? `npm run build && chown -R www-data:www-data public/build` first.
