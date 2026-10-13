// ホームの「5つのコースを知ろう」に出す、コースの紹介（画面の文言）。
// チームメンバーの試作（home_with_radar.php）から。code は data/courses.csv と同じ。

export interface CourseIntro {
  code: string;
  name: string;
  icon: string;
  description: string;
}

export const COURSE_INTROS: CourseIntro[] = [
  { code: 'mechanical', name: '機械創造', icon: '⚙️', description: '機械の設計やものづくりを学ぶ' },
  { code: 'robotics', name: '知能ロボット', icon: '🤖', description: 'ロボットや制御技術を学ぶ' },
  { code: 'electrical', name: '電気電子', icon: '⚡', description: '電気や電子回路について学ぶ' },
  { code: 'information', name: '情報システム', icon: '💻', description: 'プログラミングや情報技術を学ぶ' },
  { code: 'chemistry', name: '物質化学', icon: '🧪', description: '化学や物質について学ぶ' },
];
