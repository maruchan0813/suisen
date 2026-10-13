<?php

// コースと先輩のデータを DB に入れる。何度動かしても同じ状態になる。
// 使い方：php database/seed.php
// 先に database/schema.sql で表を作り、config/env.php を用意しておく。
// MAMP・XAMPP の PHP で動かす（例：/Applications/MAMP/bin/php/php8.x.x/bin/php database/seed.php）。

declare(strict_types=1);

use Suisen\Core\FeatureVector;
use Suisen\Infra\Csv;
use Suisen\Infra\Database;
use Suisen\Policy;

require __DIR__ . '/../vendor/autoload.php';

const SENIORS_FILE = 'data/seniors_test.csv';

$rootDir = dirname(__DIR__);
$env = require $rootDir . '/config/env.php';
$policy = Policy::load($rootDir . '/policy');
date_default_timezone_set($policy->string('TIMEZONE'));
$featureKeys = $policy->featureKeys();
$db = Database::connect($env);

$courses = Csv::read($rootDir . '/data/courses.csv');
$seniors = Csv::read($rootDir . '/' . SENIORS_FILE);

$db->transaction(function () use ($db, $courses, $seniors, $featureKeys): void {
    $courseIdByCode = seedCourses($db, $courses, $featureKeys);
    seedSeniors($db, $seniors, $featureKeys, $courseIdByCode);
});

echo sprintf("コース %d 件、先輩 %d 件を入れました。\n", count($courses), count($seniors));

/**
 * コースは診断結果から参照されるので、消さずに上書きする。
 *
 * @param list<array<string, string>> $courses
 * @param list<string> $featureKeys
 * @return array<string, int> code => id
 */
function seedCourses(Database $db, array $courses, array $featureKeys): array
{
    $statement = $db->pdo()->prepare(
        'INSERT INTO courses (id, code, name, features, display_order) VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE code = VALUES(code), name = VALUES(name),
                                 features = VALUES(features), display_order = VALUES(display_order)',
    );
    $idByCode = [];
    foreach ($courses as $course) {
        $statement->execute([
            (int) $course['id'],
            $course['code'],
            $course['name'],
            featuresJson($course, $featureKeys),
            (int) $course['display_order'],
        ]);
        $idByCode[$course['code']] = (int) $course['id'];
    }
    return $idByCode;
}

/**
 * 先輩は入れ直す。実データに差し替えるときは、同じ形の CSV を SENIORS_FILE に指定し、
 * is_test_data を false にして入れる。
 *
 * @param list<array<string, string>> $seniors
 * @param list<string> $featureKeys
 * @param array<string, int> $courseIdByCode
 */
function seedSeniors(Database $db, array $seniors, array $featureKeys, array $courseIdByCode): void
{
    $db->pdo()->exec('DELETE FROM seniors');
    $statement = $db->pdo()->prepare(
        'INSERT INTO seniors (course_id, features, career_type, destination, is_test_data) VALUES (?, ?, ?, ?, TRUE)',
    );
    foreach ($seniors as $senior) {
        $courseId = $courseIdByCode[$senior['course_code']]
            ?? throw new RuntimeException("コース {$senior['course_code']} が data/courses.csv にありません。");
        $statement->execute([$courseId, featuresJson($senior, $featureKeys), $senior['career_type'], $senior['destination']]);
    }
}

/**
 * @param array<string, string> $row
 * @param list<string> $featureKeys
 */
function featuresJson(array $row, array $featureKeys): string
{
    return json_encode(FeatureVector::fromNumbers($row, $featureKeys)->toArray(), JSON_THROW_ON_ERROR);
}
