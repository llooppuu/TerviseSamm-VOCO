<?php
declare(strict_types=1);

namespace App\Utils;

final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];

    public static function load(string $path): void
    {
        if (!file_exists($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;

            $pos = strpos($line, '=');
            if ($pos === false) continue;

            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));

            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }

            self::$vars[$key] = $val;
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        return self::$vars[$key] ?? getenv($key) ?: $default;
    }
}
