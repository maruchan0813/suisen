// 信頼度（高・中・低）のバッジ。色だけに頼らず、必ず文字も出す。

import type { ConfidenceLevel } from '../api/types';

interface Props {
  level: ConfidenceLevel;
  label: string;
  caption: string;
  testId?: string;
}

export function ConfidenceBadge({ level, label, caption, testId }: Props) {
  return (
    <span className={`confidence confidence--${level}`} data-testid={testId} data-level={level}>
      <span className="confidence__caption">{caption}</span>
      <strong className="confidence__label">{label}</strong>
    </span>
  );
}
