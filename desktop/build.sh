#!/usr/bin/env bash
# Builds dist/LawatKape-linux.AppImage and dist/LawatKape-win.exe (portable:
# the NSIS installer needs Wine to build on Linux). --publish copies them to
# storage/app/desktop/, where Profile's download buttons serve them.
set -euo pipefail
cd "$(dirname "$0")"
npm install --no-audit --no-fund
# makensis aborts under a non-UTF-8 locale ("main argv conversion failed").
LANG=C.UTF-8 LC_ALL=C.UTF-8 npx electron-builder --linux AppImage --win portable --x64
if [ "${1:-}" = "--publish" ]; then
    mkdir -p ../storage/app/desktop
    cp dist/LawatKape-linux.AppImage dist/LawatKape-win.exe ../storage/app/desktop/
    chown -R www-data:www-data ../storage/app/desktop
    echo "Published to storage/app/desktop/"
fi
