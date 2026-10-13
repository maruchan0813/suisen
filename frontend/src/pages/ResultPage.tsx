// S5 診断結果。診断の直後は「焦らし → ドラムロール → 結果」の順に演出する。
// ホームなどから見直すときは、すぐ結果を出す。

import { AnimatePresence, motion } from 'motion/react';
import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import { api, isLoggedOut } from '../api/client';
import type { CareerExample, DiagnosisResultView } from '../api/types';
import { Button } from '../components/Button';
import { CountUp } from '../components/CountUp';
import { ErrorText } from '../components/ErrorText';
import { RadarChart } from '../components/RadarChart';
import { RevealCard } from '../components/RevealCard';
import { Sparkles } from '../components/Sparkles';
import { StyleBars } from '../components/StyleBars';
import { useSession } from '../session';

type Stage = 'intro' | 'drumroll' | 'result';

const DRUMROLL_MS = 1800;
const CHIPS_SHOWN_FIRST = 10;
const KCT_CAREER_PAGE = 'https://www.kct.ac.jp/about/career';

/** 診断の直後に、結果の画面へ移るときに渡す印 */
export interface ResultLocationState {
  justDiagnosed?: boolean;
}

function formatDate(value: string): string {
  const date = new Date(value.replace(' ', 'T'));
  return `${date.getFullYear()}年${date.getMonth() + 1}月${date.getDate()}日 ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

/** 北九州高専の「進路状況」のページで、そのコースの欄まで移るリンク（テキスト指定リンク） */
function kctCareerUrl(courseFullName: string): string {
  return `${KCT_CAREER_PAGE}#:~:text=${encodeURIComponent(courseFullName)},-${encodeURIComponent('主な就職先')}`;
}

/** 同じ進路の先輩は1枚にまとめて、人数を出す */
function groupCareers(careers: CareerExample[]): { career: CareerExample; count: number }[] {
  const groups = new Map<string, { career: CareerExample; count: number }>();
  for (const career of careers) {
    const key = `${career.course_name}|${career.type}|${career.destination}`;
    const group = groups.get(key);
    if (group === undefined) {
      groups.set(key, { career, count: 1 });
    } else {
      group.count++;
    }
  }
  return [...groups.values()];
}

function Dots() {
  return (
    <span className="dots" aria-hidden="true">
      {[0, 1, 2].map((i) => (
        <motion.span key={i} animate={{ opacity: [0.2, 1, 0.2] }} transition={{ duration: 1.2, repeat: Infinity, delay: i * 0.2 }}>
          .
        </motion.span>
      ))}
    </span>
  );
}

function Intro({ onReveal }: { onReveal: () => void }) {
  return (
    <motion.section className="intro" data-testid="result-intro" exit={{ opacity: 0 }}>
      <motion.img
        className="intro__logo"
        src="./icon.webp"
        alt="SUISEN"
        animate={{ y: [0, -10, 0] }}
        transition={{ duration: 3, repeat: Infinity, ease: 'easeInOut' }}
      />
      <motion.p className="intro__line" initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.4, duration: 0.8 }}>
        診断結果が出ました。
      </motion.p>
      <motion.p
        className="intro__line intro__line--big"
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 1.7, duration: 0.8 }}
      >
        あなたに合ったコースは<Dots />
      </motion.p>
      <motion.div
        initial={{ opacity: 0, scale: 0.8 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ delay: 3.2, type: 'spring', stiffness: 220, damping: 14 }}
      >
        <Button className="intro__button pulse" onClick={onReveal} data-testid="reveal-button">
          結果を見る
        </Button>
      </motion.div>
    </motion.section>
  );
}

function Drumroll() {
  return (
    <motion.div
      className="drumroll"
      data-testid="result-drumroll"
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0, scale: 1.1 }}
      transition={{ duration: 0.4 }}
    >
      <motion.div animate={{ scale: [1, 1.08, 1] }} transition={{ duration: 0.6, repeat: Infinity }}>
        診断中<Dots />
      </motion.div>
    </motion.div>
  );
}

