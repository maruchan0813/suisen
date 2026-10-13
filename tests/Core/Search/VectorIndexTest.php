<?php

declare(strict_types=1);

namespace Suisen\Tests\Core\Search;

use PHPUnit\Framework\TestCase;
use Suisen\Core\Search\Chunk;
use Suisen\Core\Search\SearchHit;
use Suisen\Core\Search\TfidfVectorizer;
use Suisen\Core\Search\VectorIndex;

final class VectorIndexTest extends TestCase
{
    private TfidfVectorizer $vectorizer;
    private VectorIndex $index;

    protected function setUp(): void
    {
        $chunks = [
            new Chunk('db#summary', 'db', 'データベース', '情報', ['information'], 'データベース SQL の設計', false, 5),
            new Chunk('db#plan1', 'db', 'データベース', '情報', ['information'], 'データベース 正規化 SQL'),
            new Chunk('chem#summary', 'chem', '有機化学', '化学', ['chemistry'], '有機化学 反応', false, 3),
            new Chunk('lit#summary', 'lit', '情報リテラシー', '共通', ['information', 'chemistry'], '情報 リテラシー SQL 入門', true, 1),
        ];
        $this->vectorizer = TfidfVectorizer::fit(array_map(static fn (Chunk $chunk): string => $chunk->text, $chunks));
        $this->index = new VectorIndex(array_map(
            fn (Chunk $chunk): array => ['chunk' => $chunk, 'vector' => $this->vectorizer->transform($chunk->text)],
            $chunks,
        ));
    }

    public function testKeepsOnlyBestChunkPerSubject(): void
    {
        $ids = $this->search('データベース SQL', null);

        self::assertSame(1, count(array_filter($ids, static fn (string $id): bool => str_starts_with($id, 'db#'))));
        self::assertStringStartsWith('db#', $ids[0]);
    }

    public function testFiltersByCourseIncludingSharedSubjects(): void
    {
        $ids = $this->search('SQL', 'chemistry');

        self::assertSame(['lit#summary'], $ids);
    }

    public function testCanExcludeSubjectsSharedByAllCourses(): void
    {
        $hits = $this->index->search($this->vectorizer->transform('SQL'), 'chemistry', 10, 0.01, includeShared: false);

        self::assertSame([], $hits);
    }

    public function testListsSubjectsByCourseAndGrade(): void
    {
        $titles = static fn (array $chunks): array => array_map(static fn (Chunk $chunk): string => $chunk->title, $chunks);

        self::assertSame(['データベース'], $titles($this->index->subjectsOf('information', [5])));
        self::assertSame(['情報リテラシー', 'データベース'], $titles($this->index->subjectsOf('information', [])));
        self::assertSame([], $titles($this->index->subjectsOf('chemistry', [5])));
    }

    public function testSearchCanFilterByGrade(): void
    {
        $hits = $this->index->search($this->vectorizer->transform('SQL'), null, 10, 0.01, true, [1]);

        self::assertSame(['lit#summary'], array_map(static fn (SearchHit $hit): string => $hit->chunk->id, $hits));
    }

    public function testDropsHitsBelowMinimumScore(): void
    {
        $hits = $this->index->search($this->vectorizer->transform('有機化学'), null, 10, 0.99);

        self::assertSame([], $hits);
    }

    /** @return list<string> */
    private function search(string $query, ?string $courseCode): array
    {
        return array_map(
            static fn (SearchHit $hit): string => $hit->chunk->id,
            $this->index->search($this->vectorizer->transform($query), $courseCode, 10, 0.01),
        );
    }
}
