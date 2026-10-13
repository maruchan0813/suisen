<?php
// 画面やメールに出す文言。{{名前}} の部分はプログラムが値を差し込む。

return [
    // エラー（キーは API の error.code と同じ）
    'invalid_email'       => '学校のメールアドレス（@apps.kct.ac.jp）を入力してください。',
    'send_too_soon'       => '少し待ってから、もう一度送ってください。',
    'mail_failed'         => 'メールを送れませんでした。時間をおいて試してください。',
    'no_pending_email'    => 'もう一度、メールアドレスの入力からやり直してください。',
    'wrong_code'          => '確認コードが違います。（残り {{remaining}} 回）',
    'code_expired'        => '確認コードの有効期限が切れました。送り直してください。',
    'too_many_attempts'   => '間違いが多いため、このコードは使えなくなりました。送り直してください。',
    'invalid_content'     => 'メッセージを入力してください（{{max}}文字まで）。',
    'conversation_closed' => 'この会話は診断済みです。ホームから新しい会話を始めてください。',
    'turn_limit'          => '会話が長くなったので、診断してください。',
    'not_enough_turns'    => 'もう少し話してから診断しましょう。',
    'llm_unavailable'     => 'AI に接続できませんでした。学内ネットワークにつながっているか確かめてください。',
    'network_error'       => '通信に失敗しました。',
    'not_logged_in'       => 'ログインの期限が切れました。もう一度ログインしてください。',
    'invalid_csrf'        => '画面の有効期限が切れました。ページを読み込み直してください。',
    'not_found'           => '見つかりませんでした。',
    'method_not_allowed'  => 'この操作はできません。',
    'server_error'        => 'エラーが起きました。時間をおいて試してください。',

    // 確認コードのメール
    'mail_subject' => '【SUISEN】確認コード',
    'mail_body'    => "SUISEN の確認コードは {{code}} です。\n{{minutes}}分以内に入力してください。\n\n心当たりがない場合は、このメールを無視してください。",

    // チャット
    'chat_greeting' => "こんにちは！SUISEN です。コース選びの相談にのります。\nまずは、最近の授業や実験で「楽しかった」「もっとやってみたい」と思ったことを教えてください。",
    'recommendation'                => 'あなたに一番合いそうなのは「{{course}}」コースです。特に{{reasons}}への興味が、このコースの学びと重なっています。',
    'recommendation_without_reason' => 'あなたに一番合いそうなのは「{{course}}」コースです。',
    'recommendation_subjects'       => 'このコースでは{{subjects}}などの授業で学べます。',
    'recommendation_subject_joiner' => 'や',
    'no_references'                 => '（今回は参考資料なし）',
    'no_asked_questions'            => '（まだなし）',
    'no_subject_list'               => '（今回は授業一覧なし）',
    'subject_list_source'           => '授業一覧（{{course}}・{{grades}}）',
    'revision_header'             => '# 前の返事の直し（必ず守る。前の返事には問題があったので、作り直す）',
    'revision_unknown_subjects'   => '{{subjects}} という授業はシラバスにない。この名前は書かない。授業名は「参考資料」と「コースの説明」にあるものだけを使い、なければ「分かりません」と答える。',
    'revision_forbidden_phrases'  => '{{phrases}} は内部の言葉なので、学生に向けて書かない。言い換えて自然に話す（例：「シラバスによると」）。',
    'revision_repeated_questions' => '{{questions}} はもう聞いた質問。この質問や、言い方を変えただけの質問はしない。',
    'turn_instruction_answer_only'  => '学生は質問をしています。質問に答えるだけにして、最後に質問を付けない（「ほかに知りたいことがあれば聞いてね」のような一言で終えてよい）。',
    'turn_instruction_ask_topic'    => '学生の話を受け止めてから、「{{topic}}」について、学生が答えやすい形の質問を1つだけする（専門用語を避け、身近な例で聞く）。上の「これまでにした質問」とは違う質問にする。「{{topic}}」という項目名や、「質問を1つします」「次は〜について聞きます」のような前置きは書かず、自然な会話の質問だけを書く。',
    'turn_instruction_ask_free'     => '学生の話を受け止めてから、学生の興味や学び方をもっと詳しく知るための質問を1つだけする。上の「これまでにした質問」とは違う質問にする。',
    'career_type_employment'    => '就職',
    'career_type_further_study' => '進学',
];
