<?php

// 先輩のテストデータ（data/seniors_test.csv）を作る。
// 使い方：php database/generate_test_seniors.php
//
// 実データが手に入らないので、コースの特徴から「そのコースに進みそうな先輩」を作る。
// 乱数の種を固定しているので、何度作っても同じデータになる（テストで結果を比べるため）。

declare(strict_types=1);

use Suisen\Core\Similarity;
use Suisen\Infra\Csv;
use Suisen\Policy;

require __DIR__ . '/../vendor/autoload.php';

const RANDOM_SEED = 20261006;
const SENIORS_PER_COURSE = 20;
const EMPLOYMENT_RATIO = 0.6;   // 就職：進学 ＝ 6：4
const NOISE_STANDARD_DEVIATION = 0.8;

$rootDir = dirname(__DIR__);
$policy = Policy::load($rootDir . '/policy');
$min = $policy->int('FEATURE_MIN');
$max = $policy->int('FEATURE_MAX');
$neutral = $policy->int('NEUTRAL_FEATURE_VALUE');
$interestKeys = $policy->featureKeys(Policy::GROUP_INTEREST);
$styleKeys = $policy->featureKeys(Policy::GROUP_STYLE);

$courses = Csv::read($rootDir . '/data/courses.csv');
$careerOptions = Csv::read($rootDir . '/data/career_options.csv');

mt_srand(RANDOM_SEED);

$styleAverages = [];
foreach ($styleKeys as $key) {
    $styleAverages[$key] = Similarity::mean(array_map(static fn (array $course): float => (float) $course[$key], $courses));
}
$largestStyleGap = max(array_map(
    static fn (array $course): float => max(array_map(
        static fn (string $key): float => abs((float) $course[$key] - $styleAverages[$key]),
        $styleKeys,
    )),
    $courses,
));

$rows = [];
foreach ($courses as $course) {
    $baseValues = baseValuesOf($course, $interestKeys, $styleKeys, $styleAverages, $largestStyleGap, $min, $max, $neutral);
    for ($i = 0; $i < SENIORS_PER_COURSE; $i++) {
        $careerType = randomUnit() < EMPLOYMENT_RATIO ? 'employment' : 'further_study';
        $row = ['course_code' => $course['code']];
        foreach ($baseValues as $key => $base) {
            $row[$key] = clampToScale((int) round($base + normalRandom() * NOISE_STANDARD_DEVIATION), $min, $max);
        }
        $row['career_type'] = $careerType;
        $row['destination'] = pickDestination($careerOptions, $course['code'], $careerType);
        $rows[] = $row;
    }
}

$header = ['course_code', ...$policy->featureKeys(), 'career_type', 'destination'];
Csv::write($rootDir . '/data/seniors_test.csv', $header, $rows);
echo count($rows) . " 件を data/seniors_test.csv に書きました。\n";

/**
 * 先輩の値の基準。
 * - 興味：そのコースで多く学ぶ項目ほど高い（最大の項目が上限、0の項目が下限）
 * - 学び方：5コースの平均より多い項目は高く、少ない項目は低い
 *
 * @param array<string, string> $course
 * @param list<string> $interestKeys
 * @param list<string> $styleKeys
 * @param array<string, float> $styleAverages
 * @return array<string, float>
 */
function baseValuesOf(
    array $course,
    array $interestKeys,
    array $styleKeys,
    array $styleAverages,
    float $largestStyleGap,
    int $min,
    int $max,
    int $neutral,
): array {
    $largestInterest = max(array_map(static fn (string $key): float => (float) $course[$key], $interestKeys));
    $values = [];
    foreach ($interestKeys as $key) {
        $values[$key] = $min + ($max - $min) * ((float) $course[$key] / $largestInterest);
    }
    foreach ($styleKeys as $key) {
        $values[$key] = $neutral + ($max - $neutral) * (((float) $course[$key] - $styleAverages[$key]) / $largestStyleGap);
    }
    return $values;
}

/** @param list<array<string, string>> $careerOptions */
function pickDestination(array $careerOptions, string $courseCode, string $careerType): string
{
    $candidates = array_values(array_filter(
        $careerOptions,
        static fn (array $option): bool => $option['course_code'] === $courseCode && $option['career_type'] === $careerType,
    ));
    if ($candidates === []) {
        throw new RuntimeException("{$courseCode} の {$careerType} の進路の候補が data/career_options.csv にありません。");
    }
    return $candidates[mt_rand(0, count($candidates) - 1)]['destination'];
}

function clampToScale(int $value, int $min, int $max): int
{
    return max($min, min($max, $value));
}

/** 0以上1未満の乱数。 */
function randomUnit(): float
{
    return mt_rand() / (mt_getrandmax() + 1);
}

/** 平均0・標準偏差1の正規分布の乱数（ボックス＝ミュラー法）。 */
function normalRandom(): float
{
    $u1 = 1.0 - randomUnit(); // 0 を避ける（log(0) を計算しないため）
    $u2 = randomUnit();
    return sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
}
