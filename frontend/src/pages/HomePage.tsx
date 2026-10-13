// S3 ホーム：ようこそのバナー、あなたの診断結果（レーダーチャート付き）、5つのコースの紹介。
// チームメンバーの試作（home_with_radar.php）のレイアウトをもとにする。

import { motion } from 'motion/react';
import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api, isLoggedOut } from '../api/client';
import type { DiagnosisResultView, Status } from '../api/types';
import { Button } from '../components/Button';
import { ErrorText } from '../components/ErrorText';
import { PageTransition } from '../components/PageTransition';
import { RadarChart } from '../components/RadarChart';
import { COURSE_INTROS } from '../content/courses';
import { useSession } from '../session';

function formatDate(value: string): string {
  const date = new Date(value.replace(' ', 'T'));
  return `${date.getFullYear()}年${date.getMonth() + 1}月${date.getDate()}日 ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

const rise = {
  hidden: { opacity: 0, y: 24 },
  show: { opacity: 1, y: 0, transition: { type: 'spring' as const, stiffness: 200, damping: 22 } },
};

export function HomePage() {
  const { refresh } = useSession();
  const navigate = useNavigate();
  const [status, setStatus] = useState<Status | null>(null);
  const [latest, setLatest] = useState<DiagnosisResultView | null>(null);
  const [busy, setBusy] = useState<'start' | 'logout' | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    void api.status().then(async (result) => {
      if (result.ok) {
        setStatus(result.data);
        // レーダーチャートには、最新の診断の興味9項目を使う
        if (result.data.latest_result_conversation_id !== null) {
          const latestResult = await api.result(result.data.latest_result_conversation_id);
          if (latestResult.ok) {
            setLatest(latestResult.data);
          }
        }
      } else if (isLoggedOut(result)) {
        await refresh();
        navigate('/');
      } else {
        setError(result.error.message);
      }
    });
  }, [navigate, refresh]);

  async function start() {
    setBusy('start');
    setError('');
    const result = await api.startConversation();
    if (result.ok) {
      navigate(result.data.redirect);
      return;
    }
    setError(result.error.message);
    setBusy(null);
  }

  async function logout() {
    setBusy('logout');
    await api.logout();
    await refresh();
    navigate('/');
  }

  const hasDiagnosis = status?.course_name != null;

  return (
    <PageTransition>
      <div className="home">
        <header className="home__header">
          <Link className="home__logo" to="/home">
            <img src="./icon.webp" alt="" />
            SUISEN
          </Link>
          <Button variant="ghost" busy={busy === 'logout'} onClick={logout} data-testid="logout-button">
            ログアウト
          </Button>
        </header>

        <motion.section
          className="welcome"
          initial={{ opacity: 0, y: 30, scale: 0.98 }}
          animate={{ opacity: 1, y: 0, scale: 1 }}
          transition={{ type: 'spring', stiffness: 140, damping: 18 }}
        >
          <h1>あなたの可能性を見つけよう。</h1>
          <p>
            AIとの会話を通して、あなたの興味や関心を発見。<br />
            自分に合ったコースを探してみましょう！
          </p>
          <div className="welcome__buttons">
            <Button variant="white" busy={busy === 'start'} onClick={start} data-testid="start-button">
              ✨ 新しい診断を始める
            </Button>
            {status?.active_conversation_id != null && (
              <motion.div whileHover={{ y: -2 }}>
                <Link className="button button--outline-white" to={`/chat/${status.active_conversation_id}`} data-testid="continue-button">
                  続きから
                </Link>
              </motion.div>
            )}
          </div>
        </motion.section>

        <ErrorText message={error} />

        <h2 className="home__title">あなたの診断結果</h2>
        {status !== null && (
          <motion.section
            className="card home-result"
            variants={{ show: { transition: { staggerChildren: 0.1, delayChildren: 0.2 } } }}
            initial="hidden"
            animate="show"
          >
            <div className="home-result__left">
              <motion.div className="home-result__label" variants={rise}>あなたに一番合うコース</motion.div>
              <motion.div className="home-result__course" variants={rise} data-testid="status-course">
                {hasDiagnosis ? `${status.course_name}コース` : 'まだ診断していません'}
              </motion.div>
              <motion.p variants={rise}>
                {hasDiagnosis
                  ? 'あなたの興味や関心からおすすめされたコースです。'
                  : '「新しい診断を始める」から、AIと話してみましょう。'}
              </motion.p>
              <motion.div className="home-result__info" variants={rise}>
                {hasDiagnosis && status.diagnosed_at !== null && (
                  <div className="info-box">
                    最終診断<strong data-testid="status-date">{formatDate(status.diagnosed_at)}</strong>
                  </div>
                )}
                <div className="info-box">
                  診断回数<strong data-testid="status-count">{status.diagnosis_count}回</strong>
                </div>
              </motion.div>
              {status.latest_result_conversation_id !== null && (
                <motion.div variants={rise}>
                  <Link className="button button--accent home-result__link" to={`/result/${status.latest_result_conversation_id}`} data-testid="result-link">
                    結果をくわしく見る →
                  </Link>
                </motion.div>
              )}
            </div>
            <motion.div className="home-result__right" variants={rise}>
              <h3>あなたの興味・関心</h3>
              {latest !== null ? (
                <>
                  <RadarChart scores={latest.interests} />
                  <p className="chart-note">1〜5の5段階評価（最新の診断）</p>
                </>
              ) : (
                <p className="chart-empty">興味・関心の診断データはまだありません。</p>
              )}
            </motion.div>
          </motion.section>
        )}

        <h2 className="home__title">5つのコースを知ろう</h2>
        <motion.div
          className="course-grid"
          initial="hidden"
          whileInView="show"
          viewport={{ once: true, amount: 0.2 }}
          variants={{ show: { transition: { staggerChildren: 0.08 } } }}
        >
          {COURSE_INTROS.map((course) => (
            <motion.div
              key={course.code}
              className={`course-card ${latest?.course_code === course.code ? 'course-card--recommended' : ''}`}
              variants={rise}
              whileHover={{ y: -6, rotate: -1 }}
              data-testid="course-card"
            >
              <motion.div
                className="course-card__icon"
                whileHover={{ scale: 1.25, rotate: [0, -12, 12, 0] }}
                transition={{ duration: 0.5 }}
              >
                {course.icon}
              </motion.div>
              <h3>{course.name}</h3>
              <p>{course.description}</p>
              {latest?.course_code === course.code && <span className="course-card__badge">あなたに一番合う</span>}
            </motion.div>
          ))}
        </motion.div>

        <footer className="home__footer">SUISEN - あなたの未来を見つけるコース推薦アプリ</footer>
      </div>
    </PageTransition>
  );
}
