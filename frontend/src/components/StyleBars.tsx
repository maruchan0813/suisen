// 学び方の棒グラフ。画面に入ると左から伸びる。一番高いものを強調する。

import { motion } from 'motion/react';
import type { Score } from '../api/types';

const MAX_SCORE = 5;

export function StyleBars({ scores }: { scores: Score[] }) {
  const top = Math.max(...scores.map((s) => s.score));
  return (
    <div className="bars" data-testid="result-styles">
      {scores.map((s, i) => (
        <div key={s.label} className={`bars__row ${s.score === top ? 'bars__row--top' : ''}`}>
          <span className="bars__name">{s.label}</span>
          <div className="bars__track">
            <motion.div
              className="bars__fill"
              initial={{ width: 0 }}
              whileInView={{ width: `${(s.score / MAX_SCORE) * 100}%` }}
              viewport={{ once: true }}
              transition={{ duration: 1.1, delay: 0.15 * i, ease: [0.2, 0.8, 0.2, 1] }}
            />
          </div>
        </div>
      ))}
      <div className="bars__axis">
        <span />
        <div>{[0, 1, 2, 3, 4, 5].map((n) => <span key={n}>{n}</span>)}</div>
      </div>
    </div>
  );
}
