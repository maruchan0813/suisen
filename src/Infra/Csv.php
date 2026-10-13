<?php

declare(strict_types=1);

namespace Suisen\Infra;

use RuntimeException;

/**
 * 1行目を見出しにした CSV の読み書き。
 */
final class Csv
{
    /** @return list<array<string, string>> 見出しをキーにした行 */
    public static function read(string $path): array
    {
        $handle = self::open($path, 'r');
        $header = fgetcsv($handle, escape: '');
        if ($header === false) {
            throw new RuntimeException("{$path} が空です。");
        }
        $header[0] = preg_replace('/^\x{FEFF}/u', '', (string) $header[0]); // Excel で保存した CSV の BOM を除く

        $rows = [];
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if ($values === [null]) {
                continue; // 空行
            }
            $rows[] = array_combine($header, $values);
        }
        fclose($handle);
        return $rows;
    }

    /**
     * @param list<string> $header
     * @param list<array<string, string|int|float>> $rows
     */
    public static function write(string $path, array $header, array $rows): void
    {
        $handle = self::open($path, 'w');
        fputcsv($handle, $header, escape: '');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(static fn (string $column) => $row[$column], $header), escape: '');
        }
        fclose($handle);
    }

    /** @return resource */
    private static function open(string $path, string $mode)
    {
        $handle = fopen($path, $mode);
        if ($handle === false) {
            throw new RuntimeException("{$path} を開けません。");
        }
        return $handle;
    }
}
