<?php

namespace App\Http\Controllers;

/**
 * The Android app (android/), for staff and admins to install from Profile.
 * Served from storage rather than public/ so only signed-in accounts can
 * download it. `android/build.sh --publish` puts it there.
 */
class AndroidAppController extends Controller
{
    public static function path(): string
    {
        return config('services.android.apk_path');
    }

    public static function available(): bool
    {
        return is_file(self::path());
    }

    public function download()
    {
        abort_unless(self::available(), 404, "The Android app hasn't been built on this server yet.");

        return response()->download(self::path(), 'LawatKape.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
}
