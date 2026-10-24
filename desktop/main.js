// The shop system in its own window, the desktop twin of android/. Same
// rules: the trusted HTTPS address first, the local http address when that
// name can't be reached, never a certificate the computer doesn't trust.
const { app, BrowserWindow, shell, Menu } = require('electron');
const path = require('path');

const SERVER = 'https://lawatkape.aserotech.com';
const LOCAL_SERVER = 'http://192.168.2.100';
const OWN_HOSTS = new Set([new URL(SERVER).host, new URL(LOCAL_SERVER).host, 'lawatkape.lab']);

let onLocalFallback = false;

function createWindow() {
    const win = new BrowserWindow({
        width: 1366,
        height: 860,
        minWidth: 360,
        title: "Lawa't Kape",
        icon: path.join(__dirname, 'icon.png'),
        backgroundColor: '#FDF8F5',
        autoHideMenuBar: true,
        webPreferences: { contextIsolation: true, sandbox: true },
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
        if (host === new URL(SERVER).host) onLocalFallback = false; // "Try again" goes back to HTTPS
    });

    win.webContents.on('did-fail-load', (e, code, desc, url, isMainFrame) => {
        if (!isMainFrame || code === -3) return; // -3: navigation replaced, not a failure
        const failed = new URL(url);
        if (!onLocalFallback && failed.host === new URL(SERVER).host) {
            onLocalFallback = true;
            win.loadURL(LOCAL_SERVER + failed.pathname + failed.search);
        } else {
            win.loadFile(path.join(__dirname, 'offline.html'));
        }
    });

    win.loadURL(SERVER + '/');
    return win;
}

app.on('certificate-error', (e, wc, url, error, cert, callback) => callback(false));

app.whenReady().then(() => {
    Menu.setApplicationMenu(Menu.buildFromTemplate([
        { label: 'App', submenu: [{ role: 'reload' }, { role: 'forceReload' }, { type: 'separator' }, { role: 'togglefullscreen' }, { role: 'quit' }] },
        { label: 'Edit', submenu: [{ role: 'copy' }, { role: 'paste' }, { role: 'selectAll' }] },
        { label: 'View', submenu: [{ role: 'zoomIn' }, { role: 'zoomOut' }, { role: 'resetZoom' }] },
    ]));
    createWindow();
    app.on('activate', () => BrowserWindow.getAllWindows().length || createWindow());
});

app.on('window-all-closed', () => app.quit());

