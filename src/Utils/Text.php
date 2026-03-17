<?php
declare(strict_types=1);

namespace App\Utils;

final class Text
{
    public static function normalizeUtf8(string $value): string
    {
        // Tüüpiline mojibake muster, nt "RattasÃµit" -> "Rattasõit"
        if (!preg_match('/Ã|Â/u', $value)) {
            return $value;
        }

        if (!function_exists('iconv')) {
            return $value;
        }

        $fixed = iconv('UTF-8', 'Windows-1252//IGNORE', $value);
        if ($fixed === false || $fixed === '') {
            return $value;
        }

        return $fixed;
    }
}
