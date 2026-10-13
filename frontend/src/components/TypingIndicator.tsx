// 考え中・診断中の表示。3つの点が順に跳ねる。診断中は全体がゆっくり脈打つ。

import { motion } from 'motion/react';

export function TypingIndicator({ label, pulsing = false }: { label: string; pulsing?: boolean }) {
  return (
    <motion.div
      className={`typing ${pulsing ? 'typing--pulsing' : ''}`}
      role="status"
      data-testid="status-indicator"
      initial={{ opacity: 0, y: 12, scale: 0.9 }}
      animate={pulsing ? { opacity: [0.75, 1, 0.75], y: 0, scale: [1, 1.03, 1] } : { opacity: 1, y: 0, scale: 1 }}
      exit={{ opacity: 0, scale: 0.9 }}
      transition={pulsing ? { duration: 1.6, repeat: Infinity } : { type: 'spring', stiffness: 300, damping: 22 }}
    >
      <span className="typing__dots" aria-hidden="true">
        {[0, 1, 2].map((i) => (
          <motion.span
            key={i}
            className="typing__dot"
            animate={{ y: [0, -7, 0] }}
            transition={{ duration: 0.7, repeat: Infinity, delay: i * 0.15, ease: 'easeInOut' }}
          />
        ))}
      </span>
      <span>{label}</span>
    </motion.div>
  );
}
