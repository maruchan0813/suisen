// 興味のバランスのレーダーチャート。ライブラリを使わず SVG で描き、中心から広がるように出す。

import { motion } from 'motion/react';
import type { Score } from '../api/types';

const SIZE = 340;
const CENTER = SIZE / 2;
const RADIUS = 110;
const MAX_SCORE = 5;
const RINGS = [1, 2, 3, 4, 5];
const LABEL_OFFSET = 24;
// 左右の項目名が切れないよう、描く範囲の左右に余白をとる（項目名は「・」で2行に分けるので狭くてよい）
const LABEL_SPACE_X = 46;
const LABEL_LINE_HEIGHT = 16;

function pointAt(index: number, count: number, ratio: number): [number, number] {
  const angle = -Math.PI / 2 + (index / count) * Math.PI * 2; // 真上から時計回り
  return [CENTER + Math.cos(angle) * RADIUS * ratio, CENTER + Math.sin(angle) * RADIUS * ratio];
}

/** 「設計・ものづくり」→「設計・」「ものづくり」のように、「・」の後ろで2行に分ける */
function labelLines(label: string): string[] {
  const index = label.indexOf('・');
  return index === -1 ? [label] : [label.slice(0, index + 1), label.slice(index + 1)];
}

function polygon(count: number, ratioOf: (index: number) => number): string {
  return Array.from({ length: count }, (_, i) => pointAt(i, count, ratioOf(i)).join(',')).join(' ');
}

export function RadarChart({ scores }: { scores: Score[] }) {
  const count = scores.length;
  const top = Math.max(...scores.map((s) => s.score));

  return (
    <svg className="radar" viewBox={`${-LABEL_SPACE_X} 0 ${SIZE + LABEL_SPACE_X * 2} ${SIZE}`} role="img" aria-label="興味のバランス" data-testid="result-radar">
      {RINGS.map((ring) => (
        <polygon key={ring} className="radar__ring" points={polygon(count, () => ring / MAX_SCORE)} />
      ))}
      {scores.map((_, i) => {
        const [x, y] = pointAt(i, count, 1);
        return <line key={i} className="radar__axis" x1={CENTER} y1={CENTER} x2={x} y2={y} />;
      })}
      <motion.polygon
        className="radar__area"
        points={polygon(count, (i) => scores[i].score / MAX_SCORE)}
        initial={{ scale: 0, opacity: 0 }}
        animate={{ scale: 1, opacity: 1 }}
        style={{ transformOrigin: `${CENTER}px ${CENTER}px` }}
        transition={{ type: 'spring', stiffness: 90, damping: 12, delay: 0.3 }}
      />
      {scores.map((s, i) => {
        const [x, y] = pointAt(i, count, s.score / MAX_SCORE);
        return (
          <motion.circle
            key={s.label}
            className="radar__point"
            cx={x}
            cy={y}
            r={s.score === top ? 6 : 4}
            initial={{ scale: 0 }}
            animate={{ scale: 1 }}
            style={{ transformOrigin: `${x}px ${y}px` }}
            transition={{ delay: 0.7 + i * 0.06, type: 'spring', stiffness: 400 }}
          />
        );
      })}
      {scores.map((s, i) => {
        const [x, y] = pointAt(i, count, (RADIUS + LABEL_OFFSET) / RADIUS);
        return (
          <text
            key={s.label}
            className={`radar__label ${s.score === top ? 'radar__label--top' : ''}`}
            x={x}
            y={y}
            textAnchor={Math.abs(x - CENTER) < 4 ? 'middle' : x > CENTER ? 'start' : 'end'}
            dominantBaseline="middle"
          >
            {labelLines(s.label).map((line, lineIndex, lines) => (
              <tspan key={line} x={x} dy={lineIndex === 0 ? -((lines.length - 1) * LABEL_LINE_HEIGHT) / 2 : LABEL_LINE_HEIGHT}>
                {line}
              </tspan>
            ))}
          </text>
        );
      })}
    </svg>
  );
}
