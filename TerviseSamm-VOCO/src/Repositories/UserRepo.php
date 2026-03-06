<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepo
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed>|null */
    public function findActiveByUsername(string $username): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT id, role, name, username, password_hash, is_active
             FROM users WHERE username = :u AND is_active = 1 LIMIT 1"
        );
        $st->execute([':u' => $username]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT id, role, name, username, is_active FROM users WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function listTeachers(): array
    {
        $st = $this->pdo->query(
            "SELECT id, role, name, username, is_active FROM users WHERE role = 'TEACHER' OR role = 'ADMIN_TEACHER' ORDER BY name"
        );
        return $st->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function listStudents(): array
    {
        $st = $this->pdo->query(
            "SELECT id, name, username FROM users WHERE role = 'STUDENT' ORDER BY name"
        );
        return $st->fetchAll();
    }
}
