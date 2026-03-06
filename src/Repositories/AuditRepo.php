<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AuditRepo
{
    public function __construct(private PDO $pdo) {}

    public function log(?int $actorUserId, string $action, ?string $entityType = null, ?int $entityId = null): void
    {
        $st = $this->pdo->prepare(
            "INSERT INTO audit_log (actor_user_id, action, entity_type, entity_id, ip_address, user_agent)
             VALUES (:actor, :action, :etype, :eid, :ip, :ua)"
        );
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $st->execute([
            ':actor' => $actorUserId,
            ':action' => $action,
            ':etype' => $entityType,
            ':eid' => $entityId,
            ':ip' => $ip,
            ':ua' => $ua ? (function_exists('mb_substr') ? mb_substr($ua, 0, 255) : substr($ua, 0, 255)) : null
        ]);
    }
}
