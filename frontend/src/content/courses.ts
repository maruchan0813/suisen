// ホームの「5つのコースを知ろう」に出す、コースの紹介（画面の文言）。
// チームメンバーの試作（home_with_radar.php）から。code は data/courses.csv と同じ。
// 絵は assets/courses/ の画像（チームメンバーのイラストから、題字を消して正方形に整えたもの）。

import chemistryImage from '../assets/courses/chemistry.webp';
import electricalImage from '../assets/courses/electrical.webp';
import informationImage from '../assets/courses/information.webp';
import mechanicalImage from '../assets/courses/mechanical.webp';
import roboticsImage from '../assets/courses/robotics.webp';

export interface CourseIntro {
  code: string;
  name: string;
  image: string;
  description: string;
}

export const COURSE_INTROS: CourseIntro[] = [
  { code: 'mechanical', name: '機械創造', image: mechanicalImage, description: '機械の設計やものづくりを学ぶ' },
  { code: 'robotics', name: '知能ロボット', image: roboticsImage, description: 'ロボットや制御技術を学ぶ' },
  { code: 'electrical', name: '電気電子', image: electricalImage, description: '電気や電子回路について学ぶ' },
  { code: 'information', name: '情報システム', image: informationImage, description: 'プログラミングや情報技術を学ぶ' },
  { code: 'chemistry', name: '物質化学', image: chemistryImage, description: '化学や物質について学ぶ' },
];
