<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserRepo;
use App\Repositories\GroupRepo;
use App\Repositories\AccessRepo;
use App\Repositories\AuditRepo;
use App\Utils\Response;
use App\Utils\Security;
use PDO;

final class AdminController
{
    public function __construct(
        private UserRepo $users,
        private GroupRepo $groups,
        private AccessRepo $access,
        private AuditRepo $audit,
        private PDO $pdo
    ) {}

    public function listTeachers(): never
    {
        Security::requireLogin();
        $this->requireAdmin();

        $list = $this->users->listTeachers();
        $mapped = array_map(fn($u) => [
            'id' => (int)$u['id'],
            'role' => (string)$u['role'],
            'name' => (string)$u['name'],
            'username' => (string)$u['username'],
            'is_active' => (int)$u['is_active'],
            'access' => array_map(fn($g) => [
                'id' => (int)$g['id'],
                'code' => (string)$g['code'],
                'name' => (string)($g['name'] ?? $g['code']),
            ], $this->access->getAccessForTeacher((int)$u['id'])),
        ], $list);

        Response::json(['teachers' => $mapped], 200);
    }

    public function createTeacher(): never
    {
        Security::requireLogin();
        $adminId = Security::getUserId();
        $this->requireAdmin();
        if ($adminId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $data = Security::readJsonBody();
        $name = trim((string)($data['name'] ?? ''));
        $username = trim((string)($data['username'] ?? ''));
        $tempPassword = (string)($data['temp_password'] ?? '');

        if ($name === '' || $username === '') {
            Response::error('VALIDATION_ERROR', 'Nimi ja kasutajanimi on kohustuslikud', 400);
        }
        if (strlen($tempPassword) < 6) {
            Response::error('VALIDATION_ERROR', 'Parool peab olema vähemalt 6 tähemärki', 400);
        }

        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);
        $st = $this->pdo->prepare(
            "INSERT INTO users (role, name, username, password_hash) VALUES ('TEACHER', :n, :u, :h)"
        );
        try {
            $st->execute([':n' => $name, ':u' => $username, ':h' => $hash]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::error('DUPLICATE_USERNAME', 'Kasutajanimi on juba kasutusel', 409);
            }
            throw $e;
        }

        $userId = (int) $this->pdo->lastInsertId();
        $this->audit->log($adminId, 'TEACHER_CREATE', 'user', $userId);

        Response::json([
            'ok' => true,
            'teacher' => [
                'id' => $userId,
                'name' => $name,
                'username' => $username,
                'temp_password' => $tempPassword,
            ],
        ], 201);
    }

