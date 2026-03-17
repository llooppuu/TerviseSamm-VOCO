<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AccessRepo;
use App\Repositories\GroupRepo;
use App\Repositories\EntryRepo;
use App\Repositories\UserRepo;
use App\Services\TrendService;
use App\Utils\Response;
use App\Utils\Security;
use App\Utils\Text;

final class TeacherController
{
    public function __construct(
        private AccessRepo $access,
        private GroupRepo $groups,
        private EntryRepo $entries,
        private UserRepo $users,
        private TrendService $trend
    ) {}

    public function getGroups(): never
    {
        Security::requireLogin();
        $teacherId = Security::getUserId();
        $role = Security::getUserRole();
        if ($teacherId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        if ($role === 'ADMIN_TEACHER') {
            $list = $this->access->getGroupsForAdmin();
        } else {
            $list = $this->access->getGroupsForTeacher($teacherId);
        }

        $mapped = array_map(fn($g) => [
            'id' => (int)$g['id'],
            'code' => $g['code'],
            'name' => $g['name'] ?? $g['code'],
        ], $list);

        Response::json(['groups' => $mapped], 200);
    }

    public function getGroupSummary(string $code): never
    {
        Security::requireLogin();
        $teacherId = Security::getUserId();
        $role = Security::getUserRole();
        if ($teacherId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $group = $this->groups->findByCode($code);
        if (!$group) Response::error('NOT_FOUND', 'Rühma ei leitud', 404);

        if ($role === 'TEACHER' && !$this->access->hasAccessToGroup($teacherId, (int)$group['id'])) {
            Response::error('FORBIDDEN', 'Sul pole selle rühma ligipääsu', 403);
        }

        $days = (int)($_GET['days'] ?? 14);
        $today = date('Y-m-d');
        $from = date('Y-m-d', strtotime("-{$days} days"));

        $studentIds = $this->groups->getStudentIds((int)$group['id']);
        $studentCount = count($studentIds);

        if ($studentCount === 0) {
            Response::json([
                'group' => ['code' => $group['code'], 'name' => $group['name'] ?? $group['code']],
                'days' => $days,
                'student_count' => 0,
                'participation_count' => 0,
                'avg_pushups_last' => 0,
                'avg_delta_pushups' => 0,
            ], 200);
        }

        $participationCount = 0;
        $pushupsSum = 0;
        $pushupsCount = 0;
        $deltaSum = 0;
        $deltaCount = 0;

        foreach ($studentIds as $sid) {
            $lastEntries = $this->entries->getLastEntriesForStudent($sid, 2);
            if (empty($lastEntries)) continue;

            $last = $lastEntries[0];
            $lastDate = $last['entry_date'];
            $lastPushups = $last['pushups'] !== null ? (int)$last['pushups'] : null;

            if ($lastDate >= $from) $participationCount++;

            if ($lastPushups !== null) {
                $pushupsSum += $lastPushups;
                $pushupsCount++;
            }

            $prev = $lastEntries[1] ?? null;
            $prevPushups = $prev && $prev['pushups'] !== null ? (int)$prev['pushups'] : null;
            if ($lastPushups !== null && $prevPushups !== null) {
                $deltaSum += $lastPushups - $prevPushups;
                $deltaCount++;
            }
        }

        $avgPushups = $pushupsCount > 0 ? round($pushupsSum / $pushupsCount, 1) : 0;
        $avgDelta = $deltaCount > 0 ? round($deltaSum / $deltaCount, 1) : 0;

        Response::json([
            'group' => ['code' => $group['code'], 'name' => $group['name'] ?? $group['code']],
            'days' => $days,
            'student_count' => $studentCount,
            'participation_count' => $participationCount,
            'avg_pushups_last' => $avgPushups,
            'avg_delta_pushups' => $avgDelta,
        ], 200);
    }

    public function getGroupStudents(string $code): never
    {
        Security::requireLogin();
        $teacherId = Security::getUserId();
        $role = Security::getUserRole();
        if ($teacherId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $group = $this->groups->findByCode($code);
        if (!$group) Response::error('NOT_FOUND', 'Rühma ei leitud', 404);

        if ($role === 'TEACHER' && !$this->access->hasAccessToGroup($teacherId, (int)$group['id'])) {
            Response::error('FORBIDDEN', 'Sul pole selle rühma ligipääsu', 403);
        }

        $studentIds = $this->groups->getStudentIds((int)$group['id']);
        $today = date('Y-m-d');

        $students = [];
        foreach ($studentIds as $sid) {
            $user = $this->users->findById($sid);
            if (!$user) continue;

            $lastEntries = $this->entries->getLastEntriesForStudent($sid, 2);
            $last = $lastEntries[0] ?? null;
            $prev = $lastEntries[1] ?? null;

            $lastDate = $last['entry_date'] ?? null;
            $lastPushups = $last && $last['pushups'] !== null ? (int)$last['pushups'] : null;
            $lastActivityName = $last && $last['activity_name'] !== null ? Text::normalizeUtf8((string)$last['activity_name']) : null;
            $prevPushups = $prev && $prev['pushups'] !== null ? (int)$prev['pushups'] : null;

            $trendStatus = $this->trend->getTrendStatus($lastDate, $lastPushups, $prevPushups, $today);

            $students[] = [
                'student_id' => (int)$user['id'],
                'name' => (string)$user['name'],
                'last_entry_date' => $lastDate,
                'last_activity_name' => $lastActivityName,
                'trend_status' => $trendStatus,
            ];
        }

        usort($students, fn($a, $b) => strcmp($a['name'], $b['name']));

        Response::json([
            'group' => ['code' => $group['code']],
            'students' => $students,
        ], 200);
    }

    public function getStudentRecentEntries(int $studentId): never
    {
        Security::requireLogin();
        $teacherId = Security::getUserId();
        $role = Security::getUserRole();
        if ($teacherId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $user = $this->users->findById($studentId);
        if (!$user || $user['role'] !== 'STUDENT') {
            Response::error('NOT_FOUND', 'Õpilast ei leitud', 404);
        }

        $groupIds = $role === 'ADMIN_TEACHER'
            ? array_column($this->access->getGroupsForAdmin(), 'id')
            : $this->access->getGroupIdsForTeacher($teacherId);

        $hasAccess = false;
        foreach ($groupIds as $gid) {
            $ids = $this->groups->getStudentIds($gid);
            if (in_array($studentId, $ids, true)) {
                $hasAccess = true;
                break;
            }
        }
        if (!$hasAccess) Response::error('FORBIDDEN', 'Sul pole selle õpilase ligipääsu', 403);

        $limit = (int)($_GET['limit'] ?? 5);
        $limit = min(max($limit, 1), 20);
        $rows = $this->entries->getLastEntriesForStudent($studentId, $limit);

        $entries = array_map(fn($r) => [
            'entry_date' => $r['entry_date'],
            'pushups' => $r['pushups'] !== null ? (int)$r['pushups'] : null,
            'weight_kg' => $r['weight_kg'] !== null ? (float)$r['weight_kg'] : null,
            'activity_name' => $r['activity_name'] !== null ? Text::normalizeUtf8((string)$r['activity_name']) : null,
        ], $rows);

        Response::json([
            'student' => ['id' => (int)$user['id'], 'name' => (string)$user['name']],
            'entries' => $entries,
        ], 200);
    }
}
