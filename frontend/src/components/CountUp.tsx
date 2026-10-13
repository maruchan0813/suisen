// 数字を0から数え上げる。画面に入ったときに始まる。

import { animate, useInView } from 'motion/react';
import { useEffect, useRef, useState } from 'react';

export function CountUp({ to, duration = 1.2 }: { to: number; duration?: number }) {
  const ref = useRef<HTMLElement>(null);
  const inView = useInView(ref, { once: true });
  const [value, setValue] = useState(0);

  useEffect(() => {
    if (!inView) {
      return;
    }
    const controls = animate(0, to, { duration, ease: 'easeOut', onUpdate: (v) => setValue(Math.round(v)) });
    return () => controls.stop();
  }, [inView, to, duration]);

  return <b ref={ref}>{value}</b>;
}
