#!/bin/bash
# Undo linux-after-install.sh.
rm -f '/usr/bin/lawatkape-desktop'
if [ -f /etc/apparmor.d/lawatkape-desktop ]; then
    hash apparmor_parser 2>/dev/null && apparmor_parser -R /etc/apparmor.d/lawatkape-desktop 2>/dev/null || true
    rm -f /etc/apparmor.d/lawatkape-desktop
fi
