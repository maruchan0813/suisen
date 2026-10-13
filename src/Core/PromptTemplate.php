<?php

declare(strict_types=1);

namespace Suisen\Core;

use InvalidArgumentException;

/**
 * 文章の中の {{名前}} を値に置き換える。プロンプトと画面の文言の両方で使う。
 */
final class PromptTemplate
{
    /** @param array<string, string|int|float> $variables */
    public static function render(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            static function (array $match) use ($variables): string {
                $name = $match[1];
                if (!array_key_exists($name, $variables)) {
                    throw new InvalidArgumentException("{{{$name}}} に入れる値がありません。");
                }
                return (string) $variables[$name];
            },
            $template,
        );
    }
}
