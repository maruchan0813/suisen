// 「診断を始める」ボタンの前に置くアイコン。吹き出しと黄色い星（会話からひらめきを見つける）。
// Claude Design で描いた案 A（SUISEN 診断ボタンのアイコン）。絵文字ではなく SVG なので、どの端末でも同じ形に見える。

interface StartIconProps {
  size?: string | number;
}

export function StartIcon({ size = '2.3em' }: StartIconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 32 32" aria-hidden="true" focusable="false" className="start-icon">
      <path
        d="M7 6h14a4 4 0 0 1 4 4v7a4 4 0 0 1-4 4h-6.5l-5.5 4.5V21H7a4 4 0 0 1-4-4v-7a4 4 0 0 1 4-4z"
        fill="#e3f2fd"
        stroke="#3a9be6"
        strokeWidth="2.2"
        strokeLinejoin="round"
      />
      <circle cx="9.5" cy="13.5" r="1.5" fill="#3a9be6" />
      <circle cx="14" cy="13.5" r="1.5" fill="#3a9be6" />
      <circle cx="18.5" cy="13.5" r="1.5" fill="#3a9be6" />
      <path d="M25.5 1.5l1.7 4.1 4.1 1.7-4.1 1.7-1.7 4.1-1.7-4.1-4.1-1.7 4.1-1.7z" fill="#f8c94a" />
    </svg>
  );
}
