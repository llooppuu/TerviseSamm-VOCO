<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ActivityRepo;
use App\Repositories\AuditRepo;
use App\Utils\Response;
use App\Utils\Security;
use App\Utils\Text;

final class ActivityController
{
    public function __construct(
        private ActivityRepo $activities,
        private AuditRepo $audit
    ) {}

    public function listActivities(): never
    {
        Security::requireLogin();
        $search = trim((string)($_GET['search'] ?? ''));
        try {
            $list = $this->activities->listActive($search, 200);
        } catch (\PDOException $e) {
            if (in_array($e->getCode(), ['42S02', '42S22'], true)) {
                Response::error('DB_SCHEMA_MISMATCH', 'DB skeem on vananenud. Käivita: mysql -u root -p < database/migrate_activities.sql', 500);
            }
            throw $e;
        }
        $mapped = array_map(fn($a) => [
            'id' => (int)$a['id'],
            'name' => Text::normalizeUtf8((string)$a['name']),
        ], $list);

        Response::json(['activities' => $mapped], 200);
    }

    public function createActivity(): never
    {
        Security::requireLogin();
        $userId = Security::getUserId();
        $role = Security::getUserRole();
        if ($userId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);
        if (!in_array($role, ['TEACHER', 'ADMIN_TEACHER'], true)) {
            Response::error('FORBIDDEN', 'Tegevusi saavad lisada ainult õpetaja ja boss', 403);
        }

        $data = Security::readJsonBody();
        $name = Text::normalizeUtf8(trim((string)($data['name'] ?? '')));
        if ($name === '') {
            Response::error('VALIDATION_ERROR', 'Tegevuse nimi on kohustuslik', 400);
        }
        if ((function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > 100) {
            Response::error('VALIDATION_ERROR', 'Tegevuse nimi max 100 tähemärki', 400);
        }

        try {
            $id = $this->activities->create($name, (int)$userId);
        } catch (\PDOException $e) {
            if (in_array($e->getCode(), ['42S02', '42S22'], true)) {
                Response::error('DB_SCHEMA_MISMATCH', 'DB skeem on vananenud. Käivita: mysql -u root -p < database/migrate_activities.sql', 500);
            }
            if ($e->getCode() === '23000') {
                Response::error('DUPLICATE_ACTIVITY', 'Selline tegevus on juba olemas', 409);
            }
            throw $e;
        }

        $this->audit->log((int)$userId, 'ACTIVITY_CREATE', 'activity', $id);
        Response::json(['ok' => true, 'activity' => ['id' => $id, 'name' => $name]], 201);
    }

    public function deleteActivity(int $id): never
    {
        Security::requireLogin();
        $userId = Security::getUserId();
        $role = Security::getUserRole();
        if ($userId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);
        if (!in_array($role, ['TEACHER', 'ADMIN_TEACHER'], true)) {
            Response::error('FORBIDDEN', 'Tegevusi saavad kustutada ainult õpetaja ja boss', 403);
        }

        try {
            $activity = $this->activities->findActiveById($id);
            if (!$activity) {
                Response::error('NOT_FOUND', 'Tegevust ei leitud', 404);
            }

            $this->activities->deactivate($id);
        } catch (\PDOException $e) {
            if (in_array($e->getCode(), ['42S02', '42S22'], true)) {
                Response::error('DB_SCHEMA_MISMATCH', 'DB skeem on vananenud. Käivita: mysql -u root -p < database/migrate_activities.sql', 500);
            }
            throw $e;
        }

        $this->audit->log((int)$userId, 'ACTIVITY_DEACTIVATE', 'activity', $id);
        Response::json(['ok' => true], 200);
    }
}
