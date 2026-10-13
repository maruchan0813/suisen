// API の返事の形。PHP の API（public/api/）と合わせる。

export type ApiResult<T> =
  | { ok: true; data: T }
  | { ok: false; error: { code: string; message: string } };

export interface SessionInfo {
  logged_in: boolean;
  pending_email: string | null;
  csrf_token: string;
  verification_enabled: boolean;
  code_digits: number;
  network_error_message: string;
}

export interface Status {
  course_name: string | null;
  diagnosed_at: string | null;
  diagnosis_count: number;
  active_conversation_id: number | null;
  latest_result_conversation_id: number | null;
}

export type Role = 'user' | 'assistant';

export interface ChatMessage {
  role: Role;
  content: string;
  /** 返事の根拠にしたシラバス（保存しないので、読み込み直すと消える） */
  sources?: string[];
}

export interface Conversation {
  id: number;
  is_active: boolean;
  messages: ChatMessage[];
  can_diagnose: boolean;
}

export interface SendResult {
  reply: string;
  turn_count: number;
  can_diagnose: boolean;
  sources: string[];
}

export interface Score {
  label: string;
  score: number;
}

export interface CareerExample {
  type: string;
  is_study: boolean;
  destination: string;
  course_name: string;
}

export interface KctCareers {
  grad: number;
  job: number;
  study: number;
  jobs: string[];
  schools: string[];
}

/** 診断結果の画面（S5）に出す中身 */
export interface DiagnosisResultView {
  conversation_id: number;
  course_code: string;
  course_name: string;
  catch: string;
  diagnosed_at: string;
  interests: Score[];
  styles: Score[];
  messages: string[];
  sources: string[];
  careers: CareerExample[];
  kct: KctCareers | null;
}

export interface DiagnoseResult extends Redirect {
  course_name: string;
  recommendation_text: string;
  career_text: string;
  sources: string[];
}

export interface Redirect {
  redirect: string;
}

export interface StartedConversation extends Redirect {
  conversation_id: number;
}
