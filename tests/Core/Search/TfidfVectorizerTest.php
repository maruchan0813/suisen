<?php

declare(strict_types=1);

namespace Suisen\Tests\Core\Search;

use PHPUnit\Framework\TestCase;
use Suisen\Core\Search\SparseVector;
use Suisen\Core\Search\TfidfVectorizer;

final class TfidfVectorizerTest extends TestCase
{
    public function testRareTermsWeighMoreThanCommonOnes(): void
    {
        $vectorizer = TfidfVectorizer::fit(['授業 化学', '授業 回路', '授業 機械']);

        self::assertGreaterThan($vectorizer->idf()['授業'], $vectorizer->idf()['化学']);
    }

    public function testVectorsHaveUnitLength(): void
    {
        $vector = TfidfVectorizer::fit(['回路設計', '化学実験'])->transform('回路設計の化学実験');

        self::assertEqualsWithDelta(1.0, SparseVector::dot($vector, $vector), 1e-9);
    }

    public function testUnknownTermsAreIgnored(): void
    {
        self::assertSame([], TfidfVectorizer::fit(['回路設計'])->transform('ロボット'));
    }

    public function testSimilarTextIsCloserThanUnrelatedText(): void
    {
        $vectorizer = TfidfVectorizer::fit(['電気回路の設計', '有機化学の実験', 'C言語のプログラミング']);
        $query = $vectorizer->transform('回路を設計したい');

        self::assertGreaterThan(
            SparseVector::dot($query, $vectorizer->transform('有機化学の実験')),
            SparseVector::dot($query, $vectorizer->transform('電気回路の設計')),
        );
    }
}
