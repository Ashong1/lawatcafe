<?php

namespace App\Support;

/** CSV rows the owner opens in Excel: a text cell starting with = + - @ would run as a formula, so it gets a leading '. */
final class Csv
{
    public static function safe(array $row): array
    {
        return array_map(fn ($cell) => is_string($cell) && $cell !== '' && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$cell : $cell, $row);
    }
}
