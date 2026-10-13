// アイコンの黄色いきらめきが、中心から散る。診断結果の演出に使う。

import { motion } from 'motion/react';

const SPARKLE_COUNT = 14;

export function Sparkles() {
  return (
    <span className="sparkles" aria-hidden="true">
      {Array.from({ length: SPARKLE_COUNT }, (_, i) => {
        const angle = (i / SPARKLE_COUNT) * Math.PI * 2;
        const distance = 70 + (i % 3) * 30;
        return (
          <motion.span
            key={i}
            className="sparkles__piece"
            initial={{ x: 0, y: 0, scale: 0, opacity: 1, rotate: 0 }}
            animate={{
              x: Math.cos(angle) * distance,
              y: Math.sin(angle) * distance,
              scale: [0, 1.2, 0],
              opacity: [1, 1, 0],
              rotate: 180,
            }}
            transition={{ duration: 1.1, ease: 'easeOut', delay: 0.15 + (i % 4) * 0.04 }}
          />
        );
      })}
    </span>
  );
}
