<?php

declare(strict_types=1);

namespace Suisen\Http;

use Suisen\App;
use Suisen\AppException;
use Throwable;

/**
 * API の共通の手順：POST か、CSRF トークン、ログイン、本文の読み取り、エラーの変換。
 * 各 API は「何をするか」だけを $action に書く。
 */
final class ApiHandler
{
    private const CSRF_HEADER = 'HTTP_X_CSRF_TOKEN';

    /**
     * @param callable(array<string, mixed> $body, ?int $userId): ?array $action
     */
    public static function run(App $app, bool $requiresLogin, callable $action): never
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new AppException('method_not_allowed', 405);
            }
            if (!$app->session->isValidCsrfToken($_SERVER[self::CSRF_HEADER] ?? null)) {
                throw new AppException('invalid_csrf', 403);
            }
            $userId = $requiresLogin
                ? ($app->session->userId() ?? throw new AppException('not_logged_in', 401))
                : null;

            JsonResponse::ok($action(self::readBody(), $userId) ?? []);
        } catch (AppException $e) {
            JsonResponse::error($e->httpStatus, $e->errorCode, $app->policy->message($e->errorCode, $e->params));
        } catch (Throwable $e) {
            error_log((string) $e);
            JsonResponse::error(500, 'server_error', $app->policy->message('server_error'));
        }
    }

    /**
     * 読むだけの API（GET）。何も変えないので CSRF トークンは確かめない。
     *
     * @param callable(?int $userId): array $action
     */
    public static function runRead(App $app, bool $requiresLogin, callable $action): never
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
                throw new AppException('method_not_allowed', 405);
            }
            $userId = $requiresLogin
                ? ($app->session->userId() ?? throw new AppException('not_logged_in', 401))
                : null;

            JsonResponse::ok($action($userId));
        } catch (AppException $e) {
            JsonResponse::error($e->httpStatus, $e->errorCode, $app->policy->message($e->errorCode, $e->params));
        } catch (Throwable $e) {
            error_log((string) $e);
            JsonResponse::error(500, 'server_error', $app->policy->message('server_error'));
        }
    }

    /** 本文の文字列の値。文字列でなければ空文字にする。 */
    public static function string(array $body, string $name): string
    {
        return is_string($body[$name] ?? null) ? $body[$name] : '';
    }

    /** 本文の整数の値。整数でなければ0にする。 */
    public static function int(array $body, string $name): int
    {
        $value = filter_var($body[$name] ?? null, FILTER_VALIDATE_INT);
        return $value === false ? 0 : $value;
    }

    /** @return array<string, mixed> */
    private static function readBody(): array
    {
        $body = json_decode((string) file_get_contents('php://input'), true);
        return is_array($body) ? $body : [];
    }
}
