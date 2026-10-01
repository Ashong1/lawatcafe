<?php

namespace App\Support;

/** MAC addresses as people read them on a phone's Wi-Fi settings: AA:BB:CC:DD:EE:FF. */
final class Mac
{
    public static function format(?string $mac): string
    {
        $hex = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', (string) $mac));

        return strlen($hex) === 12 ? implode(':', str_split($hex, 2)) : (string) $mac;
    }
}
