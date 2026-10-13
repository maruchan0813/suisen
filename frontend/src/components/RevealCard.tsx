// 結果のカード。画面に入ったときに下から浮かび上がる。

import { motion } from 'motion/react';
import type { ReactNode } from 'react';

export function RevealCard({ title, children, testId }: { title: string; children: ReactNode; testId?: string }) {
  return (
    <motion.section
      className="card result-card"
      data-testid={testId}
      initial={{ opacity: 0, y: 40, scale: 0.97 }}
      whileInView={{ opacity: 1, y: 0, scale: 1 }}
      viewport={{ once: true, amount: 0.2 }}
      transition={{ type: 'spring', stiffness: 160, damping: 20 }}
    >
      <h2 className="result-card__title">{title}</h2>
      {children}
    </motion.section>
  );
}
