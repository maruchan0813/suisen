// エラーの文言。出るときに小さく左右に揺れる。

import { AnimatePresence, motion } from 'motion/react';

export function ErrorText({ message }: { message: string }) {
  return (
    <AnimatePresence>
      {message !== '' && (
        <motion.p
          key={message}
          className="form__error"
          role="alert"
          data-testid="error-message"
          initial={{ opacity: 0, x: 0 }}
          animate={{ opacity: 1, x: [0, -8, 8, -5, 5, 0] }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.45 }}
        >
          {message}
        </motion.p>
      )}
    </AnimatePresence>
  );
}
