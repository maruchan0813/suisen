<?php

declare(strict_types=1);

namespace Suisen\Core\Search;

/**
 * 文章を TF-IDF のベクトルにする。「文章 → ベクトル」はここだけで行う。
 * 埋め込み（意味でベクトルにする方法）に差し替えるときは、この役割の部品を足す。
 */
final class TfidfVectorizer
{
    /** @param array<string, float> $idf 語 => IDF */
    public function __construct(private readonly array $idf)
    {
    }

    /**
     * 文章の集まりから IDF を求める。
     *
     * @param list<string> $texts
     */
    public static function fit(array $texts): self
    {
        $documentFrequency = [];
        foreach ($texts as $text) {
            foreach (array_unique(TextTokenizer::tokenize($text)) as $term) {
                $documentFrequency[$term] = ($documentFrequency[$term] ?? 0) + 1;
            }
        }

        $documentCount = count($texts);
        $idf = [];
        foreach ($documentFrequency as $term => $frequency) {
            $idf[(string) $term] = log((1 + $documentCount) / (1 + $frequency)) + 1.0;
        }
        return new self($idf);
    }

    /**
     * 長さ1の TF-IDF ベクトル。IDF を知らない語は無視する。
     *
     * @return array<string, float>
     */
    public function transform(string $text): array
    {
        $vector = [];
        foreach (array_count_values(TextTokenizer::tokenize($text)) as $term => $count) {
            $term = (string) $term;
            if (isset($this->idf[$term])) {
                $vector[$term] = (1.0 + log($count)) * $this->idf[$term];
            }
        }
        return SparseVector::normalize($vector);
    }

    /** @return array<string, float> */
    public function idf(): array
    {
        return $this->idf;
    }
}
