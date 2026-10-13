// S2 確認コード入力

import { AnimatePresence, motion } from 'motion/react';
import { useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '../api/client';
import { Button } from '../components/Button';
import { ErrorText } from '../components/ErrorText';
import { PageTransition } from '../components/PageTransition';
import { useSession } from '../session';

export function LoginCodePage() {
  const { session, refresh } = useSession();
  const navigate = useNavigate();
  const [code, setCode] = useState('');
  const [busy, setBusy] = useState(false);
  const [resending, setResending] = useState(false);
  const [error, setError] = useState('');
  const [info, setInfo] = useState('');
  const email = session?.pending_email ?? '';
  const digits = session?.code_digits ?? 6;

  async function submit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError('');
    setInfo('');
    const result = await api.verifyCode(code);
    if (!result.ok) {
      setError(result.error.message);
      setBusy(false);
      return;
    }
    await refresh();
    navigate(result.data.redirect);
  }

  async function resend() {
    setResending(true);
    setError('');
    setInfo('');
    const result = await api.resendCode(email);
    if (result.ok) {
      setInfo('確認コードを送り直しました。');
    } else {
      setError(result.error.message);
    }
    setResending(false);
  }

  return (
    <PageTransition className="center">
      <section className="card login">
        <img className="login__icon login__icon--small" src="./icon.webp" alt="SUISEN" />
        <p className="login__lead">
          <strong data-testid="pending-email">{email}</strong> に確認コードを送りました。
        </p>
        <form className="form" noValidate onSubmit={submit}>
          <label className="form__label" htmlFor="code">{digits}桁の確認コード</label>
          <input
            className="form__input form__input--code"
            id="code"
            type="text"
            inputMode="numeric"
            autoComplete="one-time-code"
            maxLength={digits}
            value={code}
            onChange={(event) => setCode(event.target.value.replace(/\D/g, ''))}
            data-testid="code-input"
          />
          <Button type="submit" busy={busy} disabled={code.length !== digits} data-testid="verify-button">
            ログイン
          </Button>
          <Button variant="link" busy={resending} onClick={resend} data-testid="resend-button">
            コードを送り直す
          </Button>
          <AnimatePresence>
            {info !== '' && (
              <motion.p
                className="form__info"
                role="status"
                data-testid="info-message"
                initial={{ opacity: 0, y: -6 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0 }}
              >
                {info}
              </motion.p>
            )}
          </AnimatePresence>
          <ErrorText message={error} />
        </form>
        <Link className="link" to="/">メールアドレスを入力し直す</Link>
      </section>
    </PageTransition>
  );
}
