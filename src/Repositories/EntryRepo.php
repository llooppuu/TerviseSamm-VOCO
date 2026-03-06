<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class EntryRepo
{
    public function __construct(private PDO $pdo) {}

    /** @return array<int,array<string,mixed>> */
    public function listForStudent(int $studentId, string $from, string $to): array
    {
        $st = $this->pdo->prepare(
            "SELECT e.id, e.entry_date, e.weight_kg, e.pushups, e.note, af.feedback_text
             FROM entries e
             LEFT JOIN ai_feedback af ON af.entry_id = e.id
             WHERE e.student_user_id = :sid AND e.entry_date BETWEEN :from AND :to
             ORDER BY e.entry_date DESC"
        );
        $st->execute([':sid' => $studentId, ':from' => $from, ':to' => $to]);
        return $st->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM entries WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function existsForDate(int $studentId, string $date, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM entries WHERE student_user_id = :sid AND entry_date = :d";
        $params = [':sid' => $studentId, ':d' => $date];
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude";
            $params[':exclude'] = $excludeId;
        }
        $st = $this->pdo->prepare($sql . " LIMIT 1");
        $st->execute($params);
        return $st->fetch() !== false;
    }

    public function create(int $studentId, string $entryDate, ?float $weightKg, ?int $pushups, ?string $note): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO entries (student_user_id, entry_date, weight_kg, pushups, note)
             VALUES (:sid, :d, :w, :p, :n)"
        );
        $st->execute([
            ':sid' => $studentId,
            ':d' => $entryDate,
            ':w' => $weightKg,
            ':p' => $pushups,
            ':n' => $note ? (function_exists('mb_substr') ? mb_substr($note, 0, 300) : substr($note, 0, 300)) : null
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, ?float $weightKg, ?int $pushups, ?string $note): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE entries SET weight_kg = :w, pushups = :p, note = :n WHERE id = :id"
        );
        $st->execute([
            ':id' => $id,
            ':w' => $weightKg,
            ':p' => $pushups,
            ':n' => $note ? (function_exists('mb_substr') ? mb_substr($note, 0, 300) : substr($note, 0, 300)) : null
        ]);
        return $st->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $st = $this->pdo->prepare("DELETE FROM entries WHERE id = :id");
        $st->execute([':id' => $id]);
        return $st->rowCount() > 0;
    }

    /** @return array<int,array<string,mixed>> Viimased N sissekannet õpilasele */
    public function getLastEntriesForStudent(int $studentId, int $limit = 5): array
    {
        $st = $this->pdo->prepare(
            "SELECT id, entry_date, weight_kg, pushups, note
             FROM entries WHERE student_user_id = :sid
             ORDER BY entry_date DESC LIMIT " . (int)$limit
        );
        $st->execute([':sid' => $studentId]);
        return $st->fetchAll();
    }
}
