<?php

declare(strict_types=1);

namespace Suisen\Core;

use InvalidArgumentException;

/**
 * 13項目の値をキー付きで持つ。どの項目があるかは呼ぶ側（方針）が決める。
 */
final class FeatureVector
{
    /** @param array<string, float> $values */
    private function __construct(private readonly array $values)
    {
    }

    /**
     * 信頼できない値（LLM の出力など）から作る。
     * 足りない項目や範囲外の値は $fallback にする。
     *
     * @param array<mixed> $raw
     * @param list<string> $keys
     */
    public static function sanitize(array $raw, array $keys, int $min, int $max, int $fallback): self
    {
        $values = [];
        foreach ($keys as $key) {
            $value = filter_var(
                $raw[$key] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => $min, 'max_range' => $max]],
            );
            $values[$key] = (float) ($value === false ? $fallback : $value);
        }
        return new self($values);
    }

    /**
     * 信頼できる値（DB や CSV のデータ）から作る。項目が足りなければ例外にする。
     *
     * @param array<mixed> $raw
     * @param list<string> $keys
     */
    public static function fromNumbers(array $raw, array $keys): self
    {
        $values = [];
        foreach ($keys as $key) {
            if (!isset($raw[$key]) || !is_numeric($raw[$key])) {
                throw new InvalidArgumentException("項目 {$key} の値がありません。");
            }
            $values[$key] = (float) $raw[$key];
        }
        return new self($values);
    }

    /**
     * 指定した項目の値を、その順に並べて返す。
     *
     * @param list<string> $keys
     * @return list<float>
     */
    public function pick(array $keys): array
    {
        return array_map(fn (string $key): float => $this->get($key), $keys);
    }

    public function get(string $key): float
    {
        if (!array_key_exists($key, $this->values)) {
            throw new InvalidArgumentException("項目 {$key} はありません。");
        }
        return $this->values[$key];
    }

    /** @return array<string, float> */
    public function toArray(): array
    {
        return $this->values;
    }
}
