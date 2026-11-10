<?php

declare(strict_types=1);

namespace Suisen\Service;

use Suisen\Core\Confidence;
use Suisen\Policy;

/**
 * 信頼度を、画面に出す文言にする。段階の計算は Core\Confidence、文言は policy/messages.php。
 */
final class ConfidencePresenter
{
    public function __construct(private readonly Policy $policy)
    {
    }

    /**
     * チャット中に出す「いまの情報量」。
     *
     * @return array{level: string, label: string, covered: int, total: int, hint: ?string}
     */
    public function evidence(string $level, int $covered, int $total): array
    {
        return [
            'level'   => $level,
            'label'   => $this->policy->message("confidence_label_{$level}"),
            'covered' => $covered,
            'total'   => $total,
            'hint'    => $level === Confidence::LOW ? $this->policy->message('evidence_hint_low') : null,
        ];
    }

    /**
     * 結果画面に出す信頼度。「高」でなければ、もう一度診断する案内も付ける。2位との差が小さいときは、僅差の2位のコースも添える。
     *
     * @param string $evidenceLevel 根拠の量だけで決めた段階（理由の文に使う）
     * @return array{level: string, label: string, covered: int, total: int, reason: string, runner_up: ?string, retry: ?string}
     */
    public function result(string $level, string $evidenceLevel, int $covered, int $total, bool $marginClear, string $runnerUpCourseName): array
    {
        $notHigh = $level !== Confidence::HIGH;
        return [
            'level'     => $level,
            'label'     => $this->policy->message("confidence_label_{$level}"),
            'covered'   => $covered,
            'total'     => $total,
            'reason'    => $this->policy->message("confidence_reason_{$evidenceLevel}", [
                'margin' => $this->policy->message($marginClear ? 'confidence_margin_clear' : 'confidence_margin_close'),
            ]),
            'runner_up' => $notHigh && !$marginClear ? $this->policy->message('confidence_runner_up', ['course' => $runnerUpCourseName]) : null,
            'retry'     => $notHigh ? $this->policy->message('confidence_retry') : null,
        ];
    }
}
