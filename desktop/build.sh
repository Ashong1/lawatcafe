#!/usr/bin/env bash
# Builds dist/LawatKape-linux.AppImage and dist/LawatKape-Setup.exe (a Windows
# setup wizard). --publish copies them to storage/app/desktop/, where
# Profile's download buttons serve them.
set -euo pipefail
cd "$(dirname "$0")"
npm install --no-audit --no-fund
# makensis aborts under a non-UTF-8 locale ("main argv conversion failed").
export LANG=C.UTF-8 LC_ALL=C.UTF-8
npx electron-builder --linux AppImage --win dir --x64
# Our own installer.nsi instead of electron-builder's NSIS target, which
# needs 32-bit Wine to pre-build the uninstaller.
# shortcut: reuses the NSIS toolkit electron-builder caches; on a fresh machine run
# `npx electron-builder --win nsis` once (it fails without Wine, but downloads it).
NSIS=$(ls -d "$HOME"/.cache/electron-builder/nsis/nsis-3*/ 2>/dev/null | head -1)
[ -n "$NSIS" ] || { echo "NSIS toolkit missing: run 'npx electron-builder --win nsis' once in desktop/ first." >&2; exit 1; }
NSISDIR="$NSIS" "$NSIS/linux/makensis" -V2 -DVERSION="$(node -p "require('./package.json').version")" installer.nsi
if [ "${1:-}" = "--publish" ]; then
    mkdir -p ../storage/app/desktop
    cp dist/LawatKape-linux.AppImage dist/LawatKape-Setup.exe ../storage/app/desktop/
    chown -R www-data:www-data ../storage/app/desktop
    echo "Published to storage/app/desktop/"
fi
