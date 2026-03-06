<?php
declare(strict_types=1);

namespace App\Utils;

final class Security
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        $name = Env::get('SESSION_NAME', 'app_sess');
        session_name($name);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf'];
    }

    public static function requireCsrf(): void
    {
        self::startSession();
        $headerName = Env::get('CSRF_HEADER', 'X-CSRF-Token');
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));
        $provided = $_SERVER[$key] ?? '';
        $expected = $_SESSION['csrf'] ?? '';
        if (!$expected || !hash_equals((string)$expected, (string)$provided)) {
            Response::error('CSRF_INVALID', 'CSRF token puudub või on vale', 403);
        }
    }

    /** @return array<string,mixed> */
    public static function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') return [];

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Response::error('BAD_JSON', 'Päringu JSON on vigane', 400);
        }
        return $data;
    }

    public static function getUserId(): ?int
    {
        self::startSession();
        $id = $_SESSION['user_id'] ?? null;
        return is_int($id) ? $id : null;
    }

    public static function getUserRole(): ?string
    {
        self::startSession();
        $role = $_SESSION['role'] ?? null;
        return is_string($role) ? $role : null;
    }

    public static function requireLogin(): void
    {
        if (self::getUserId() === null) {
            Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);
        }
    }

    public static function login(int $userId, string $role): void
    {
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = $role;
        self::csrfToken();
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
