// The shop system in its own window, the desktop twin of android/. Same
// rules: the trusted HTTPS address first, the local http address when that
// name can't be reached, never a certificate the computer doesn't trust.
// Connection: Automatic (shop, then the server's own address, then the remote
// link), In the shop, or From anywhere (Remote access in Settings -> Security).
const { app, BrowserWindow, shell, Menu, ipcMain } = require('electron');
const fs = require('fs');
const path = require('path');

const SERVER = 'https://lawatkape.aserotech.com';
const LOCAL_SERVER = 'http://192.168.2.100';
const REMOTE_SERVER = 'https://lawatkape-remote.aserotech.com';
const OWN_HOSTS = new Set([new URL(SERVER).host, new URL(LOCAL_SERVER).host, new URL(REMOTE_SERVER).host, 'lawatkape.lab']);
const MODES = ['auto', 'shop', 'remote'];

let onLocalFallback = false;
let onRemoteFallback = false;

const settingsFile = () => path.join(app.getPath('userData'), 'connection.json');
function mode() {
    try { const m = JSON.parse(fs.readFileSync(settingsFile(), 'utf8')).mode; return MODES.includes(m) ? m : 'auto'; } catch { return 'auto'; }
}

// The shop server's /up page, not just any device at that address on someone's home network.
async function shopAnswers() {
    try {
        const r = await fetch(LOCAL_SERVER + '/up', { signal: AbortSignal.timeout(1500) });
        return r.ok && (await r.text()).includes('Application up');
    } catch { return false; }
}

// Automatic checks the shop first (about a second): outside the shop its
// address doesn't answer, and waiting for it to time out took half a minute.
async function load(win) {
    onLocalFallback = false;
    onRemoteFallback = false;
    const m = mode();
    const remote = m === 'remote' || (m === 'auto' && !(await shopAnswers()));
    win.loadURL((remote ? REMOTE_SERVER : SERVER) + '/');
}

function createWindow() {
    const win = new BrowserWindow({
        width: 1366,
        height: 860,
        minWidth: 360,
        title: "Lawa't Kape",
        icon: path.join(__dirname, 'icon.png'),
        backgroundColor: '#FDF8F5',
        autoHideMenuBar: true,
        webPreferences: { contextIsolation: true, sandbox: true, preload: path.join(__dirname, 'preload.js') },
    });
    // Lets the system hide its own "download the app" card inside the app.
    win.webContents.setUserAgent(win.webContents.getUserAgent() + ' LawatKapeDesktop/' + app.getVersion());

    // Pages of the system (receipt, kitchen slip) open in a new app window;
    // anything else goes to the computer's own browser.
    win.webContents.setWindowOpenHandler(({ url }) => {
        if (OWN_HOSTS.has(new URL(url).host)) return { action: 'allow', overrideBrowserWindowOptions: { autoHideMenuBar: true } };
        shell.openExternal(url);
        return { action: 'deny' };
    });
    win.webContents.on('will-navigate', (e, url) => {
        const host = new URL(url).host;
        if (!OWN_HOSTS.has(host)) { e.preventDefault(); shell.openExternal(url); return; }
        if (host === new URL(SERVER).host) { onLocalFallback = false; onRemoteFallback = false; } // "Try again" starts over
    });

    win.webContents.on('did-fail-load', (e, code, desc, url, isMainFrame) => {
        if (!isMainFrame || code === -3) return; // -3: navigation replaced, not a failure
        const failed = new URL(url);
        const shopHost = failed.host === new URL(SERVER).host || failed.host === new URL(LOCAL_SERVER).host;
        if (!onLocalFallback && mode() !== 'remote' && failed.host === new URL(SERVER).host) {
            onLocalFallback = true;
            win.loadURL(LOCAL_SERVER + failed.pathname + failed.search);
        } else if (!onRemoteFallback && mode() === 'auto' && shopHost) {
            onRemoteFallback = true;
            win.loadURL(REMOTE_SERVER + failed.pathname + failed.search);
        } else {
            win.loadFile(path.join(__dirname, 'offline.html'));
        }
    });

    load(win);
    return win;
}

app.on('certificate-error', (e, wc, url, error, cert, callback) => callback(false));

function useConnection(m) {
    if (!MODES.includes(m)) return;
    fs.mkdirSync(app.getPath('userData'), { recursive: true });
    fs.writeFileSync(settingsFile(), JSON.stringify({ mode: m }));
    buildMenu();
    BrowserWindow.getAllWindows().forEach(load);
}

ipcMain.on('connection-mode', (e) => { e.returnValue = mode(); });
ipcMain.on('use-connection', (e, m) => useConnection(m));

function buildMenu() {
    const pick = (m, label) => ({ label, type: 'radio', checked: mode() === m, click: () => useConnection(m) });
    Menu.setApplicationMenu(Menu.buildFromTemplate([
        { label: 'App', submenu: [{ role: 'reload' }, { role: 'forceReload' }, { type: 'separator' }, { role: 'togglefullscreen' }, { role: 'quit' }] },
        { label: 'Edit', submenu: [{ role: 'copy' }, { role: 'paste' }, { role: 'selectAll' }] },
        { label: 'View', submenu: [{ role: 'zoomIn' }, { role: 'zoomOut' }, { role: 'resetZoom' }] },
        { label: 'Connection', submenu: [pick('auto', 'Automatic'), pick('shop', 'In the shop'), pick('remote', 'From anywhere (Remote access)')] },
    ]));
}

app.whenReady().then(() => {
    buildMenu();
    createWindow();
    app.on('activate', () => BrowserWindow.getAllWindows().length || createWindow());
});

app.on('window-all-closed', () => app.quit());

