#!/usr/bin/env bash
# Phone screenshots of signed-in pages with a throwaway account that is always deleted.
#   shoot.sh <role: admin|staff> <out-dir> <comma-separated paths> [extra shoot.mjs flags, e.g. --browser]
set -euo pipefail
ROLE="$1"; OUT="$2"; PAGES="$3"; shift 3
APP=/var/www/lawatcafe
EMAIL="shot-${ROLE}-$$@example.invalid"
PW="$(head -c 18 /dev/urandom | base64 | tr -dc A-Za-z0-9 | head -c 16)"
# Always runs: the throwaway shift first (it belongs to the account), then the account.
cleanup() { (cd "$APP" && sudo -u www-data php artisan tinker --execute="\$u=App\Models\User::where('email','$EMAIL')->first(); if(\$u){ App\Models\Shift::where('user_id',\$u->id)->delete(); \$u->delete(); }" >/dev/null 2>&1) || true; }
trap cleanup EXIT
(cd "$APP" && sudo -u www-data php artisan tinker --execute="App\Models\User::create(['name'=>'Screenshot $ROLE','email'=>'$EMAIL','password'=>Hash::make('$PW'),'role'=>'$ROLE']);" >/dev/null)
# A register needs an open shift, or the page shows the open-shift screen.
if [ "$ROLE" != "super_admin" ]; then
  (cd "$APP" && sudo -u www-data php artisan tinker --execute="\$u=App\Models\User::where('email','$EMAIL')->first(); App\Models\Shift::create(['user_id'=>\$u->id,'starting_cash'=>0,'status'=>'open','opened_at'=>now()]);" >/dev/null)
fi
node "$(dirname "$0")/shoot.mjs" --base http://192.168.2.100 --email "$EMAIL" --password "$PW" --out "$OUT" --pages "$PAGES" "$@"
