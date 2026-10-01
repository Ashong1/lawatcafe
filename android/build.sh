#!/usr/bin/env bash
# Builds and signs the Android app: android/build/LawatKape.apk
#
# Uses the Android SDK's own tools (aapt2, javac, d8, apksigner) without Gradle.
# Needs: JDK 17, rsvg-convert, and an Android SDK with platforms;android-34 and
# build-tools;34.0.0 (docs/ANDROID_APP.md has the setup).
#
#   ./build.sh            build and sign
#   ./build.sh --publish  also copy it to storage/app/android/ for download from Profile
#
# The signing key is created on first run OUTSIDE this repository (it is
# public). Keep it: Android only installs an update over the app if it is
# signed with the same key.
set -euo pipefail
cd "$(dirname "$0")"

SDK="${ANDROID_HOME:-/opt/android-sdk}"
BT="$SDK/build-tools/34.0.0"
PLATFORM="$SDK/platforms/android-34/android.jar"
KEY_DIR="${LAWATKAPE_KEY_DIR:-/opt/lawatkape-android}"
KEYSTORE="$KEY_DIR/release.jks"
KEYPASS="$KEY_DIR/keystore.pass"
VERSION_CODE="${VERSION_CODE:-1}"
VERSION_NAME="${VERSION_NAME:-1.0}"
OUT=build

for tool in "$BT/aapt2" "$BT/d8" "$BT/zipalign" "$BT/apksigner" "$PLATFORM"; do
    [ -e "$tool" ] || { echo "Missing $tool — see docs/ANDROID_APP.md"; exit 1; }
done

rm -rf "$OUT"
mkdir -p "$OUT/res" "$OUT/classes" "$OUT/dex" "$OUT/gen"
cp -r res/. "$OUT/res/"

# Launcher icon from the same logo as the website's favicon.
for spec in mdpi:48 hdpi:72 xhdpi:96 xxhdpi:144 xxxhdpi:192; do
    density="${spec%%:*}"; px="${spec##*:}"
    mkdir -p "$OUT/res/mipmap-$density"
    rsvg-convert -w "$px" -h "$px" ../public/favicon.svg -o "$OUT/res/mipmap-$density/ic_launcher.png"
done

"$BT/aapt2" compile --dir "$OUT/res" -o "$OUT/res.zip"
"$BT/aapt2" link -o "$OUT/unsigned.apk" -I "$PLATFORM" \
    --manifest AndroidManifest.xml -A assets --java "$OUT/gen" \
    --version-code "$VERSION_CODE" --version-name "$VERSION_NAME" \
    "$OUT/res.zip"

javac -encoding UTF-8 -nowarn --release 8 -classpath "$PLATFORM" \
    -d "$OUT/classes" $(find src "$OUT/gen" -name '*.java')
"$BT/d8" --min-api 24 --lib "$PLATFORM" --output "$OUT/dex" $(find "$OUT/classes" -name '*.class')
(cd "$OUT/dex" && zip -q ../unsigned.apk classes.dex)
"$BT/zipalign" -f -p 4 "$OUT/unsigned.apk" "$OUT/aligned.apk"

if [ ! -f "$KEYSTORE" ]; then
    echo "Creating the signing key in $KEY_DIR (back this folder up)."
    mkdir -p "$KEY_DIR"; chmod 700 "$KEY_DIR"
    head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' > "$KEYPASS"
    chmod 600 "$KEYPASS"
    keytool -genkeypair -keystore "$KEYSTORE" -storepass:file "$KEYPASS" -keypass:file "$KEYPASS" \
        -alias lawatkape -keyalg RSA -keysize 2048 -validity 10000 \
        -dname "CN=Lawa't Kape, O=Lawa't Kape, C=PH" >/dev/null 2>&1
    chmod 600 "$KEYSTORE"
fi

"$BT/apksigner" sign --ks "$KEYSTORE" --ks-pass "file:$KEYPASS" \
    --out "$OUT/LawatKape.apk" "$OUT/aligned.apk"
"$BT/apksigner" verify "$OUT/LawatKape.apk"
echo "Built $OUT/LawatKape.apk ($VERSION_NAME, code $VERSION_CODE, $(( $(stat -c %s "$OUT/LawatKape.apk") / 1024 )) KB)"

if [ "${1:-}" = "--publish" ]; then
    mkdir -p ../storage/app/android
    cp "$OUT/LawatKape.apk" ../storage/app/android/LawatKape.apk
    chown -R www-data:www-data ../storage/app/android 2>/dev/null || true
    echo "Published to storage/app/android/LawatKape.apk"
fi
