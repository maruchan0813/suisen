// S1 メールアドレス入力

import { motion } from 'motion/react';
import { useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api/client';
import { Button } from '../components/Button';
import { ErrorText } from '../components/ErrorText';
import { PageTransition } from '../components/PageTransition';
import { useSession } from '../session';

export function LoginEmailPage() {
  const { session, refresh } = useSession();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function submit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError('');
    const result = await api.login(email);
    if (!result.ok) {
      setError(result.error.message);
      setBusy(false);
      return;
    }
    await refresh();
    navigate(result.data.redirect);
  }

  return (
    <PageTransition className="center">
      <section className="card login">
        <motion.img
          className="login__icon"
          src="./icon.webp"
          alt="SUISEN"
          initial={{ scale: 0.4, rotate: -20, opacity: 0 }}
          animate={{ scale: 1, rotate: 0, opacity: 1 }}
          transition={{ type: 'spring', stiffness: 200, damping: 12, delay: 0.1 }}
        />
        <motion.p
          className="login__lead"
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
        >
          チャットで話すだけで、あなたに合うコースと、似た先輩の進路が分かります。
        </motion.p>
        <form className="form" noValidate onSubmit={submit}>
          <label className="form__label" htmlFor="email">学校のメールアドレス</label>
          <input
            className="form__input"
            id="email"
            type="email"
            autoComplete="email"
            placeholder="xxxx@apps.kct.ac.jp"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            data-testid="email-input"
          />
          <Button type="submit" busy={busy} data-testid="send-code-button">
            {session?.verification_enabled ? '確認コードを送る' : 'ログイン'}
          </Button>
          <ErrorText message={error} />
        </form>
      </section>
    </PageTransition>
  );
}
