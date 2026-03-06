<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Utils\Security;

final class AuthMiddleware
{
    public static function requireLogin(): void
    {
        Security::requireLogin();
    }
}
