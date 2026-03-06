<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class GroupRepo
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed>|null */
    public function findByCode(string $code): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM `groups` WHERE code = :c AND is_active = 1 LIMIT 1");
        $st->execute([':c' => $code]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM `groups` WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function listAll(): array
    {
        $st = $this->pdo->query("SELECT id, code, name, is_active FROM `groups` ORDER BY code");
        return $st->fetchAll();
    }

    public function create(string $code, ?string $name = null): int
    {
        $st = $this->pdo->prepare("INSERT INTO `groups` (code, name) VALUES (:c, :n)");
        $st->execute([':c' => $code, ':n' => $name ?? $code]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, ?string $name, ?bool $isActive): bool
    {
        $sets = [];
        $params = [':id' => $id];
        if ($name !== null) { $sets[] = 'name = :n'; $params[':n'] = $name; }
        if ($isActive !== null) { $sets[] = 'is_active = :a'; $params[':a'] = $isActive ? 1 : 0; }
        if (empty($sets)) return true;
        $st = $this->pdo->prepare("UPDATE `groups` SET " . implode(', ', $sets) . " WHERE id = :id");
        $st->execute($params);
        return $st->rowCount() > 0;
    }

    public function addStudent(int $groupId, int $studentId): bool
    {
        $st = $this->pdo->prepare("INSERT IGNORE INTO group_students (group_id, student_user_id) VALUES (:g, :s)");
        $st->execute([':g' => $groupId, ':s' => $studentId]);
        return $st->rowCount() > 0;
    }

    public function removeStudent(int $groupId, int $studentId): bool
    {
        $st = $this->pdo->prepare("DELETE FROM group_students WHERE group_id = :g AND student_user_id = :s");
        $st->execute([':g' => $groupId, ':s' => $studentId]);
        return $st->rowCount() > 0;
    }

    /** @return array<int,int> student_user_id list */
    public function getStudentIds(int $groupId): array
    {
        $st = $this->pdo->prepare("SELECT student_user_id FROM group_students WHERE group_id = :g");
        $st->execute([':g' => $groupId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<int,array{id:int,name:string,username:string}> */
    public function getStudentsDetailed(int $groupId): array
    {
        $st = $this->pdo->prepare(
            "SELECT u.id, u.name, u.username
             FROM group_students gs
             JOIN users u ON u.id = gs.student_user_id
             WHERE gs.group_id = :g AND u.role = 'STUDENT'
             ORDER BY u.name"
        );
        $st->execute([':g' => $groupId]);

        return array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'username' => (string) $row['username'],
        ], $st->fetchAll());
    }
}
