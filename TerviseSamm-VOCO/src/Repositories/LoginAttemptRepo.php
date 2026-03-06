<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class LoginAttemptRepo
{
    private const WINDOW_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function __construct(private PDO $pdo) {}

    public function recordAttempt(string $ip, string $username): void
    {
        $st = $this->pdo->prepare(
            "INSERT INTO login_attempts (ip, username) VALUES (:ip, :u)"
        );
        $st->execute([':ip' => $ip, ':u' => $username]);
    }

    public function getAttemptCount(string $ip, string $username): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . self::WINDOW_MINUTES . ' minutes'));
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND username = :u AND created_at > :cutoff"
        );
        $st->execute([':ip' => $ip, ':u' => $username, ':cutoff' => $cutoff]);
        return (int) $st->fetchColumn();
    }

    public function isRateLimited(string $ip, string $username): bool
    {
        return $this->getAttemptCount($ip, $username) >= self::MAX_ATTEMPTS;
    }

    public function clearOldAttempts(): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $this->pdo->prepare("DELETE FROM login_attempts WHERE created_at < ?")->execute([$cutoff]);
    }
}
