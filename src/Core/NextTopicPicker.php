<?php

declare(strict_types=1);

namespace Suisen\Core;

/**
 * 次に学生に聞く項目を決める。
 * LLM に「まだ聞いていない項目を聞いて」と頼むだけでは、答えてもらえなかった質問を何度も繰り返すので、
 * 項目をこちらで決めて渡す。
 *
 * 項目の表示名を「・」で分けた言葉（例：「力学・材料」→「力学」「材料」）が、
 * これまでの AI の質問にも学生の発言にも出ていない項目を、並び順で最初の1つ選ぶ。
 */
final class NextTopicPicker
{
    private const LABEL_SEPARATOR = '・';

    /** @param list<string> $labels 聞く順に並べた項目の表示名 */
    public function __construct(private readonly array $labels)
    {
    }

    /**
     * @param list<string> $texts これまでの AI の質問と学生の発言
     * @param list<string> $askedTopics もう聞かせた項目（AI が言い換えると言葉では分からないので、記録から除く）
     * @return string|null 次に聞く項目の表示名。全部ふれていれば null
     */
    public function pick(array $texts, array $askedTopics = []): ?string
    {
        $conversation = implode("\n", $texts);
        foreach ($this->labels as $label) {
            if (!in_array($label, $askedTopics, true) && !self::isMentioned($label, $conversation)) {
                return $label;
            }
        }
        return null;
    }

    private static function isMentioned(string $label, string $conversation): bool
    {
        foreach (explode(self::LABEL_SEPARATOR, $label) as $word) {
            if ($word !== '' && str_contains($conversation, $word)) {
                return true;
            }
        }
        return false;
    }
}
