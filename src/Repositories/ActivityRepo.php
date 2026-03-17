<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ActivityRepo
{
    public function __construct(private PDO $pdo) {}

    /** @return array<int,array<string,mixed>> */
    public function listActive(?string $search = null, int $limit = 200): array
    {
        $sql = "SELECT id, name, created_by_user_id, created_at
                FROM activities
                WHERE is_active = 1";
        $params = [];

        $search = trim((string)$search);
        if ($search !== '') {
            $sql .= " AND name LIKE :q";
            $params[':q'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY name ASC LIMIT " . max(1, min($limit, 500));
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findActiveById(int $id): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT id, name, created_by_user_id, created_at
             FROM activities
             WHERE id = :id AND is_active = 1
             LIMIT 1"
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function create(string $name, int $createdByUserId): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO activities (name, created_by_user_id, is_active)
             VALUES (:name, :uid, 1)"
        );
        $st->execute([
            ':name' => trim($name),
            ':uid' => $createdByUserId,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function deactivate(int $id): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE activities
             SET is_active = 0
             WHERE id = :id AND is_active = 1"
        );
        $st->execute([':id' => $id]);
        return $st->rowCount() > 0;
    }
}
