<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class EntryRepo
{
    public function __construct(private PDO $pdo) {}

    /** @return array<int,array<string,mixed>> */
    public function listForStudent(
        int $studentId,
        string $from,
        string $to,
        ?int $activityId = null,
        ?string $activitySearch = null
    ): array
    {
        $sql = "SELECT e.id, e.entry_date, e.weight_kg, e.pushups, e.note, e.activity_id, a.name AS activity_name, af.feedback_text
                FROM entries e
                LEFT JOIN activities a ON a.id = e.activity_id
                LEFT JOIN ai_feedback af ON af.entry_id = e.id
                WHERE e.student_user_id = :sid
                  AND e.entry_date BETWEEN :from AND :to";
        $params = [':sid' => $studentId, ':from' => $from, ':to' => $to];

        if ($activityId !== null && $activityId > 0) {
            $sql .= " AND e.activity_id = :activity_id";
            $params[':activity_id'] = $activityId;
        }

        $activitySearch = trim((string)$activitySearch);
        if ($activitySearch !== '') {
            $sql .= " AND a.name LIKE :activity_q";
            $params[':activity_q'] = '%' . $activitySearch . '%';
        }

        $sql .= " ORDER BY e.entry_date DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT e.*, a.name AS activity_name
             FROM entries e
             LEFT JOIN activities a ON a.id = e.activity_id
             WHERE e.id = :id
             LIMIT 1"
        );
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

    public function create(
        int $studentId,
        string $entryDate,
        ?float $weightKg,
        ?int $pushups,
        ?string $note,
        ?int $activityId
    ): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO entries (student_user_id, entry_date, weight_kg, pushups, note, activity_id)
             VALUES (:sid, :d, :w, :p, :n, :a)"
        );
        $st->execute([
            ':sid' => $studentId,
            ':d' => $entryDate,
            ':w' => $weightKg,
            ':p' => $pushups,
            ':n' => $note ? (function_exists('mb_substr') ? mb_substr($note, 0, 300) : substr($note, 0, 300)) : null,
            ':a' => $activityId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, ?float $weightKg, ?int $pushups, ?string $note, ?int $activityId): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE entries
             SET weight_kg = :w, pushups = :p, note = :n, activity_id = :a
             WHERE id = :id"
        );
        $st->execute([
            ':id' => $id,
            ':w' => $weightKg,
            ':p' => $pushups,
            ':n' => $note ? (function_exists('mb_substr') ? mb_substr($note, 0, 300) : substr($note, 0, 300)) : null,
            ':a' => $activityId,
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
            "SELECT e.id, e.entry_date, e.weight_kg, e.pushups, e.note, e.activity_id, a.name AS activity_name
             FROM entries e
             LEFT JOIN activities a ON a.id = e.activity_id
             WHERE e.student_user_id = :sid
             ORDER BY entry_date DESC LIMIT " . (int)$limit
        );
        $st->execute([':sid' => $studentId]);
        return $st->fetchAll();
    }
}
