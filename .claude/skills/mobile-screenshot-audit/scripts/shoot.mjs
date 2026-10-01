// Phone screenshots + mobile checks for Lawa't Kape pages, as the Android app sees them.
// Usage (via shoot.sh, which creates and deletes the account):
//   node shoot.mjs --base http://192.168.2.100 --email x --password y --out DIR --pages "pos,kds" [--width 412] [--height 915] [--browser]
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';

const require = createRequire('/opt/lawatkape-tools/');
const { chromium } = require('playwright');

const args = Object.fromEntries(process.argv.slice(2).reduce((acc, a, i, all) => {
    if (a.startsWith('--')) acc.push([a.slice(2), all[i + 1] && !all[i + 1].startsWith('--') ? all[i + 1] : true]);
    return acc;
}, []));

const base = args.base || 'http://192.168.2.100';
const width = Number(args.width || 412);
const height = Number(args.height || 915);
const out = args.out || '.';
const pages = String(args.pages || 'dashboard').split(',').map(p => p.trim()).filter(Boolean);
// The Android app's WebView identity, so pages render their in-app variant.
const ua = args.browser
    ? 'Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36'
    : 'Mozilla/5.0 (Linux; Android 14; Pixel 7; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/126.0 Mobile Safari/537.36 LawatKapeApp/1.0';

fs.mkdirSync(out, { recursive: true });
const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width, height }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, userAgent: ua });
const page = await context.newPage();
// The app bridge exists inside the app; pages branch on it.
if (!args.browser) {
    await page.addInitScript(() => {
        window.LawatKapeApp = { isApp: () => true, print() {}, vibrate() {}, retry() {}, changeServer() {}, serverUrl: () => location.origin };
    });
}

if (args.email) {
    await page.goto(`${base}/login`, { waitUntil: 'networkidle' });
    await page.fill('input[name="email"]', args.email);
    await page.fill('input[name="password"]', args.password);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button[type="submit"]')]);
}

const report = {};
for (const p of pages) {
    const slug = p.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'root';
    const res = await page.goto(`${base}/${p.replace(/^\//, '')}`, { waitUntil: 'networkidle' }).catch(e => null);
    await page.waitForTimeout(900);
    // --eval "<js>": a step to run before the shots, e.g. open a dialog or add
    // items to the register's cart. Runs in the page; may return a promise.
    if (args.eval) {
        await page.evaluate(code => new Function(code)(), String(args.eval)).catch(e => console.error(`eval failed on ${p}: ${e.message}`));
        await page.waitForTimeout(900);
    }
    await page.screenshot({ path: path.join(out, `${slug}.png`) });
    // The admin/staff layouts scroll inside <main>, not the window, so a
    // "full page" shot misses most of the page: scroll the real scroller one
    // screen at a time instead (up to 6 screens).
    const screens = await page.evaluate(() => {
        const m = document.querySelector('main');
        const el = m && m.scrollHeight > m.clientHeight + 4 ? m : document.scrollingElement;
        window.__lkScroller = el;
        return Math.min(6, Math.ceil(el.scrollHeight / el.clientHeight));
    });
    for (let i = 1; i < screens; i++) {
        await page.evaluate(i => { const el = window.__lkScroller; el.scrollTop = i * el.clientHeight * 0.9; }, i);
        await page.waitForTimeout(250);
        await page.screenshot({ path: path.join(out, `${slug}_${i + 1}.png`) });
    }
    await page.evaluate(() => { window.__lkScroller.scrollTop = 0; });

    report[p] = await page.evaluate(() => {
        const vw = window.innerWidth;
        // On screen: the phone's off-canvas menu sits left of the viewport.
        const visible = el => {
            const r = el.getBoundingClientRect();
            const s = getComputedStyle(el);
            return r.width > 0 && r.height > 0 && r.right > 0 && r.left < vw
                && s.visibility !== 'hidden' && s.display !== 'none' && s.opacity !== '0';
        };
        const label = el => (el.getAttribute('aria-label') || el.innerText || el.value || el.name || el.tagName).trim().replace(/\s+/g, ' ').slice(0, 40);
        const small = [...document.querySelectorAll('a, button, input:not([type=hidden]), select, textarea, [role=button]')]
            .filter(visible)
            .map(el => ({ el, r: el.getBoundingClientRect() }))
            .filter(({ r }) => r.width < 44 || r.height < 44)
            .filter(({ el }) => !el.closest('[aria-hidden="true"]'))
            .map(({ el, r }) => `${label(el)} (${Math.round(r.width)}x${Math.round(r.height)})`);
        // Content past the right edge, even where a parent hides the overflow
        // (the page can't scroll to it, so it is simply cut off). Sideways
        // scrolling strips (overflow-x-auto) are intentional and excluded.
        const inScroller = el => { for (let p = el.parentElement; p; p = p.parentElement) { const o = getComputedStyle(p).overflowX; if (o === 'auto' || o === 'scroll') return true; } return false; };
        const wide = [...document.querySelectorAll('body *')]
            .filter(el => { const r = el.getBoundingClientRect(); const st = getComputedStyle(el); return r.width > 0 && r.height > 0 && st.display !== 'none' && st.visibility !== 'hidden' && r.left < vw && r.left >= 0; })
            .filter(el => el.getBoundingClientRect().right > vw + 1 && !inScroller(el) && !el.closest('[aria-hidden="true"], [x-cloak]'))
            // Empty decorative shapes (background circles) are clipped on purpose.
            .filter(el => el.innerText.trim() !== '' || el.querySelector('a, button, input, select, textarea, img'))
            .slice(0, 8).map(el => `${el.tagName.toLowerCase()}.${[...el.classList].slice(0, 3).join('.')} → ${Math.round(el.getBoundingClientRect().right)}px`);
        const tiny = [...document.querySelectorAll('body *')].filter(visible)
            .filter(el => [...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim().length > 1))
            .filter(el => parseFloat(getComputedStyle(el).fontSize) < 12)
            .slice(0, 8).map(el => `"${el.innerText.trim().slice(0, 30)}" ${getComputedStyle(el).fontSize}`);
        const smallInputs = [...document.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea')]
            .filter(visible).filter(el => parseFloat(getComputedStyle(el).fontSize) < 16)
            .map(el => `${el.name || el.id || el.type} ${getComputedStyle(el).fontSize}`);
        return {
            title: document.title,
            horizontalScroll: document.documentElement.scrollWidth > vw + 1 ? `${document.documentElement.scrollWidth}px > ${vw}px` : false,
            pageHeight: document.documentElement.scrollHeight,
            smallTouchTargets: small.length, smallTouchTargetExamples: small.slice(0, 10),
            overflowingElements: wide, tinyText: tiny, inputsUnder16px: smallInputs.slice(0, 6),
        };
    });
    report[p].status = res ? res.status() : 'failed';
    report[p].finalUrl = page.url().replace(base, '');
}

fs.writeFileSync(path.join(out, 'report.json'), JSON.stringify(report, null, 2));
await browser.close();
console.log(JSON.stringify(Object.fromEntries(Object.entries(report).map(([k, v]) => [k, {
    status: v.status, url: v.finalUrl, hScroll: v.horizontalScroll, cutOff: v.overflowingElements.length, smallTargets: v.smallTouchTargets, tinyText: v.tinyText.length, smallInputs: v.inputsUnder16px.length,
}])), null, 1));
