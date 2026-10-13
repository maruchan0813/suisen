<?php

// シラバスから検索の索引（data/search_index.json）を作る。
// 使い方：php database/build_search_index.php
// 先に php database/fetch_syllabus.php で詳細ページを取得しておく。

declare(strict_types=1);

use Suisen\App;
use Suisen\Core\Search\Chunk;
use Suisen\Core\Search\SyllabusChunker;
use Suisen\Core\Search\TfidfVectorizer;
use Suisen\Core\Search\VectorIndex;
use Suisen\Infra\Csv;
use Suisen\Infra\SyllabusParser;
use Suisen\Policy;
use Suisen\Repository\SearchIndexRepository;

require __DIR__ . '/../vendor/autoload.php';

const SPECIALIZED_SUBJECT = '専門';

$rootDir = dirname(__DIR__);
$policy = Policy::load($rootDir . '/policy');

$courses = Csv::read($rootDir . '/data/courses.csv');
$courseByDepartment = array_column($courses, null, 'department_id');
$subjects = array_filter(
    Csv::read($rootDir . '/data/syllabus/subjects_2026.csv'),
    static fn (array $row): bool => isset($courseByDepartment[$row['department_id']]) && $row['区分'] === SPECIALIZED_SUBJECT,
);

$excludedPhrases = $policy->list('RAG_EXCLUDED_SUMMARY_PHRASES');
$excludedSubjects = 0;
$missingDetails = 0;
$subjectsByContent = [];
foreach ($subjects as $row) {
    $course = $courseByDepartment[$row['department_id']];
    $detailFile = sprintf('%s/data/syllabus_raw/detail/%s_%s.html', $rootDir, $row['department_id'], $row['科目番号']);
    if (is_file($detailFile)) {
        $parsed = SyllabusParser::parse((string) file_get_contents($detailFile));
    } else {
        $parsed = ['summary' => '', 'weekly_topics' => []];
        $missingDetails++;
    }

    if (containsAny($parsed['summary'], $excludedPhrases)) {
        $excludedSubjects++;
        continue;
    }

    // 同じ科目名で中身もまったく同じもの（全コース共通の科目）は1つにまとめる
    $contentKey = md5($row['科目名'] . "\n" . $parsed['summary'] . "\n" . implode("\n", $parsed['weekly_topics']));
    $subjectsByContent[$contentKey] ??= [
        'key'           => "{$row['department_id']}_{$row['科目番号']}",
        'title'         => $row['科目名'],
        'term'          => termLabel($row['開講時期']),
        'required'      => $row['必修選択'],
        'grade'         => (int) $row['学年'],
        'course_codes'  => [],
        'summary'       => $parsed['summary'],
        'weekly_topics' => $parsed['weekly_topics'],
    ];
    $subjectsByContent[$contentKey]['course_codes'][] = $course['code'];
}

$courseNameByCode = array_column($courses, 'name', 'code');
$chunker = new SyllabusChunker($policy->int('RAG_CHUNK_MAX_CHARS'));
$chunks = [];
foreach ($subjectsByContent as $subject) {
    $courseCodes = array_values(array_unique($subject['course_codes']));
    $sharedByAllCourses = count($courseCodes) === count($courses);
    $courseLabel = $sharedByAllCourses ? '全コース共通' : implode('・', array_map(
        static fn (string $code): string => "{$courseNameByCode[$code]}コース",
        $courseCodes,
    ));
    array_push($chunks, ...$chunker->chunk(
        $subject['key'],
        $subject['title'],
        "{$courseLabel}／{$subject['term']}／{$subject['required']}",
        $courseCodes,
        $subject['summary'],
        $subject['weekly_topics'],
        $sharedByAllCourses,
        $subject['grade'],
    ));
}

$vectorizer = TfidfVectorizer::fit(array_map(static fn (Chunk $chunk): string => $chunk->text, $chunks));
$index = new VectorIndex(array_map(
    static fn (Chunk $chunk): array => ['chunk' => $chunk, 'vector' => $vectorizer->transform($chunk->text)],
    $chunks,
));
(new SearchIndexRepository($rootDir . '/' . App::SEARCH_INDEX_FILE))->save($vectorizer, $index);

printf(
    "科目 %d 件（除いた科目 %d 件、まとめた後 %d 件）からチャンク %d 件を作り、%s に保存しました。詳細ページがない科目：%d 件\n",
    count($subjects),
    $excludedSubjects,
    count($subjectsByContent),
    count($chunks),
    App::SEARCH_INDEX_FILE,
    $missingDetails,
);

/** @param list<string> $phrases */
function containsAny(string $text, array $phrases): bool
{
    foreach ($phrases as $phrase) {
        if (str_contains($text, $phrase)) {
            return true;
        }
    }
    return false;
}

/** 例：「4前」→「4年前期」、「4前/4後」→「4年通年」 */
function termLabel(string $term): string
{
    if (preg_match('/^(\d)前\/\d後$/u', $term, $match) === 1) {
        return "{$match[1]}年通年";
    }
    if (preg_match('/^(\d)(前|後)$/u', $term, $match) === 1) {
        return "{$match[1]}年{$match[2]}期";
    }
    return $term;
}
