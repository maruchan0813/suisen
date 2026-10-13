import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// ビルドしたファイルは public/app/ に置き、MAMP・XAMPP の Apache が配る。
// 開発中（npm run dev）は、/api への呼び出しを MAMP（http://localhost）へ転送する。
export default defineConfig({
  plugins: [react()],
  base: './',
  build: {
    outDir: '../public/app',
    emptyOutDir: true,
  },
  server: {
    proxy: { '/api': 'http://localhost' },
  },
});
