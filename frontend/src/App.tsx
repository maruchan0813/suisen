// 画面の移動。ログインしていなければログイン画面へ、ログイン中ならホームへ移す。

import { AnimatePresence, MotionConfig } from 'motion/react';
import type { ReactElement } from 'react';
import { HashRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { ChatPage } from './pages/ChatPage';
import { HomePage } from './pages/HomePage';
import { LoginCodePage } from './pages/LoginCodePage';
import { LoginEmailPage } from './pages/LoginEmailPage';
import { ResultPage } from './pages/ResultPage';
import { SessionProvider, useSession } from './session';

function AnimatedRoutes() {
  const location = useLocation();
  const { session } = useSession();
  if (session === null) {
    return null; // 読み込み中
  }

  const loggedIn = session.logged_in;
  const requireLogin = (page: ReactElement) => (loggedIn ? page : <Navigate to="/" replace />);
  const guestOnly = (page: ReactElement) => (loggedIn ? <Navigate to="/home" replace /> : page);

  return (
    <AnimatePresence mode="wait">
      <Routes location={location} key={location.pathname}>
        <Route path="/" element={guestOnly(<LoginEmailPage />)} />
        <Route
          path="/verify"
          element={guestOnly(session.pending_email !== null ? <LoginCodePage /> : <Navigate to="/" replace />)}
        />
        <Route path="/home" element={requireLogin(<HomePage />)} />
        <Route path="/chat/:id" element={requireLogin(<ChatPage />)} />
        <Route path="/result/:id" element={requireLogin(<ResultPage />)} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </AnimatePresence>
  );
}

export function App() {
  return (
    // 動きを減らす設定の人には、動きを小さくする
    <MotionConfig reducedMotion="user">
      <SessionProvider>
        <HashRouter>
          <AnimatedRoutes />
        </HashRouter>
      </SessionProvider>
    </MotionConfig>
  );
}
