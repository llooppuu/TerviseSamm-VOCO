<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AccessRepo
{
    public function __construct(private PDO $pdo) {}

    /** @return array<int,array<string,mixed>> Öpetaja rühmad (ainult lubatud) */
    public function getGroupsForTeacher(int $teacherId): array
    {
        $st = $this->pdo->prepare(
            "SELECT g.id, g.code, g.name
             FROM teacher_group_access tga
             JOIN `groups` g ON g.id = tga.group_id AND g.is_active = 1
             WHERE tga.teacher_user_id = :tid
             ORDER BY g.code"
        );
        $st->execute([':tid' => $teacherId]);
        return $st->fetchAll();
    }

    /** Admin näeb kõiki rühmi */
    public function getGroupsForAdmin(): array
    {
        $st = $this->pdo->query("SELECT id, code, name FROM `groups` WHERE is_active = 1 ORDER BY code");
        return $st->fetchAll();
    }

    public function hasAccessToGroup(int $teacherId, int $groupId): bool
    {
        $st = $this->pdo->prepare(
            "SELECT 1 FROM teacher_group_access WHERE teacher_user_id = :tid AND group_id = :gid LIMIT 1"
        );
        $st->execute([':tid' => $teacherId, ':gid' => $groupId]);
        return $st->fetch() !== false;
    }

    public function hasAccessToGroupByCode(int $teacherId, string $code): bool
    {
        $st = $this->pdo->prepare(
            "SELECT 1 FROM teacher_group_access tga
             JOIN `groups` g ON g.id = tga.group_id
             WHERE tga.teacher_user_id = :tid AND g.code = :c LIMIT 1"
        );
        $st->execute([':tid' => $teacherId, ':c' => $code]);
        return $st->fetch() !== false;
    }

    /** @return array<int,int> group_id list */
    public function getGroupIdsForTeacher(int $teacherId): array
    {
        $st = $this->pdo->prepare(
            "SELECT group_id FROM teacher_group_access WHERE teacher_user_id = :tid"
        );
        $st->execute([':tid' => $teacherId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public function grant(int $teacherId, int $groupId, int $adminId): bool
    {
        $st = $this->pdo->prepare(
            "INSERT IGNORE INTO teacher_group_access (teacher_user_id, group_id, granted_by_user_id) VALUES (:tid, :gid, :aid)"
        );
        $st->execute([':tid' => $teacherId, ':gid' => $groupId, ':aid' => $adminId]);
        return $st->rowCount() > 0;
    }

    public function revoke(int $teacherId, int $groupId): bool
    {
        $st = $this->pdo->prepare(
            "DELETE FROM teacher_group_access WHERE teacher_user_id = :tid AND group_id = :gid"
        );
        $st->execute([':tid' => $teacherId, ':gid' => $groupId]);
        return $st->rowCount() > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public function getAccessForTeacher(int $teacherId): array
    {
        $st = $this->pdo->prepare(
            "SELECT g.id, g.code, g.name FROM teacher_group_access tga
             JOIN `groups` g ON g.id = tga.group_id
             WHERE tga.teacher_user_id = :tid"
        );
        $st->execute([':tid' => $teacherId]);
        return $st->fetchAll();
    }
}
