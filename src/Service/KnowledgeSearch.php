<?php

declare(strict_types=1);

namespace Suisen\Service;

use Suisen\Core\Search\SearchHit;
use Suisen\Core\Search\TfidfVectorizer;
use Suisen\Core\Search\VectorIndex;

/**
 * 資料（シラバス）の検索の窓口。チャットと診断の両方で使う。
 */
final class KnowledgeSearch
{
    public function __construct(
        private readonly TfidfVectorizer $vectorizer,
        private readonly VectorIndex $index,
        private readonly float $minScore,
    ) {
    }

    /**
     * @param string|null $courseCode このコースに関わる資料だけを探す（null なら全コース）
     * @param bool $includeShared 全コース共通の科目も探すか（「このコースの授業」として挙げるときは false）
     * @param list<int> $grades この学年の授業だけを探す（空なら全学年）
     * @return list<SearchHit>
     */
    public function search(string $query, ?string $courseCode, int $limit, bool $includeShared = true, array $grades = []): array
    {
        $queryVector = $this->vectorizer->transform($query);
        if ($queryVector === []) {
            return [];
        }
        return $this->index->search($queryVector, $courseCode, $limit, $this->minScore, $includeShared, $grades);
    }

    /**
     * コースと学年で絞り込んだ授業の一覧を、LLM に渡す文章にする。
     * 言葉の近さで探す検索は「3年の授業を全部」のような質問が苦手なので、表から絞り込んで渡す。
     *
     * @param list<int> $grades
     */
    public function subjectListText(?string $courseCode, array $grades, int $maxSubjects, int $summaryChars): string
    {
        $lines = [];
        foreach (array_slice($this->index->subjectsOf($courseCode, $grades), 0, $maxSubjects) as $chunk) {
            $summary = trim((string) preg_replace('/^.*?概要：/su', '', $chunk->text));
            $lines[] = '- ' . $chunk->heading() . ($summary === '' || !str_contains($chunk->text, '概要：') ? '' : '：' . mb_substr($summary, 0, $summaryChars));
        }
        return implode("\n", $lines);
    }

    /** @return list<string> 索引にある授業名（返事の見直しで、実在する授業かを確かめるため） */
    public function subjectTitles(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $entry): string => $entry['chunk']->title,
            $this->index->entries(),
        )));
    }

    /**
     * LLM に渡す参考資料の文章。見つからなければ空文字。
     *
     * @param list<SearchHit> $hits
     */
    public static function referenceText(array $hits): string
    {
        $lines = [];
        foreach (array_values($hits) as $i => $hit) {
            $lines[] = sprintf('[%d] %s', $i + 1, $hit->chunk->text);
        }
        return implode("\n\n", $lines);
    }

    /**
     * 画面に出す、参考にした科目の見出し。
     *
     * @param list<SearchHit> $hits
     * @return list<string>
     */
    public static function headings(array $hits): array
    {
        return array_values(array_unique(array_map(static fn (SearchHit $hit): string => $hit->chunk->heading(), $hits)));
    }
}
