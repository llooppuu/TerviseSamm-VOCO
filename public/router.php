<?php
// PHP built-in server router: php -S localhost:8000 router.php
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $uri;
if ($uri === '/' || $uri === '') {
    header('Location: /login.html', true, 302);
    return true;
}
if (file_exists($file) && !is_dir($file)) {
    return false;
}
if (str_starts_with($uri, '/api/')) {
    require __DIR__ . '/index.php';
    return true;
}
if (file_exists(__DIR__ . $uri)) {
    return false;
}
require __DIR__ . '/index.php';
