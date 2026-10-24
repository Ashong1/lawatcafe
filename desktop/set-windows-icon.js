// electron-builder normally stamps the icon into the Windows .exe with rcedit,
// which needs Wine on Linux. resedit does the same in plain JavaScript.
const fs = require('fs');
const path = require('path');
const ResEdit = require('resedit');
const PELibrary = require('pe-library');

exports.default = async (context) => {
    if (context.electronPlatformName !== 'win32') return;
    const exe = path.join(context.appOutDir, `${context.packager.appInfo.productFilename}.exe`);
    const pe = PELibrary.NtExecutable.from(fs.readFileSync(exe));
    const res = PELibrary.NtExecutableResource.from(pe);
    const ico = ResEdit.Data.IconFile.from(fs.readFileSync(path.join(__dirname, 'icon.ico')));
    ResEdit.Resource.IconGroupEntry.replaceIconsForResource(res.entries, 1, 1033, ico.icons.map((i) => i.data));
    const [vi] = ResEdit.Resource.VersionInfo.fromEntries(res.entries);
    vi.setStringValues({ lang: 1033, codepage: 1200 }, { ProductName: "Lawa't Kape", FileDescription: "Lawa't Kape", CompanyName: "Lawa't Kape" });
    vi.outputToResourceEntries(res.entries);
    res.outputResource(pe);
    fs.writeFileSync(exe, Buffer.from(pe.generate()));
};
