<?php

declare(strict_types=1);

namespace Suisen\Service;

/**
 * LLM が考えた検索の計画と、学生のメッセージが質問かどうか。
 */
final class SearchPlan
{
    public function __construct(
        public readonly bool $needsSearch,
        public readonly string $query,
        public readonly ?string $courseCode,
        public readonly bool $studentAsked = false,
        /** @var list<int> 学生が指定した学年（「3年次の授業」なら [3]）。指定がなければ空 */
        public readonly array $grades = [],
    ) {
    }
}
