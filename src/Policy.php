<?php

declare(strict_types=1);

namespace Suisen;

use LogicException;
use Suisen\Core\PromptTemplate;

/**
 * policy/ フォルダの方針（意見で変わる値・文言・プロンプト）を読み出す窓口。
 */
final class Policy
{
    public const GROUP_INTEREST = 'interest';
    public const GROUP_STYLE = 'style';

    /**
     * @param array<string, mixed> $values
     * @param list<array{key: string, label: string, group: string}> $features
     * @param array<string, string> $messages
     * @param array<string, string> $courseSummaries
     * @param array<string, string> $courseCatches
     */
    private function __construct(
        private readonly array $values,
        private readonly array $features,
        private readonly array $messages,
        private readonly array $courseSummaries,
        private readonly array $courseCatches,
        private readonly string $promptDir,
    ) {
    }

    public static function load(string $policyDir): self
    {
        return new self(
            require $policyDir . '/app.php',
            require $policyDir . '/features.php',
            require $policyDir . '/messages.php',
            require $policyDir . '/course_summaries.php',
            require $policyDir . '/course_catches.php',
            $policyDir . '/prompts',
        );
    }

    public function int(string $name): int
    {
        return (int) $this->value($name);
    }

    public function float(string $name): float
    {
        return (float) $this->value($name);
    }

    public function bool(string $name): bool
    {
        return (bool) $this->value($name);
    }

    /** @return list<string> */
    public function list(string $name): array
    {
        return array_values((array) $this->value($name));
    }

    public function string(string $name): string
    {
        return (string) $this->value($name);
    }

    /** @return list<string> */
    public function featureKeys(?string $group = null): array
    {
        $features = $group === null
            ? $this->features
            : array_filter($this->features, static fn (array $feature): bool => $feature['group'] === $group);
        return array_values(array_column($features, 'key'));
    }

    public function featureLabel(string $key): string
    {
        foreach ($this->features as $feature) {
            if ($feature['key'] === $key) {
                return $feature['label'];
            }
        }
        throw new LogicException("項目 {$key} は policy/features.php にありません。");
    }

    /** @return list<string> */
    public function featureLabels(?string $group = null): array
    {
        return array_map(fn (string $key): string => $this->featureLabel($key), $this->featureKeys($group));
    }

    /** @param array<string, string|int|float> $params */
    public function message(string $code, array $params = []): string
    {
        if (!array_key_exists($code, $this->messages)) {
            throw new LogicException("文言 {$code} は policy/messages.php にありません。");
        }
        return PromptTemplate::render($this->messages[$code], $params);
    }

    public function prompt(string $name): string
    {
        $path = "{$this->promptDir}/{$name}.txt";
        $prompt = is_file($path) ? file_get_contents($path) : false;
        if ($prompt === false) {
            throw new LogicException("プロンプト {$path} を読めません。");
        }
        return $prompt;
    }

    public function courseSummary(string $courseCode): string
    {
        return $this->courseSummaries[$courseCode] ?? '';
    }

    /** 診断結果の画面に出す、コースごとのひとこと */
    public function courseCatch(string $courseCode): string
    {
        return $this->courseCatches[$courseCode] ?? '';
    }

    private function value(string $name): mixed
    {
        if (!array_key_exists($name, $this->values)) {
            throw new LogicException("方針の値 {$name} は policy/app.php にありません。");
        }
        return $this->values[$name];
    }
}
