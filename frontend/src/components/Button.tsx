// ボタン：押すと少し縮み、離すと戻る。処理中は押せない。

import { motion, type HTMLMotionProps } from 'motion/react';

type Variant = 'primary' | 'secondary' | 'accent' | 'ghost' | 'link' | 'white';

interface ButtonProps extends HTMLMotionProps<'button'> {
  variant?: Variant;
  busy?: boolean;
}

export function Button({ variant = 'primary', busy = false, disabled, className = '', ...rest }: ButtonProps) {
  const inactive = disabled || busy;
  return (
    <motion.button
      type="button"
      className={`button button--${variant} ${className}`}
      disabled={inactive}
      aria-busy={busy}
      whileHover={inactive ? undefined : { scale: 1.04, y: -1 }}
      whileTap={inactive ? undefined : { scale: 0.94 }}
      transition={{ type: 'spring', stiffness: 400, damping: 18 }}
      {...rest}
    />
  );
}
