<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Utils\Response;
use App\Utils\Security;

final class RoleMiddleware
{
    /** @param array<int,string> $allowedRoles */
    public static function requireRole(array $allowedRoles): void
    {
        $role = Security::getUserRole();
        if ($role === null || !in_array($role, $allowedRoles, true)) {
            Response::error('FORBIDDEN', 'Sul pole ligipääsu', 403);
        }
    }
}
