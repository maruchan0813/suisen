<?php

declare(strict_types=1);

namespace Suisen\Core;

use Suisen\Core\Search\TextTokenizer;

/**
 * LLM の返事を、プログラムで見直す。指示だけでは守り切れない2つを確かめる。
 * - シラバスにない授業名を作っていないか
 * - これまでと似た質問を繰り返していないか
 */
final class ReplyReview
{
    /** 「」で囲まれた、授業名らしい言葉（〜工学、〜演習、〜Ⅰ など） */
    private const SUBJECT_LIKE_PATTERN = '/「([^」]{2,30}?(学|工学|演習|実験|実習|概論|特論|技術|論|[ⅠⅡⅢⅣＡＢＣＤABCD]))」/u';
    /** 「微生物工学Ⅰ・Ⅱ」のようにまとめた呼び方は、末尾の番号を除いて照らす */
    private const NUMBERING_SUFFIX_PATTERN = '/[ⅠⅡⅢⅣＡＢＣＤABCD・･、\s]+$/u';
    private const QUESTION_PATTERN = '/[^。！？!?\n]*[？?]/u';
    private const MIN_BASE_LENGTH = 2;

    /** @var list<string> */
    private readonly array $normalizedTitles;

    /**
     * @param list<string> $subjectTitles シラバスにある授業名
     * @param list<string> $forbiddenPhrases 学生に言ってはいけない内部の言葉（「参考資料」など）
     */
    public function __construct(
        array $subjectTitles,
        private readonly float $repeatSimilarity,
        private readonly array $forbiddenPhrases = [],
    ) {
        $this->normalizedTitles = array_map([self::class, 'normalize'], $subjectTitles);
    }

    /**
     * 返事の中の、シラバスにない授業名。学生のメッセージにある言葉（引用）は除く。
     *
     * @return list<string>
     */
    public function unknownSubjects(string $reply, string $studentMessage): array
    {
        preg_match_all(self::SUBJECT_LIKE_PATTERN, $reply, $matches);
        $unknown = array_filter(
            array_unique($matches[1]),
            fn (string $name): bool => !str_contains($studentMessage, $name) && !$this->isKnownSubject($name),
        );
        return array_values($unknown);
    }

    /** @return list<string> 返事に入っている、学生に言ってはいけない内部の言葉 */
    public function forbiddenPhrasesIn(string $reply): array
    {
        return array_values(array_filter($this->forbiddenPhrases, static fn (string $phrase): bool => str_contains($reply, $phrase)));
    }

    /**
     * 返事の質問のうち、これまでの質問と似ているもの。
     *
     * @param list<string> $askedQuestions
     * @return list<string> 似ていた「これまでの質問」
     */
    public function repeatedQuestions(string $reply, array $askedQuestions): array
    {
        $repeated = [];
        foreach (self::questionsIn($reply) as $question) {
            foreach ($askedQuestions as $asked) {
                if (self::similarity($question, $asked) >= $this->repeatSimilarity) {
                    $repeated[] = $asked;
                }
            }
        }
        return array_values(array_unique($repeated));
    }

    /**
     * 返事から、これまでと似た質問の文を取り除く。作り直しても同じ質問が残ったときの最後の手段。
     *
     * @param list<string> $askedQuestions
     */
    public function withoutRepeatedQuestions(string $reply, array $askedQuestions): string
    {
        $cleaned = $reply;
        foreach (self::questionsIn($reply) as $question) {
            foreach ($askedQuestions as $asked) {
                if (self::similarity($question, $asked) >= $this->repeatSimilarity) {
                    $cleaned = str_replace($question, '', $cleaned);
                    break;
                }
            }
        }
        $cleaned = trim((string) preg_replace("/\n{3,}/u", "\n\n", $cleaned));
        return $cleaned === '' ? $reply : $cleaned; // 全部消えるなら、元の返事のままにする
    }

    /**
     * 返事から、シラバスにない授業名を含む文を取り除く。作り直しても残ったときの最後の手段。
     *
     * @param list<string> $unknownSubjects
     */
    public function withoutSentencesMentioning(string $reply, array $unknownSubjects): string
    {
        $sentences = preg_split('/(?<=[。！？!?\n])/u', $reply) ?: [$reply];
        $kept = array_filter($sentences, static function (string $sentence) use ($unknownSubjects): bool {
            foreach ($unknownSubjects as $subject) {
                if (str_contains($sentence, "「{$subject}」")) {
                    return false;
                }
            }
            return true;
        });
        $cleaned = trim((string) preg_replace("/\n{3,}/u", "\n\n", implode('', $kept)));
        return $cleaned === '' ? $reply : $cleaned;
    }

    /** @return list<string> 文章の中の「？」で終わる文 */
    public static function questionsIn(string $text): array
    {
        preg_match_all(self::QUESTION_PATTERN, $text, $matches);
        return array_values(array_filter(array_map('trim', $matches[0]), static fn (string $q): bool => $q !== ''));
    }

    /** 語の集まりの重なり（ジャカード係数、0〜1）。 */
    public static function similarity(string $a, string $b): float
    {
        $tokensA = array_unique(TextTokenizer::tokenize($a));
        $tokensB = array_unique(TextTokenizer::tokenize($b));
        $union = count(array_unique([...$tokensA, ...$tokensB]));
        return $union === 0 ? 0.0 : count(array_intersect($tokensA, $tokensB)) / $union;
    }

    private function isKnownSubject(string $name): bool
    {
        $base = (string) preg_replace(self::NUMBERING_SUFFIX_PATTERN, '', self::normalize($name));
        if (mb_strlen($base) < self::MIN_BASE_LENGTH) {
            return false;
        }
        foreach ($this->normalizedTitles as $title) {
            if (str_starts_with($title, $base)) {
                return true;
            }
        }
        return false;
    }

    /** 全角英数字を半角にそろえる（「ＣＡＤ演習」と「CAD演習」を同じに扱う）。 */
    private static function normalize(string $text): string
    {
        return mb_convert_kana(trim($text), 'asKV', 'UTF-8');
    }
}
