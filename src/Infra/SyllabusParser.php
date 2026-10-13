<?php

declare(strict_types=1);

namespace Suisen\Infra;

/**
 * 高専 Web シラバスの詳細ページの HTML から、概要と週ごとの授業内容を取り出す。
 * ページの形が変わったらここだけを直す。
 */
final class SyllabusParser
{
    private const SUMMARY_LABEL = '概要:';
    private const PLAN_START = '授業計画';
    private const PLAN_END = 'モデルコアカリキュラム';
    private const WEEK_PATTERN = '/^\d+週$/u';
    /** 表の区切りの文字（例：1stQ、2ndQ、前期、後期）。授業内容が空の週のあとに来る */
    private const TERM_DIVIDER_PATTERN = '/^(\d(st|nd|rd|th)Q|前期|後期)$/u';

    /** @return array{summary: string, weekly_topics: list<string>} */
    public static function parse(string $html): array
    {
        $lines = self::textLines($html);
        return [
            'summary'       => self::summaryOf($lines),
            'weekly_topics' => self::weeklyTopicsOf($lines),
        ];
    }

    /** @return list<string> HTML の文字を、空でない行に分けたもの */
    private static function textLines(string $html): array
    {
        $withoutCode = (string) preg_replace('#<(script|style)\b.*?</\1>#is', '', $html);
        $text = html_entity_decode((string) preg_replace('/<[^>]+>/', "\n", $withoutCode), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // trim() はバイト単位なので全角空白（U+3000）を渡すと日本語の文字を壊す。正規表現で除く
        $lines = array_map(static fn (string $line): string => (string) preg_replace('/^[\s\x{3000}]+|[\s\x{3000}]+$/u', '', $line), explode("\n", $text));
        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    /**
     * 「概要:」の次の行から、次の見出し（「〜:」で終わる行）の前までをつなぐ。
     *
     * @param list<string> $lines
     */
    private static function summaryOf(array $lines): string
    {
        $start = array_search(self::SUMMARY_LABEL, $lines, true);
        if ($start === false) {
            return '';
        }
        $parts = [];
        for ($i = $start + 1; $i < count($lines) && !str_ends_with($lines[$i], ':'); $i++) {
            $parts[] = $lines[$i];
        }
        return implode('', $parts);
    }

    /** 「N週」の次の行が授業内容か。空の週は、次の行が次の週や表の終わりの見出しになる。 */
    private static function isTopic(string $line): bool
    {
        return preg_match(self::WEEK_PATTERN, $line) !== 1
            && preg_match(self::TERM_DIVIDER_PATTERN, $line) !== 1
            && !str_contains($line, self::PLAN_END);
    }

    /**
     * 「授業計画」から「モデルコアカリキュラム」までの間で、「N週」の次の行を集める。
     *
     * @param list<string> $lines
     * @return list<string>
     */
    private static function weeklyTopicsOf(array $lines): array
    {
        $start = array_search(self::PLAN_START, $lines, true);
        if ($start === false) {
            return [];
        }
        $topics = [];
        for ($i = $start + 1; $i < count($lines) - 1; $i++) {
            if (str_contains($lines[$i], self::PLAN_END)) {
                break;
            }
            if (preg_match(self::WEEK_PATTERN, $lines[$i]) === 1 && self::isTopic($lines[$i + 1])) {
                $topics[] = $lines[$i + 1];
            }
        }
        return $topics;
    }
}
