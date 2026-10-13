// ログイン状態と CSRF トークンを、画面全体で共有する。

import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';
import { api, rememberSession } from './api/client';
import type { SessionInfo } from './api/types';

interface SessionContextValue {
  session: SessionInfo | null;
  /** ログイン・ログアウトのあとに読み直す（セッションと CSRF トークンが変わるため） */
  refresh: () => Promise<SessionInfo | null>;
}

const SessionContext = createContext<SessionContextValue | null>(null);

export function SessionProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<SessionInfo | null>(null);
  const [loadError, setLoadError] = useState('');

  const refresh = useCallback(async () => {
    const result = await api.session();
    if (!result.ok) {
      setLoadError(result.error.message);
      return null;
    }
    rememberSession(result.data);
    setSession(result.data);
    return result.data;
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  if (loadError !== '' && session === null) {
    return <p className="load-error" role="alert">{loadError}</p>;
  }
  return <SessionContext.Provider value={{ session, refresh }}>{children}</SessionContext.Provider>;
}

export function useSession(): SessionContextValue {
  const value = useContext(SessionContext);
  if (value === null) {
    throw new Error('SessionProvider の中で使ってください。');
  }
  return value;
}
