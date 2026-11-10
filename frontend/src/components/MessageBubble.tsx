// チャットの吹き出し。ばねのように弾んで出る（自分は右から、AI は左から）。

import { motion } from 'motion/react';
import type { ChatMessage } from '../api/types';

const SOURCES_PREFIX = '参考にしたシラバス：';

export function MessageBubble({ message, animate }: { message: ChatMessage; animate: boolean }) {
  const fromUser = message.role === 'user';
  if (message.notice === true) {
    return (
      <motion.li
        layout
        className="message message--notice"
        data-testid="chat-notice"
        initial={animate ? { opacity: 0, scale: 0.9, y: 12 } : false}
        animate={{ opacity: 1, scale: 1, y: 0 }}
        transition={{ type: 'spring', stiffness: 260, damping: 20 }}
      >
        {message.content}
      </motion.li>
    );
  }
  return (
    <motion.li
      layout
      className={`message message--${message.role}`}
      data-testid="message"
      data-role={message.role}
      initial={animate ? { opacity: 0, x: fromUser ? 60 : -60, scale: 0.85 } : false}
      animate={{ opacity: 1, x: 0, scale: 1 }}
      transition={{ type: 'spring', stiffness: 320, damping: 22 }}
    >
      <span className="message__text">{message.content}</span>
      {message.sources !== undefined && message.sources.length > 0 && (
        <small className="message__sources" data-testid="message-sources">
          {SOURCES_PREFIX + message.sources.join('、')}
        </small>
      )}
    </motion.li>
  );
}
