// 画面の切り替え：次の画面が下から浮かび上がる。

import { motion } from 'motion/react';
import type { ReactNode } from 'react';

export function PageTransition({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <motion.main
      className={className}
      initial={{ opacity: 0, y: 32, filter: 'blur(6px)' }}
      animate={{ opacity: 1, y: 0, filter: 'blur(0px)' }}
      exit={{ opacity: 0, y: -20, filter: 'blur(6px)' }}
      transition={{ type: 'spring', stiffness: 260, damping: 26 }}
    >
      {children}
    </motion.main>
  );
}
