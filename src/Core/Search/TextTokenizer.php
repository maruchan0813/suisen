<?php

declare(strict_types=1);

namespace Suisen\Core\Search;

/**
 * 文章を検索用の「語」に分ける。
 * 英数字の連続は1語、日本語は隣り合う2文字の組（文字 bigram）を語にする。
 * 日本語は単語の区切りがないので、辞書（形態素解析）を使わずに済むこの方法にした。
 */
final class TextTokenizer
{
    /** @return list<string> */
    public static function tokenize(string $text): array
    {
        // 全角英数字・空白を半角に、半角カナを全角にそろえる
        $normalized = mb_strtolower(mb_convert_kana($text, 'asKV', 'UTF-8'));
        preg_match_all('/[a-z0-9]+|[^\sa-z0-9\p{P}\p{S}]+/u', $normalized, $matches);

        $tokens = [];
        foreach ($matches[0] as $run) {
            if (preg_match('/^[a-z0-9]+$/', $run) === 1) {
                $tokens[] = $run;
                continue;
            }
            array_push($tokens, ...self::bigrams($run));
        }
        return $tokens;
    }

    /** @return list<string> 1文字だけならその1文字 */
    private static function bigrams(string $run): array
    {
        $characters = mb_str_split($run);
        if (count($characters) === 1) {
            return $characters;
        }
        $bigrams = [];
        for ($i = 0; $i < count($characters) - 1; $i++) {
            $bigrams[] = $characters[$i] . $characters[$i + 1];
        }
        return $bigrams;
    }
}
