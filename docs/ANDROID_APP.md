# Android app

**Lawa't Kape for Android** puts the whole system on the shop phone as an
app: register, kitchen display, network pages, Barista AI and settings, full
screen. It is a small app (about 40 KB) that loads the live system from the
shop's server, so **every update to the system appears in the app without
reinstalling it**.

- [Installing it on a phone](#installing-it-on-a-phone)
- [What the app adds over the browser](#what-the-app-adds-over-the-browser)
- [Limits](#limits)
- [How it works](#how-it-works)
- [Building it](#building-it)
- [Releasing an update](#releasing-an-update)
- [The signing key](#the-signing-key)

---

## Installing it on a phone

1. Connect the phone to the shop Wi-Fi.
2. Open the system in the phone's browser (`http://192.168.2.100`), sign in,
   and go to **Profile**.
3. Tap **Download the app**, then open the downloaded `LawatKape.apk`.
4. Android asks once to allow the browser to **install unknown apps**.
   Allow it, go back, and tap **Install**. (The app isn't on the Play Store,
   so Android asks this for any app installed this way.)
5. Open **Lawa't Kape** from the home screen and sign in.

Recommended: add the phone on **Wi-Fi & Network → Trusted Devices**. The app works
without this, because the server is reachable before Wi-Fi sign-in, but
Android otherwise keeps showing "Sign in to Wi-Fi" notifications.

Works on Android 7.0 and newer.

## What the app adds over the browser

| | Browser | App |
|---|---|---|
| Full screen, no address bar | no | yes |
| Screen stays on while the app is open | no | yes |
| Order reminders vibrate | some phones | yes |
| Barista AI photos from the camera or gallery | yes | yes |
| Printing vouchers and receipts | yes | yes, through Android's print service |
| Sales export (CSV) saved to Downloads | yes | yes |
| Back button moves back through pages | yes | yes; exits on the first page |
| Clear screen when off the shop Wi-Fi | browser error | "Can't reach the shop system" with **Try again** |
| Survives the staff HTTPS certificate warning | no (warning) | not needed: uses the server's own address |

## Limits

- **Shop Wi-Fi only.** The system lives on the shop's network. Away from the
  shop the app shows the "can't reach" screen. Reaching it from home would
  need a VPN, which isn't set up.
- **No alerts while the app is closed.** Waiting-order reminders and
  notifications show while the app is open. Alerts on a locked phone would
  need a push service (Google Firebase), which depends on the internet.
- **Installed by hand**, not from the Play Store. Updates to the app
  itself, which are rare because the system updates on its own, are installed
  the same way, over the old one.
- **Not yet tried on a physical phone at the time of writing.** It was
  built and checked on the server (package, permissions, signature, and the
  download).

## How it works

`android/` holds the whole app:

| File | What it is |
|---|---|
| `AndroidManifest.xml` | Package `ph.lawatkape.app`, Android 7.0+ (API 24), targets API 34; permissions: internet, vibration, Downloads on Android 9 and older |
| `src/ph/lawatkape/app/MainActivity.java` | One screen holding a WebView, plus the phone features |
| `res/xml/network_security_config.xml` | Plain HTTP allowed **only** for the shop's own addresses (`192.168.2.100`, `*.lawatkape.lab`); everything else must be HTTPS |
| `assets/offline.html` | The "can't reach the shop system" screen, with Try again and Change server address |
| `build.sh` | Builds and signs the APK |

Details:

- **Address**: it opens `http://192.168.2.100` (changeable from the offline
  screen, saved on the phone). It doesn't use `lawatkape.lab`: that name goes
  through Nginx Proxy Manager's HTTPS, whose certificate phones don't trust.
  A link to `lawatkape.lab` is rewritten to the same page on the server.
  Links to other websites, phone numbers and email open in the phone's own
  apps.
- **Certificates**: the app never accepts a certificate the phone doesn't
  trust.
- **Bridge**: pages can call `window.LawatKapeApp` for `print()`,
  `vibrate()`, `retry()`, `changeServer()`, `serverUrl()` and `isApp()`. On
  every page the app points `window.print()` and `navigator.vibrate()` at
  these. The pages that print as soon as they open (receipt, voucher batch)
  call `LawatKapeApp.print()` themselves, and the Vouchers page opens batch
  print in place instead of a new tab.
- **Recognising the app**: its user agent ends in `LawatKapeApp/<version>`.
  The Profile page hides the download card inside the app.
- **Download**: `GET /app/android` (signed-in accounts only,
  `AndroidAppController`) serves the APK from `storage/app/android/`, never
  from `public/`.

## Building it

One-time setup on the server (about 1 GB):

```bash
apt install openjdk-17-jdk-headless librsvg2-bin unzip zip
mkdir -p /opt/android-sdk/cmdline-tools && cd /tmp
curl -sSLO https://dl.google.com/android/repository/commandlinetools-linux-11076708_latest.zip
unzip -q commandlinetools-linux-*_latest.zip -d /opt/android-sdk/cmdline-tools
mv /opt/android-sdk/cmdline-tools/cmdline-tools /opt/android-sdk/cmdline-tools/latest
yes | /opt/android-sdk/cmdline-tools/latest/bin/sdkmanager --licenses
/opt/android-sdk/cmdline-tools/latest/bin/sdkmanager "platforms;android-34" "build-tools;34.0.0"
```

Then build:

```bash
cd /var/www/lawatcafe
android/build.sh             # → android/build/LawatKape.apk
android/build.sh --publish   # also make it downloadable from Profile
```

The build uses the SDK's own tools directly (aapt2, javac, d8, zipalign,
apksigner), with no Gradle and no third-party libraries, and makes the
launcher icon from `public/favicon.svg`. `android/build/` is git-ignored.

## Releasing an update

Only needed when the **app** changes (`android/`), not when the system
changes.

```bash
VERSION_CODE=2 VERSION_NAME=1.1 android/build.sh --publish
```

`VERSION_CODE` must be higher than the installed one. Installing the new APK
over the old one keeps the sign-in.

## The signing key

The first build creates the key in `/opt/lawatkape-android/` (`release.jks`
and its password in `keystore.pass`, readable by root only). It is
deliberately **outside the repository**, which is public.

**Back that folder up.** Android only installs an update over the app if it
is signed with the same key. With the key lost, every phone would have to
uninstall the app (signing out) before installing a new one.
