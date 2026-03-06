<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserRepo;
use App\Repositories\AuditRepo;
use App\Repositories\AccessRepo;
use App\Repositories\LoginAttemptRepo;
use App\Utils\Response;
use App\Utils\Security;

final class AuthController
{
    public function __construct(
        private UserRepo $users,
        private AuditRepo $audit,
        private AccessRepo $access,
        private LoginAttemptRepo $loginAttempts
    ) {}

    public function login(): never
    {
        $data = Security::readJsonBody();
        $username = trim((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($username === '' || $password === '') {
            Response::error('VALIDATION_ERROR', 'Kasutajanimi ja parool on kohustuslikud', 400);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($this->loginAttempts->isRateLimited($ip, $username)) {
            $this->audit->log(null, 'LOGIN_RATE_LIMITED', 'user', null);
            Response::error('RATE_LIMITED', 'Liiga palju ebaõnnestunud katseid. Proovi 10 minuti pärast.', 429);
        }

        $user = $this->users->findActiveByUsername($username);
        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            $this->loginAttempts->recordAttempt($ip, $username);
            $this->audit->log(null, 'LOGIN_FAIL', 'user', null);
            Response::error('INVALID_CREDENTIALS', 'Vale kasutaja või parool', 401);
        }

        Security::login((int)$user['id'], (string)$user['role']);
        $this->audit->log((int)$user['id'], 'LOGIN_SUCCESS', 'user', (int)$user['id']);

        $payload = [
            'ok' => true,
            'user' => [
                'id' => (int)$user['id'],
                'role' => (string)$user['role'],
                'name' => (string)$user['name'],
                'username' => (string)$user['username'],
            ],
            'csrfToken' => Security::csrfToken(),
        ];

        if ($user['role'] === 'TEACHER') {
            $payload['allowedGroups'] = $this->access->getGroupsForTeacher((int)$user['id']);
        } elseif ($user['role'] === 'ADMIN_TEACHER') {
            $payload['allowedGroups'] = $this->access->getGroupsForAdmin();
        }

        Response::json($payload, 200);
    }

    public function me(): never
    {
        Security::requireLogin();
        $uid = Security::getUserId();
        $role = Security::getUserRole();
        if ($uid === null || $role === null) {
            Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);
        }

        $user = $this->users->findById($uid);
        if (!$user) Response::error('UNAUTHORIZED', 'Kasutajat ei leitud', 401);

        $payload = [
            'user' => [
                'id' => (int)$user['id'],
                'role' => (string)$user['role'],
                'name' => (string)$user['name'],
                'username' => (string)$user['username'],
            ],
            'csrfToken' => Security::csrfToken(),
        ];

        if ($user['role'] === 'TEACHER') {
            $payload['allowedGroups'] = $this->access->getGroupsForTeacher((int)$user['id']);
        } elseif ($user['role'] === 'ADMIN_TEACHER') {
            $payload['allowedGroups'] = $this->access->getGroupsForAdmin();
        }

        Response::json($payload, 200);
    }

    public function logout(): never
    {
        $uid = Security::getUserId();
        $this->audit->log($uid, 'LOGOUT', 'user', $uid ?? null);
        Security::logout();
        Response::json(['ok' => true], 200);
    }
}