function ResultBody({ result }: { result: DiagnosisResultView }) {
  const navigate = useNavigate();
  const [starting, setStarting] = useState(false);
  const [error, setError] = useState('');
  const courseFullName = `${result.course_name}コース`;

  async function diagnoseAgain() {
    setStarting(true);
    const started = await api.startConversation();
    if (started.ok) {
      navigate(started.data.redirect);
      return;
    }
    setError(started.error.message);
    setStarting(false);
  }

  return (
    <motion.main className="result" data-testid="result" initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
      <div className="result__topbar">
        <Link className="button button--ghost" to="/home" data-testid="home-link">ホームへ</Link>
        <img className="header__icon" src="./icon.webp" alt="SUISEN" />
      </div>

      <motion.section
        className="hero"
        initial={{ opacity: 0, y: 40, scale: 0.9 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ type: 'spring', stiffness: 120, damping: 14 }}
      >
        <small>あなたに一番合うコースは</small>
        <h1 className="hero__course" data-testid="result-course">
          <motion.span
            className="hero__name"
            initial={{ scale: 0.3, opacity: 0, rotate: -6 }}
            animate={{ scale: 1, opacity: 1, rotate: 0 }}
            transition={{ type: 'spring', stiffness: 200, damping: 10, delay: 0.35 }}
          >
            {courseFullName}
            <motion.span
              className="hero__marker"
              initial={{ scaleX: 0 }}
              animate={{ scaleX: 1 }}
              transition={{ delay: 0.9, duration: 0.6, ease: 'easeOut' }}
            />
          </motion.span>
          <Sparkles />
        </h1>
        {result.catch !== '' && (
          <motion.p className="hero__catch" initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 1.1 }}>
            {result.catch}
          </motion.p>
        )}
        <motion.div className="hero__meta" initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 1.3 }}>
          <span data-testid="result-date">診断日時 {formatDate(result.diagnosed_at)}</span>
        </motion.div>
      </motion.section>

      <RevealCard title="あなたの興味バランス" testId="result-interests">
        <div className="radar-box">
          <RadarChart scores={result.interests} />
        </div>
        <h3 className="result-card__sub">あなたの学び方</h3>
        <StyleBars scores={result.styles} />
      </RevealCard>

      <RevealCard title="AIからのコメント" testId="result-comments">
        {result.messages.map((message, i) => (
          <motion.p
            key={i}
            className="result-bubble"
            initial={{ opacity: 0, x: -24 }}
            whileInView={{ opacity: 1, x: 0 }}
            viewport={{ once: true }}
            transition={{ delay: 0.15 * i, type: 'spring', stiffness: 200, damping: 20 }}
          >
            {message}
          </motion.p>
        ))}
        {result.sources.length > 0 && <p className="result-note">参考にしたシラバス：{result.sources.join('、')}</p>}
      </RevealCard>

      <RevealCard title="似た先輩の進路" testId="result-careers">
        <div className="careers">
          {groupCareers(result.careers).map(({ career, count }, i) => (
            <motion.div
              key={i}
              className="career"
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              whileHover={{ y: -3 }}
              transition={{ delay: 0.12 * i, type: 'spring', stiffness: 220, damping: 18 }}
            >
              <div className="career__icon" aria-hidden="true">{career.is_study ? '🎓' : '💼'}</div>
              <div>
                <span className="career__tag">{career.type}</span>
                <strong>{career.destination}</strong>
                <p>{career.course_name}コースに進んだ先輩{count > 1 ? `（${count}人）` : ''}</p>
              </div>
            </motion.div>
          ))}
        </div>
        <p className="result-note">先輩の進路は、アプリの試作用に作ったデータです。</p>

        {result.kct !== null && (
          <>
            <h3 className="result-card__sub">このコースの先輩の実績（令和8年3月卒業）</h3>
            <div className="stats" data-testid="result-kct">
              <div><CountUp to={result.kct.grad} /><span>卒業者</span></div>
              <div><CountUp to={result.kct.job} /><span>就職</span></div>
              <div><CountUp to={result.kct.study} /><span>進学希望</span></div>
            </div>
            <ChipList title="主な就職先" items={result.kct.jobs} />
            <ChipList title="主な進学先" items={result.kct.schools} school />
            <p className="result-note">出典：北九州高専「進路状況」。主な就職先・進学先の抜粋で、すべてではありません。</p>
          </>
        )}
        <div className="result-more">
          <a href={kctCareerUrl(courseFullName)} target="_blank" rel="noopener noreferrer">
            北九州高専の進路状況を見る（{courseFullName}）→
          </a>
        </div>
      </RevealCard>

      <ErrorText message={error} />
      <div className="actions actions--center">
        <Button busy={starting} onClick={diagnoseAgain} data-testid="again-button">もう一度診断する</Button>
        <Link className="button button--secondary" to="/home">ホームに戻る</Link>
      </div>
    </motion.main>
  );
}

function ChipList({ title, items, school = false }: { title: string; items: string[]; school?: boolean }) {
  const [expanded, setExpanded] = useState(false);
  const shown = expanded ? items : items.slice(0, CHIPS_SHOWN_FIRST);
  const hiddenCount = items.length - shown.length;
  return (
    <>
      <p className="chips__title">{title}</p>
      <motion.div
        className="chips"
        initial="hidden"
        whileInView="show"
        viewport={{ once: true }}
        variants={{ show: { transition: { staggerChildren: 0.03 } } }}
      >
        {shown.map((item) => (
          <motion.span
            key={item}
            className={`chip ${school ? 'chip--school' : ''}`}
            variants={{ hidden: { opacity: 0, scale: 0.6 }, show: { opacity: 1, scale: 1 } }}
          >
            {item}
          </motion.span>
        ))}
        {hiddenCount > 0 && (
          <button type="button" className="chip chip--more" onClick={() => setExpanded(true)}>
            すべて見る（ほか{hiddenCount}件）
          </button>
        )}
      </motion.div>
    </>
  );
}

export function ResultPage() {
  const conversationId = Number(useParams().id);
  const location = useLocation();
  const navigate = useNavigate();
  const { refresh } = useSession();
  const justDiagnosed = (location.state as ResultLocationState | null)?.justDiagnosed === true;
  const [stage, setStage] = useState<Stage>(justDiagnosed ? 'intro' : 'result');
  const [result, setResult] = useState<DiagnosisResultView | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    void api.result(conversationId).then(async (response) => {
      if (response.ok) {
        setResult(response.data);
      } else if (isLoggedOut(response)) {
        await refresh();
        navigate('/');
      } else {
        setError(response.error.message);
      }
    });
  }, [conversationId, navigate, refresh]);

  function reveal() {
    setStage('drumroll');
    window.setTimeout(() => {
      setStage('result');
      window.scrollTo(0, 0);
    }, DRUMROLL_MS);
  }

  if (error !== '') {
    return (
      <main className="center">
        <ErrorText message={error} />
        <Link className="button button--secondary" to="/home">ホームへ</Link>
      </main>
    );
  }

  return (
    <div className="result-page">
      <AnimatePresence mode="wait">
        {stage === 'intro' && <Intro key="intro" onReveal={reveal} />}
        {stage === 'drumroll' && <Drumroll key="drumroll" />}
        {stage === 'result' && result !== null && <ResultBody key="result" result={result} />}
      </AnimatePresence>
    </div>
  );
}
