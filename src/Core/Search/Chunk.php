<?php

declare(strict_types=1);

namespace Suisen\Core\Search;

/**
 * 検索の単位になる、資料の断片。
 */
final class Chunk
{
    /**
     * @param string $subjectKey 同じ科目のチャンクを見分けるためのキー
     * @param string $label 例：情報システムコース／3年後期／必修
     * @param list<string> $courseCodes このチャンクが関わるコース（共通科目は全コース）
     */
    public function __construct(
        public readonly string $id,
        public readonly string $subjectKey,
        public readonly string $title,
        public readonly string $label,
        public readonly array $courseCodes,
        public readonly string $text,
        public readonly bool $sharedByAllCourses = false,
        public readonly ?int $grade = null,
    ) {
    }

    public function isFor(string $courseCode): bool
    {
        return in_array($courseCode, $this->courseCodes, true);
    }

    /** @param list<int> $grades 空なら、どの学年でもよい */
    public function isInGrades(array $grades): bool
    {
        return $grades === [] || in_array($this->grade, $grades, true);
    }

    public function isSummary(): bool
    {
        return str_ends_with($this->id, SyllabusChunker::SUMMARY_SUFFIX);
    }

    /** 画面や LLM に出す見出し。例：「データベース」（情報システムコース／3年後期／必修） */
    public function heading(): string
    {
        return "「{$this->title}」（{$this->label}）";
    }
}
