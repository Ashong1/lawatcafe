// The same small bridge as the Android app: the offline page picks how to connect.
const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('LawatKapeApp', {
    connectionMode: () => ipcRenderer.sendSync('connection-mode'),
    useConnection: (mode) => ipcRenderer.send('use-connection', mode),
    retry: () => ipcRenderer.send('use-connection', ipcRenderer.sendSync('connection-mode')),
});