    public function patchTeacher(int $id): never
    {
        Security::requireLogin();
        $adminId = Security::getUserId();
        $this->requireAdmin();
        if ($adminId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $data = Security::readJsonBody();
        $isActive = array_key_exists('is_active', $data) ? (bool)$data['is_active'] : null;

        if ($isActive === null) Response::error('VALIDATION_ERROR', 'is_active on kohustuslik', 400);

        $user = $this->users->findById($id);
        if (!$user || !in_array($user['role'] ?? '', ['TEACHER', 'ADMIN_TEACHER'], true)) {
            Response::error('NOT_FOUND', 'Õpetajat ei leitud', 404);
        }
        if (($user['role'] ?? '') === 'ADMIN_TEACHER') {
            Response::error('FORBIDDEN', 'Boss-õpetaja aktiivsust ei saa siit muuta', 403);
        }

        $st = $this->pdo->prepare("UPDATE users SET is_active = :a WHERE id = :id");
        $st->execute([':a' => $isActive ? 1 : 0, ':id' => $id]);
        $this->audit->log($adminId, $isActive ? 'TEACHER_ACTIVATE' : 'TEACHER_DEACTIVATE', 'user', $id);

        Response::json(['ok' => true], 200);
    }

    public function resetTeacherPassword(int $id): never
    {
        Security::requireLogin();
        $adminId = Security::getUserId();
        $this->requireAdmin();
        if ($adminId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $user = $this->users->findById($id);
        if (!$user || !in_array($user['role'] ?? '', ['TEACHER', 'ADMIN_TEACHER'], true)) {
            Response::error('NOT_FOUND', 'Õpetajat ei leitud', 404);
        }
        if (($user['role'] ?? '') === 'ADMIN_TEACHER') {
            Response::error('FORBIDDEN', 'Boss-õpetaja parooli ei saa siit lähtestada', 403);
        }

        $tempPassword = bin2hex(random_bytes(4));
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);
        $st = $this->pdo->prepare("UPDATE users SET password_hash = :h WHERE id = :id");
        $st->execute([':h' => $hash, ':id' => $id]);

        $this->audit->log($adminId, 'PASSWORD_RESET', 'user', $id);

        Response::json(['ok' => true, 'temp_password' => $tempPassword], 200);
    }

    public function listGroups(): never
    {
        Security::requireLogin();
        $this->requireAdmin();

        $list = $this->groups->listAll();
        $mapped = array_map(fn($g) => [
            'id' => (int)$g['id'],
            'code' => (string)$g['code'],
            'name' => (string)($g['name'] ?? $g['code']),
            'is_active' => (int)$g['is_active'],
            'students' => $this->groups->getStudentsDetailed((int)$g['id']),
        ], $list);

        Response::json(['groups' => $mapped], 200);
    }

    public function createGroup(): never
    {
        Security::requireLogin();
        $adminId = Security::getUserId();
        $this->requireAdmin();
        if ($adminId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $data = Security::readJsonBody();
        $code = strtoupper(trim((string)($data['code'] ?? '')));
        $name = trim((string)($data['name'] ?? '')) ?: null;

        if ($code === '') Response::error('VALIDATION_ERROR', 'Rühma kood on kohustuslik', 400);

        try {
            $gid = $this->groups->create($code, $name);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::error('DUPLICATE_CODE', 'Rühma kood on juba kasutusel', 409);
            }
            throw $e;
        }

        $this->audit->log($adminId, 'GROUP_CREATE', 'group', $gid);

        Response::json(['ok' => true, 'group' => ['id' => $gid, 'code' => $code, 'name' => $name ?? $code]], 201);
    }

    public function patchGroup(int $id): never
    {
        Security::requireLogin();
        $adminId = Security::getUserId();
        $this->requireAdmin();
        if ($adminId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $data = Security::readJsonBody();
        $name = array_key_exists('name', $data) ? trim((string)$data['name']) : null;
        $isActive = array_key_exists('is_active', $data) ? (bool)$data['is_active'] : null;

        $group = $this->groups->findById($id);
        if (!$group) Response::error('NOT_FOUND', 'Rühma ei leitud', 404);

        $this->groups->update($id, $name, $isActive);
        $this->audit->log($adminId, 'GROUP_UPDATE', 'group', $id);

        Response::json(['ok' => true], 200);
    }

    public function getAccess(int $teacherId): never
    {
        Security::requireLogin();
        $this->requireAdmin();

        $list = $this->access->getAccessForTeacher($teacherId);
        $mapped = array_map(fn($g) => [
            'id' => (int)$g['id'],
            'code' => (string)$g['code'],
            'name' => (string)($g['name'] ?? $g['code']),
        ], $list);

        Response::json(['access' => $mapped], 200);
    }

    public function grantAccess(): never
    {
        Security::requireLogin();
        $adminId = Security::getUserId();
        $this->requireAdmin();
        if ($adminId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $data = Security::readJsonBody();
        $teacherId = (int)($data['teacher_id'] ?? 0);
        $groupId = (int)($data['group_id'] ?? 0);

        if ($teacherId < 1 || $groupId < 1) {
            Response::error('VALIDATION_ERROR', 'teacher_id ja group_id on kohustuslikud', 400);
        }

        $this->access->grant($teacherId, $groupId, $adminId);
        $this->audit->log($adminId, 'ACCESS_GRANT', 'access', null);

        Response::json(['ok' => true], 200);
    }

    public function revokeAccess(): never
    {
        Security::requireLogin();
        $adminId = Security::getUserId();
        $this->requireAdmin();
        if ($adminId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $data = Security::readJsonBody();
        $teacherId = (int)($data['teacher_id'] ?? 0);
        $groupId = (int)($data['group_id'] ?? 0);

        if ($teacherId < 1 || $groupId < 1) {
            Response::error('VALIDATION_ERROR', 'teacher_id ja group_id on kohustuslikud', 400);
        }

        $this->access->revoke($teacherId, $groupId);
        $this->audit->log($adminId, 'ACCESS_REVOKE', 'access', null);

        Response::json(['ok' => true], 200);
    }

    public function addStudentToGroup(int $groupId): never
    {
        Security::requireLogin();
        $adminId = Security::getUserId();
        $this->requireAdmin();
        if ($adminId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $data = Security::readJsonBody();
        $studentId = (int)($data['student_user_id'] ?? 0);

        if ($studentId < 1) Response::error('VALIDATION_ERROR', 'student_user_id on kohustuslik', 400);

        $group = $this->groups->findById($groupId);
        if (!$group) Response::error('NOT_FOUND', 'Rühma ei leitud', 404);

        $user = $this->users->findById($studentId);
        if (!$user || ($user['role'] ?? '') !== 'STUDENT') {
            Response::error('VALIDATION_ERROR', 'Õpilast ei leitud', 400);
        }

        $this->groups->addStudent($groupId, $studentId);
        $this->audit->log($adminId, 'GROUP_STUDENT_ADD', 'group', $groupId);

        Response::json(['ok' => true], 200);
    }

    public function removeStudentFromGroup(int $groupId): never
    {
        Security::requireLogin();
        $adminId = Security::getUserId();
        $this->requireAdmin();
        if ($adminId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $data = Security::readJsonBody();
        $studentId = (int)($data['student_user_id'] ?? 0);

        if ($studentId < 1) Response::error('VALIDATION_ERROR', 'student_user_id on kohustuslik', 400);

        $this->groups->removeStudent($groupId, $studentId);
        $this->audit->log($adminId, 'GROUP_STUDENT_REMOVE', 'group', $groupId);

        Response::json(['ok' => true], 200);
    }

    public function listStudents(): never
    {
        Security::requireLogin();
        $this->requireAdmin();

        $list = $this->users->listStudents();
        $mapped = array_map(fn($u) => [
            'id' => (int)$u['id'],
            'name' => (string)$u['name'],
            'username' => (string)$u['username'],
        ], $list);

        Response::json(['students' => $mapped], 200);
    }

    private function requireAdmin(): void
    {
        $role = Security::getUserRole();
        if ($role !== 'ADMIN_TEACHER') {
            Response::error('FORBIDDEN', 'Sul pole ligipääsu', 403);
        }
    }
}
