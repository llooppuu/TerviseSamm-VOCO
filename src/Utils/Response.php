<?php
declare(strict_types=1);

namespace App\Utils;

final class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $code, string $message, int $status = 400, array $fields = []): never
    {
        $payload = ['error' => ['code' => $code, 'message' => $message]];
        if (!empty($fields)) $payload['error']['fields'] = $fields;
        self::json($payload, $status);
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }
}
