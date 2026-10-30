<?php

namespace App\Http\Controllers;

/**
 * The Windows and Linux apps (desktop/), downloaded from Profile like the
 * Android one. `desktop/build.sh --publish` puts them in storage.
 */
class DesktopAppController extends Controller
{
    public const FILES = ['windows' => 'LawatKape-Setup.exe', 'linux' => 'LawatKape-linux.deb'];

    public static function available(string $os): bool
    {
        return is_file(storage_path('app/desktop/'.self::FILES[$os]));
    }

    /** Changes with every build, so each new build gets a new download link. */
    public static function version(string $os): int
    {
        return (int) @filemtime(storage_path('app/desktop/'.self::FILES[$os]));
    }

    public function download(string $os)
    {
        abort_unless(isset(self::FILES[$os]) && self::available($os), 404, "That app hasn't been built on this server yet.");

        return response()->download(storage_path('app/desktop/'.self::FILES[$os]), self::FILES[$os], [
            'Content-Type' => 'application/octet-stream',
            // A new build must never come from a cache (phones kept getting the old app).
            'Cache-Control' => 'no-store',
        ])->setPrivate();
    }
}
