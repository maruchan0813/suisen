<?php

declare(strict_types=1);

namespace Suisen\Repository;

use RuntimeException;
use Suisen\Core\Search\Chunk;
use Suisen\Core\Search\TfidfVectorizer;
use Suisen\Core\Search\VectorIndex;

/**
 * 検索の索引をファイルに保存する・読む。
 * 索引はシラバスから何度でも作り直せるので、DB ではなくファイルに置く。
 */
final class SearchIndexRepository
{
    public const METHOD = 'tfidf-char-bigram';

    public function __construct(private readonly string $file)
    {
    }

    public function save(TfidfVectorizer $vectorizer, VectorIndex $index): void
    {
        $chunks = array_map(static fn (array $entry): array => [
            'id'           => $entry['chunk']->id,
            'subject_key'  => $entry['chunk']->subjectKey,
            'title'        => $entry['chunk']->title,
            'label'        => $entry['chunk']->label,
            'course_codes' => $entry['chunk']->courseCodes,
            'text'         => $entry['chunk']->text,
            'shared'       => $entry['chunk']->sharedByAllCourses,
            'grade'        => $entry['chunk']->grade,
            'vector'       => self::rounded($entry['vector']),
        ], $index->entries());

        $json = json_encode(
            ['method' => self::METHOD, 'idf' => self::rounded($vectorizer->idf()), 'chunks' => $chunks],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        if (file_put_contents($this->file, $json) === false) {
            throw new RuntimeException("{$this->file} に書けませんでした。");
        }
    }

    /** @return array{vectorizer: TfidfVectorizer, index: VectorIndex} */
    public function load(): array
    {
        if (!is_file($this->file)) {
            throw new RuntimeException("{$this->file} がありません。php database/build_search_index.php で作ってください。");
        }
        $data = json_decode((string) file_get_contents($this->file), true, flags: JSON_THROW_ON_ERROR);
        if (($data['method'] ?? null) !== self::METHOD) {
            throw new RuntimeException("{$this->file} の作り方（method）が違います。索引を作り直してください。");
        }

        $entries = array_map(static fn (array $chunk): array => [
            'chunk' => new Chunk(
                $chunk['id'],
                $chunk['subject_key'],
                $chunk['title'],
                $chunk['label'],
                $chunk['course_codes'],
                $chunk['text'],
                $chunk['shared'] ?? false,
                $chunk['grade'] ?? null,
            ),
            'vector' => self::floats($chunk['vector']),
        ], $data['chunks']);

        return [
            'vectorizer' => new TfidfVectorizer(self::floats($data['idf'])),
            'index'      => new VectorIndex($entries),
        ];
    }

    /**
     * ファイルを小さくするため、小数を丸める。
     *
     * @param array<string, float> $vector
     * @return array<string, float>
     */
    private static function rounded(array $vector): array
    {
        return array_map(static fn (float $value): float => round($value, 5), $vector);
    }

    /**
     * JSON から読んだ値を float にそろえる（語が数字だけのときキーが int になるのも文字列に戻す）。
     *
     * @param array<array-key, int|float> $vector
     * @return array<string, float>
     */
    private static function floats(array $vector): array
    {
        $floats = [];
        foreach ($vector as $term => $value) {
            $floats[(string) $term] = (float) $value;
        }
        return $floats;
    }
}
