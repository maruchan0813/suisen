<?php
// 方針の値。チームやクライアントの意見で変わる値はここにだけ書く。

return [
    'TIMEZONE' => 'Asia/Tokyo',

    // ログイン
    // false：メールアドレスを入れるだけでログインする（本人確認なし）。
    // true ：確認コードをメールで送り、受け取った本人だけがログインできる。Gmail の準備ができたら true にする。
    'EMAIL_VERIFICATION_ENABLED'         => true,
    'ALLOWED_EMAIL_DOMAIN'               => 'apps.kct.ac.jp',
    'LOGIN_CODE_DIGITS'                  => 6,
    'LOGIN_CODE_TTL_SECONDS'             => 600,
    'LOGIN_CODE_MAX_ATTEMPTS'            => 5,
    'LOGIN_CODE_RESEND_INTERVAL_SECONDS' => 60,
    'SESSION_LIFETIME_SECONDS'           => 604800,

    // チャット
    'MIN_TURNS_BEFORE_DIAGNOSIS' => 5,
    'MAX_TURNS_PER_CONVERSATION' => 20,
    'MAX_MESSAGE_LENGTH'         => 1000,
    // 返事の見直し：これ以上似ている質問は「繰り返し」とみなす（0〜1）。作り直しは何回までか
    'REPLY_REPEAT_SIMILARITY'    => 0.45,
    'REPLY_MAX_REGENERATIONS'    => 1,
    // 学生に言ってはいけない内部の言葉（指示文の言葉）。返事に出たら作り直させる
    'REPLY_FORBIDDEN_PHRASES'    => ['参考資料', '授業の一覧', 'コースの説明', '質問を1つ', '質問を一つ'],

    // 診断
    'INTEREST_WEIGHT'       => 0.7,
    'STYLE_WEIGHT'          => 0.3,
    'SIMILAR_SENIOR_COUNT'  => 3,
    'RECOMMENDATION_REASON_COUNT' => 2,
    'FEATURE_MIN'           => 1,
    'FEATURE_MAX'           => 5,
    'NEUTRAL_FEATURE_VALUE' => 3,

    // 資料の検索（RAG）
    'RAG_CHUNK_MAX_CHARS'         => 500,
    'RAG_CHAT_TOP_K'              => 4,
    'RAG_CAREER_TOP_K'            => 3,
    'RAG_RECOMMEND_SUBJECT_COUNT' => 2,
    'RAG_MIN_SCORE'               => 0.05,
    'RAG_PLAN_HISTORY_MESSAGES'   => 4,
    // 学年を指定された質問（「3年の授業を教えて」）で LLM に渡す授業一覧の件数と、概要の文字数
    'RAG_SUBJECT_LIST_MAX'           => 40,
    'RAG_SUBJECT_LIST_SUMMARY_CHARS' => 50,
    // 概要にこの言葉がある科目は索引に入れない（他高専・大学の授業を受講する枠だけで、授業の中身が書かれていないため）
    'RAG_EXCLUDED_SUMMARY_PHRASES' => ['他高専・大学等で開講される授業等を受講'],

    // LLM の使い方
    'LLM_CONNECT_TIMEOUT_SECONDS' => 10,
    'LLM_TIMEOUT_SECONDS'         => 120,
    'LLM_CHAT_TEMPERATURE'        => 0.7,
    'LLM_CHAT_MAX_TOKENS'         => 512,
    'LLM_EXTRACT_TEMPERATURE'     => 0.0,
    'LLM_EXTRACT_MAX_TOKENS'      => 512,
    'LLM_CAREER_TEMPERATURE'      => 0.3, // データにない飾りの文を減らすため低め
    'LLM_CAREER_MAX_TOKENS'       => 800,
    'LLM_PLAN_TEMPERATURE'        => 0.0,
    'LLM_PLAN_MAX_TOKENS'         => 200,
];
