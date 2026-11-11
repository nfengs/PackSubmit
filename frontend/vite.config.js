import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
    },
  },
  server: {
    // 同时监听 IPv4 / IPv6，避免 localhost 解析到 ::1 时连接被拒
    host: '::',
    port: 5273,
    // 端口被占用时直接报错，避免静默漂移到 5274 导致访问地址不一致
    strictPort: true,
    proxy: {
      // 开发环境代理到 Go 后端
      '/api': {
        target: 'http://127.0.0.1:8090',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: 'dist',
    chunkSizeWarningLimit: 1800,
  },
});
