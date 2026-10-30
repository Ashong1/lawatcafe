#!/bin/bash
# electron-builder's after-install, plus an AppArmor profile: Ubuntu 24.04+
# blocks the user namespaces Electron's sandbox needs unless the app has one
# (Chrome and VS Code ship the same), and the stock check below runs as root,
# where namespaces work, so it never fell back to the SUID helper.

APP='/opt/Lawat Kape'

if type update-alternatives 2>/dev/null >&1; then
    if [ -L '/usr/bin/lawatkape-desktop' -a -e '/usr/bin/lawatkape-desktop' -a "`readlink '/usr/bin/lawatkape-desktop'`" != '/etc/alternatives/lawatkape-desktop' ]; then
        rm -f '/usr/bin/lawatkape-desktop'
    fi
    update-alternatives --install '/usr/bin/lawatkape-desktop' 'lawatkape-desktop' "$APP/lawatkape-desktop" 100 || ln -sf "$APP/lawatkape-desktop" '/usr/bin/lawatkape-desktop'
else
    ln -sf "$APP/lawatkape-desktop" '/usr/bin/lawatkape-desktop'
fi

profile_ok=false
if [ -d /etc/apparmor.d ] && [ -e /etc/apparmor.d/abi/4.0 ] && hash apparmor_parser 2>/dev/null; then
    cat > /etc/apparmor.d/lawatkape-desktop <<PROFILE
abi <abi/4.0>,
include <tunables/global>

profile lawatkape-desktop "$APP/lawatkape-desktop" flags=(unconfined) {
  userns,
  include if exists <local/lawatkape-desktop>
}
PROFILE
    apparmor_parser -r /etc/apparmor.d/lawatkape-desktop && profile_ok=true
fi

if $profile_ok || { [[ -L /proc/self/ns/user ]] && [ "$(cat /proc/sys/kernel/apparmor_restrict_unprivileged_userns 2>/dev/null || echo 0)" != 1 ]; }; then
    chmod 0755 "$APP/chrome-sandbox" || true
else
    # No usable namespaces for a normal user: fall back to the SUID helper.
    chmod 4755 "$APP/chrome-sandbox" || true
fi

if hash update-mime-database 2>/dev/null; then
    update-mime-database /usr/share/mime || true
fi

if hash update-desktop-database 2>/dev/null; then
    update-desktop-database /usr/share/applications || true
fi
