<?php

declare(strict_types=1);

namespace Suisen\Core\Search;

/**
 * チャンクとそのベクトルを持ち、質問のベクトルに近い順に返す。
 */
final class VectorIndex
{
    /** @param list<array{chunk: Chunk, vector: array<string, float>}> $entries */
    public function __construct(private readonly array $entries)
    {
    }

    /**
     * 近い順に返す。同じ科目のチャンクは一番近い1つだけを残す（資料の幅を広げるため）。
     *
     * @param array<string, float> $queryVector 長さ1のベクトル
     * @param string|null $courseCode このコースに関わるチャンクだけを対象にする（null なら全部）
     * @param bool $includeShared 全コース共通の科目も対象にするか
     * @param list<int> $grades この学年のチャンクだけを対象にする（空なら全部）
     * @return list<SearchHit>
     */
    public function search(array $queryVector, ?string $courseCode, int $limit, float $minScore, bool $includeShared = true, array $grades = []): array
    {
        $hits = [];
        foreach ($this->entries as $entry) {
            if ($courseCode !== null && !$entry['chunk']->isFor($courseCode)) {
                continue;
            }
            if (!$includeShared && $entry['chunk']->sharedByAllCourses) {
                continue;
            }
            if (!$entry['chunk']->isInGrades($grades)) {
                continue;
            }
            $score = SparseVector::dot($queryVector, $entry['vector']);
            if ($score >= $minScore) {
                $hits[] = new SearchHit($entry['chunk'], $score);
            }
        }
        usort($hits, static fn (SearchHit $a, SearchHit $b): int => [$b->score, $a->chunk->id] <=> [$a->score, $b->chunk->id]);

        return array_slice(self::bestPerSubject($hits), 0, $limit);
    }

    /**
     * コースと学年で絞り込んだ授業の一覧（概要チャンク、学年→名前の順）。「3年の授業を教えて」のような質問に使う。
     *
     * @param list<int> $grades
     * @return list<Chunk>
     */
    public function subjectsOf(?string $courseCode, array $grades): array
    {
        $subjects = [];
        foreach ($this->entries as $entry) {
            $chunk = $entry['chunk'];
            if ($chunk->isSummary() && ($courseCode === null || $chunk->isFor($courseCode)) && $chunk->isInGrades($grades)) {
                $subjects[] = $chunk;
            }
        }
        usort($subjects, static fn (Chunk $a, Chunk $b): int => [$a->grade, $a->label, $a->title] <=> [$b->grade, $b->label, $b->title]);
        return $subjects;
    }

    /** @return list<array{chunk: Chunk, vector: array<string, float>}> */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @param list<SearchHit> $sortedHits
     * @return list<SearchHit>
     */
    private static function bestPerSubject(array $sortedHits): array
    {
        $seen = [];
        $best = [];
        foreach ($sortedHits as $hit) {
            if (isset($seen[$hit->chunk->subjectKey])) {
                continue;
            }
            $seen[$hit->chunk->subjectKey] = true;
            $best[] = $hit;
        }
        return $best;
    }
}
