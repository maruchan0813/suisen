// S4 チャット

import { AnimatePresence, motion } from 'motion/react';
import { useEffect, useRef, useState, type FormEvent, type KeyboardEvent } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api, isLoggedOut } from '../api/client';
import type { ChatMessage } from '../api/types';
import { Button } from '../components/Button';
import { ErrorText } from '../components/ErrorText';
import { Header } from '../components/Header';
import { MessageBubble } from '../components/MessageBubble';
import { PageTransition } from '../components/PageTransition';
import { TypingIndicator } from '../components/TypingIndicator';
import type { ResultLocationState } from './ResultPage';
import { useSession } from '../session';

const STATUS_THINKING = '考え中…';
const STATUS_DIAGNOSING = '診断中…（少し時間がかかります）';
const INPUT_MAX_HEIGHT = 140;

/** 指で操作する端末（スマホ・タブレット）か。キーボードの Enter は改行にする */
const isTouchDevice = typeof window !== 'undefined' && window.matchMedia('(pointer: coarse)').matches;

type Waiting = 'thinking' | 'diagnosing' | null;

export function ChatPage() {
  const conversationId = Number(useParams().id);
  const { refresh } = useSession();
  const navigate = useNavigate();
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [loadedCount, setLoadedCount] = useState(0); // 読み込んだ時点の件数（これより後だけ動かして出す）
  const [isActive, setIsActive] = useState(true);
  const [canDiagnose, setCanDiagnose] = useState(false);
  const [input, setInput] = useState('');
  const [waiting, setWaiting] = useState<Waiting>(null);
  const [error, setError] = useState('');
  const bottomRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLTextAreaElement>(null);

  useEffect(() => {
    void api.conversation(conversationId).then(async (result) => {
      if (result.ok) {
        setMessages(result.data.messages);
        setLoadedCount(result.data.messages.length);
        setIsActive(result.data.is_active);
        setCanDiagnose(result.data.can_diagnose);
      } else if (isLoggedOut(result)) {
        await refresh();
        navigate('/');
      } else {
        navigate('/home'); // ない会話・他人の会話
      }
    });
  }, [conversationId, navigate, refresh]);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
  }, [messages, waiting]);

  // 返事が来たら、続けて打てるように入力欄へ戻す（送信中は入力欄を止めるので、カーソルが外れるため）
  useEffect(() => {
    if (waiting === null && isActive) {
      inputRef.current?.focus();
    }
  }, [waiting, isActive]);

  async function send(event?: FormEvent) {
    event?.preventDefault();
    const content = input.trim();
    if (content === '' || waiting !== null) {
      return;
    }
    setError('');
    setInput('');
    setMessages((current) => [...current, { role: 'user', content }]);
    setWaiting('thinking');

    const result = await api.sendMessage(conversationId, content);
    if (result.ok) {
      setCanDiagnose(result.data.can_diagnose);
      setMessages((current) => [...current, { role: 'assistant', content: result.data.reply, sources: result.data.sources }]);
    } else {
      // サーバーは保存していないので、画面からも取り除いて入力欄に戻す
      setMessages((current) => current.slice(0, -1));
      setInput(content);
      setError(result.error.message);
    }
    setWaiting(null);
  }

  async function diagnose() {
    setError('');
    setWaiting('diagnosing');
    const result = await api.diagnose(conversationId);
    if (result.ok) {
      // 結果は専用の画面で、演出を付けて見せる
      const state: ResultLocationState = { justDiagnosed: true };
      navigate(result.data.redirect, { state });
      return;
    }
    setError(result.error.message);
    setWaiting(null);
  }

  // 入力欄を、打った文字の行数に合わせて伸ばす
  useEffect(() => {
    const textarea = inputRef.current;
    if (textarea !== null) {
      textarea.style.height = 'auto';
      textarea.style.height = `${Math.min(textarea.scrollHeight, INPUT_MAX_HEIGHT)}px`;
    }
  }, [input]);

  function onKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    // 日本語入力の変換確定の Enter では送らない。スマホでは Enter は改行にする
    if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing && !isTouchDevice) {
      event.preventDefault();
      void send();
    }
  }

  return (
    <PageTransition className="chat-page">
      <Header
        left={
          <Link className="button button--ghost" to="/home" data-testid="home-link">
            ホームへ
          </Link>
        }
      />
      <div className="chat">
        <ol className="chat__messages" data-testid="message-list">
          {messages.map((message, index) => (
            <MessageBubble key={index} message={message} animate={index >= loadedCount} />
          ))}
        </ol>
        <AnimatePresence>
          {waiting !== null && (
            <TypingIndicator
              label={waiting === 'thinking' ? STATUS_THINKING : STATUS_DIAGNOSING}
              pulsing={waiting === 'diagnosing'}
            />
          )}
        </AnimatePresence>
        <ErrorText message={error} />

        <AnimatePresence mode="wait">
          {isActive ? (
            <motion.form
              key="composer"
              className="chat__composer"
              onSubmit={send}
              exit={{ opacity: 0, y: 30 }}
            >
              <textarea
                ref={inputRef}
                className="form__input chat__input"
                rows={1}
                placeholder={isTouchDevice ? 'メッセージを入力' : 'メッセージを入力（Enter で送信、Shift + Enter で改行）'}
                value={input}
                onChange={(event) => setInput(event.target.value)}
                onKeyDown={onKeyDown}
                disabled={waiting !== null}
                data-testid="message-input"
              />
              <div className="chat__buttons">
                <Button type="submit" busy={waiting === 'thinking'} disabled={waiting !== null} data-testid="send-button">
                  送信
                </Button>
                <Button
                  variant="accent"
                  busy={waiting === 'diagnosing'}
                  disabled={waiting !== null || !canDiagnose}
                  title="何回か話すと押せるようになります"
                  onClick={diagnose}
                  data-testid="diagnose-button"
                >
                  診断する
                </Button>
              </div>
            </motion.form>
          ) : (
            <motion.div
              key="finished"
              className="chat__finished"
              data-testid="finished"
              initial={{ opacity: 0, scale: 0.9 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ type: 'spring', stiffness: 200, damping: 16, delay: 0.4 }}
            >
              <p>この会話の診断は終わっています。</p>
              <div className="actions actions--center">
                <Link className="button button--primary" to={`/result/${conversationId}`} data-testid="finished-result-link">
                  結果を見る
                </Link>
                <Link className="button button--secondary" to="/home" data-testid="finished-home-link">
                  ホームへ
                </Link>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
        <div ref={bottomRef} />
      </div>
    </PageTransition>
  );
}
