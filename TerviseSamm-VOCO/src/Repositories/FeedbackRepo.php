<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class FeedbackRepo
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed>|null */
    public function getForEntry(int $entryId): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM ai_feedback WHERE entry_id = :eid LIMIT 1");
        $st->execute([':eid' => $entryId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function create(int $entryId, string $provider, string $model, string $text): void
    {
        $st = $this->pdo->prepare(
            "INSERT INTO ai_feedback (entry_id, provider, model, feedback_text) VALUES (:eid, :p, :m, :t)"
        );
        $st->execute([
            ':eid' => $entryId,
            ':p' => $provider,
            ':m' => $model,
            ':t' => $text,
        ]);
    }

    public function upsert(int $entryId, string $provider, string $model, string $text): void
    {
        $st = $this->pdo->prepare(
            "INSERT INTO ai_feedback (entry_id, provider, model, feedback_text)
             VALUES (:eid, :p, :m, :t)
             ON DUPLICATE KEY UPDATE provider = VALUES(provider), model = VALUES(model), feedback_text = VALUES(feedback_text)"
        );
        $st->execute([
            ':eid' => $entryId,
            ':p' => $provider,
            ':m' => $model,
            ':t' => $text,
        ]);
    }
}
