<?php

declare(strict_types=1);

namespace Suisen\Http;

/**
 * API の返事の形をそろえる。
 * 成功：{ "ok": true, "data": {...} }
 * 失敗：{ "ok": false, "error": { "code": "...", "message": "..." } }
 */
final class JsonResponse
{
    /** @param array<string, mixed> $data */
    public static function ok(array $data = []): never
    {
        self::send(200, ['ok' => true, 'data' => (object) $data]);
    }

    public static function error(int $status, string $code, string $message): never
    {
        self::send($status, ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]);
    }

    /** @param array<string, mixed> $body */
    private static function send(int $status, array $body): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
