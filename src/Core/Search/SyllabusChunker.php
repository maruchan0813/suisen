<?php

declare(strict_types=1);

namespace Suisen\Core\Search;

/**
 * 1つの科目を、決まった文字数以下のチャンクに分ける。
 * - 概要チャンク：見出し＋概要
 * - 授業計画チャンク：見出し＋週ごとの授業内容。長いときは週の切れ目で分ける
 * 見出しを本文にも入れるのは、科目名やコース名でも見つかるようにするため。
 */
final class SyllabusChunker
{
    public const SUMMARY_SUFFIX = '#summary';
    private const TOPIC_SEPARATOR = '、';

    public function __construct(private readonly int $maxChars)
    {
    }

    /**
     * @param list<string> $courseCodes
     * @param list<string> $weeklyTopics
     * @return list<Chunk>
     */
    public function chunk(
        string $subjectKey,
        string $title,
        string $label,
        array $courseCodes,
        string $summary,
        array $weeklyTopics,
        bool $sharedByAllCourses = false,
        ?int $grade = null,
    ): array {
        $heading = "{$label}／{$title}";
        $make = fn (string $suffix, string $body): Chunk => new Chunk(
            "{$subjectKey}#{$suffix}",
            $subjectKey,
            $title,
            $label,
            $courseCodes,
            $this->fit("{$heading}\n{$body}"),
            $sharedByAllCourses,
            $grade,
        );

        $chunks = [$make(ltrim(self::SUMMARY_SUFFIX, '#'), $summary === '' ? '' : "概要：{$summary}")];
        foreach ($this->groupTopics($heading, $weeklyTopics) as $i => $topics) {
            $chunks[] = $make('plan' . ($i + 1), '授業計画：' . implode(self::TOPIC_SEPARATOR, $topics));
        }
        return $chunks;
    }

    /**
     * 見出しと合わせて最大の文字数に収まるように、週の内容をまとめる。
     *
     * @param list<string> $weeklyTopics
     * @return list<list<string>>
     */
    private function groupTopics(string $heading, array $weeklyTopics): array
    {
        $budget = $this->maxChars - mb_strlen("{$heading}\n授業計画：");
        $groups = [];
        $current = [];
        $length = 0;
        foreach (array_values(array_unique(array_filter($weeklyTopics, static fn (string $topic): bool => $topic !== ''))) as $topic) {
            $added = mb_strlen($topic) + ($current === [] ? 0 : mb_strlen(self::TOPIC_SEPARATOR));
            if ($current !== [] && $length + $added > $budget) {
                $groups[] = $current;
                $current = [];
                $length = 0;
                $added = mb_strlen($topic);
            }
            $current[] = $topic;
            $length += $added;
        }
        if ($current !== []) {
            $groups[] = $current;
        }
        return $groups;
    }

    /** 1つの週の内容だけで長すぎるときなどに、最大の文字数で切る。 */
    private function fit(string $text): string
    {
        return mb_strlen($text) > $this->maxChars ? mb_substr($text, 0, $this->maxChars) : $text;
    }
}
