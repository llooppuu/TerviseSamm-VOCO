<?php
/**
 * Seed script: genereerib paroolid ja uuendab users tabeli.
 * Käivita: php database/seed.php
 * Default parool kõigile: secret
 */

declare(strict_types=1);

function loadEnvFile(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

$rootDir = dirname(__DIR__);
loadEnvFile($rootDir . '/config/.env');

$pass = 'secret';
$hash = password_hash($pass, PASSWORD_DEFAULT);

$users = [
    ['ADMIN_TEACHER', 'Keka Boss', 'boss@school.ee'],
    ['TEACHER', 'Õpetaja Malle', 'malle@school.ee'],
    ['STUDENT', 'Õpilane Jüri', 'jyri@school.ee'],
];

echo "Parool kõigile: {$pass}\n";
echo "Hash: {$hash}\n\n";

$dsn = getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=vormipaevik;charset=utf8mb4';
$user = getenv('DB_USER') ?: 'root';
$pwd = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO($dsn, $user, $pwd, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $st = $pdo->prepare(
        'INSERT INTO users (role, name, username, password_hash) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), name = VALUES(name)'
    );

    foreach ($users as [$role, $name, $username]) {
        $st->execute([$role, $name, $username, $hash]);
        echo "OK: {$username} ({$role})\n";
    }

    $studentId = $pdo->query("SELECT id FROM users WHERE username = 'jyri@school.ee'")->fetchColumn();
    $groupIds = $pdo->query("SELECT id FROM `groups` WHERE code IN ('ITA25','ITS25')")->fetchAll(PDO::FETCH_COLUMN);
    $adminId = $pdo->query("SELECT id FROM users WHERE role = 'ADMIN_TEACHER' LIMIT 1")->fetchColumn();
    $malleId = $pdo->query("SELECT id FROM users WHERE username = 'malle@school.ee'")->fetchColumn();

    if ($studentId && $adminId) {
        $ins = $pdo->prepare('INSERT IGNORE INTO group_students (group_id, student_user_id) VALUES (?, ?)');
        foreach ($groupIds as $gid) {
            $ins->execute([$gid, $studentId]);
        }
        echo "Õpilane Jüri lisatud rühmadesse.\n";

        $insAcc = $pdo->prepare('INSERT IGNORE INTO teacher_group_access (teacher_user_id, group_id, granted_by_user_id) VALUES (?, ?, ?)');
        foreach ($groupIds as $gid) {
            $insAcc->execute([$malleId, $gid, $adminId]);
        }
        echo "Õpetaja Malle saab ligipääsu ITA25, ITS25.\n";
    }

    echo "\nSeed valmis.\n";
} catch (PDOException $e) {
    $msg = $e->getMessage();
    echo "Viga: {$msg}\n";
    if (stripos($msg, 'could not find driver') !== false) {
        echo "Puudub PDO MySQL driver (pdo_mysql).\n";
        echo "Debian/Ubuntu: sudo apt install php-mysql\n";
    }
    echo "Enne seedit käivita: mysql -u root -p < database/init.sql\n";
    exit(1);
}
