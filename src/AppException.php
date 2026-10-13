<?php

declare(strict_types=1);

namespace Suisen;

use RuntimeException;
use Throwable;

/**
 * 利用者に伝えるエラー。$errorCode は policy/messages.php のキーで、画面の文言はそこから取る。
 */
final class AppException extends RuntimeException
{
    /** @param array<string, string|int|float> $params 文言に差し込む値 */
    public function __construct(
        public readonly string $errorCode,
        public readonly int $httpStatus = 400,
        public readonly array $params = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode, 0, $previous);
    }
}
