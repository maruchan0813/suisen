<?php

declare(strict_types=1);

namespace Suisen\Infra;

use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * MySQL への接続を1つだけ持ち、使い回す。
 */
final class Database
{
    public const DATETIME_FORMAT = 'Y-m-d H:i:s';

    private function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $env config/env.php の値 */
    public static function connect(array $env): self
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $env['DB_HOST'],
            $env['DB_PORT'],
            $env['DB_NAME'],
        );
        $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // PHP と同じタイムゾーンにそろえる（MAMP と XAMPP で既定が違っても、時刻がずれないように）
        $pdo->prepare('SET time_zone = ?')->execute([(new DateTimeImmutable())->format('P')]);

        return new self($pdo);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * $work の中の書き込みを、すべて成功したときだけ確定する。
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function transaction(callable $work): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public static function formatDateTime(DateTimeImmutable $dateTime): string
    {
        return $dateTime->format(self::DATETIME_FORMAT);
    }
}
