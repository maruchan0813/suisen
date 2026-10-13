// 画面の上の帯。アイコンは少し揺れて目を引く。

import { motion } from 'motion/react';
import type { ReactNode } from 'react';

export function Header({ left, right }: { left?: ReactNode; right?: ReactNode }) {
  return (
    <header className="header">
      <div className="header__side">{left}</div>
      <motion.img
        className="header__icon"
        src="./icon.webp"
        alt="SUISEN"
        whileHover={{ rotate: [0, -8, 8, 0], scale: 1.08 }}
        transition={{ duration: 0.5 }}
      />
      <div className="header__side header__side--right">{right}</div>
    </header>
  );
}
