// 機構：PHP の API を呼ぶ。CSRF トークンの付加、JSON の変換、通信エラーの形をそろえる。
// どの画面でも同じ形（ApiResult）で結果を受け取れるようにする。

import type {
  ApiResult,
  Conversation,
  DiagnoseResult,
  DiagnosisResultView,
  Redirect,
  SendResult,
  SessionInfo,
  StartedConversation,
  Status,
} from './types';

// ビルド後は /app/ から、開発中は / から呼ぶ。どちらでも /api/ を指すように、相対の場所にする。
const API_BASE = '../api/';

let csrfToken = '';
let networkErrorMessage = '通信に失敗しました。';

/** session.php で受け取った値を覚える。ログイン・ログアウトのたびに読み直す。 */
export function rememberSession(session: SessionInfo): void {
  csrfToken = session.csrf_token;
  networkErrorMessage = session.network_error_message;
}

async function request<T>(path: string, init: RequestInit): Promise<ApiResult<T>> {
  try {
    const response = await fetch(API_BASE + path, { ...init, credentials: 'same-origin' });
    return (await response.json()) as ApiResult<T>;
  } catch {
    return { ok: false, error: { code: 'network_error', message: networkErrorMessage } };
  }
}

function get<T>(path: string): Promise<ApiResult<T>> {
  return request<T>(path, { method: 'GET' });
}

function post<T>(path: string, body: object = {}): Promise<ApiResult<T>> {
  return request<T>(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
    body: JSON.stringify(body),
  });
}

export const api = {
  session: () => get<SessionInfo>('session.php'),
  status: () => get<Status>('status.php'),
  conversation: (id: number) => get<Conversation>(`conversation.php?id=${id}`),
  result: (conversationId: number) => get<DiagnosisResultView>(`result.php?id=${conversationId}`),
  /** メール認証がオンなら確認コードを送って /verify へ、オフならそのままログインして /home へ */
  login: (email: string) => post<Redirect>('login.php', { email }),
  resendCode: (email: string) => post<Redirect>('send_code.php', { email }),
  verifyCode: (code: string) => post<Redirect>('verify_code.php', { code }),
  logout: () => post<Redirect>('logout.php'),
  startConversation: () => post<StartedConversation>('start_conversation.php'),
  sendMessage: (conversationId: number, content: string) =>
    post<SendResult>('send_message.php', { conversation_id: conversationId, content }),
  diagnose: (conversationId: number) => post<DiagnoseResult>('diagnose.php', { conversation_id: conversationId }),
};

/** ログインの期限切れなど、ログイン画面に戻すべきエラーか。 */
export function isLoggedOut(result: ApiResult<unknown>): boolean {
  return !result.ok && result.error.code === 'not_logged_in';
}
