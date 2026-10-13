<?php

// 高専Webシラバス（公開ページ）から、5コースの科目の詳細ページを取得する。
// 使い方：php database/fetch_syllabus.php
//
// - 科目の一覧（data/syllabus_raw/subjects_<学科ID>.html）にあるリンクをそのまま使う。
//   詳細ページの year はカリキュラムの年度なので、自分で組み立てると 500 エラーになる（vault の教訓）。
// - すでにある詳細ページは取得しない。相手のサーバーに負担をかけないよう、1件ごとに間隔をあける。

declare(strict_types=1);

const BASE_URL = 'https://syllabus.kosen-k.go.jp';
const COURSE_DEPARTMENT_IDS = [31, 32, 33, 34, 35];
const WAIT_SECONDS = 1;
const TIMEOUT_SECONDS = 30;
const RETRY_COUNT = 3;          // 続けて取得するとサーバーが一時的にエラーを返すことがあるので、待ってから取り直す
const RETRY_WAIT_SECONDS = 10;

$rawDir = dirname(__DIR__) . '/data/syllabus_raw';
$detailDir = $rawDir . '/detail';

$fetched = 0;
$skipped = 0;
$failed = [];
foreach (COURSE_DEPARTMENT_IDS as $departmentId) {
    foreach (detailLinksOf("{$rawDir}/subjects_{$departmentId}.html") as $subjectId => $path) {
        $file = sprintf('%s/%d_%s.html', $detailDir, $departmentId, $subjectId);
        if (is_file($file)) {
            $skipped++;
            continue;
        }
        $html = downloadWithRetry(BASE_URL . $path);
        if ($html === null) {
            $failed[] = "{$departmentId}_{$subjectId}";
        } else {
            file_put_contents($file, $html);
            $fetched++;
        }
        sleep(WAIT_SECONDS);
    }
}

echo "取得 {$fetched} 件、取得済み {$skipped} 件、失敗 " . count($failed) . " 件\n";
if ($failed !== []) {
    echo '失敗：' . implode(', ', $failed) . "\n";
}

/** @return array<string, string> 科目番号 => 詳細ページのパス */
function detailLinksOf(string $listFile): array
{
    preg_match_all('#href="(/Pages/PublicSyllabus\?[^"]*subject_id=(\d+)[^"]*)"#', (string) file_get_contents($listFile), $matches, PREG_SET_ORDER);
    $links = [];
    foreach ($matches as $match) {
        $links[$match[2]] = html_entity_decode($match[1]);
    }
    return $links;
}

function downloadWithRetry(string $url): ?string
{
    for ($attempt = 0; $attempt <= RETRY_COUNT; $attempt++) {
        if ($attempt > 0) {
            sleep(RETRY_WAIT_SECONDS);
        }
        $html = download($url);
        if ($html !== null) {
            return $html;
        }
    }
    return null;
}

function download(string $url): ?string
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => TIMEOUT_SECONDS,
        CURLOPT_USERAGENT      => 'SUISEN syllabus fetcher (Kitakyushu KOSEN student project)',
    ]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    return ($body === false || $status !== 200) ? null : (string) $body;
}
