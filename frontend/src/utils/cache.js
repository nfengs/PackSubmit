import { TOKEN_KEY } from './token';

// 登录态不在清理范围内，避免「清缓存」把用户踢下线
const KEEP_KEYS = new Set([TOKEN_KEY]);

/**
 * 清理本应用写入的本地缓存：
 * - localStorage 中 packsubmit_ 前缀的配置（列设置、列宽等）
 * - sessionStorage
 * - CacheStorage（Service Worker / 预缓存资源）
 * 返回被清理的 localStorage 键名，便于提示用户
 */
export async function clearAppCache() {
  const removed = [];

  Object.keys(localStorage).forEach((key) => {
    if (key.startsWith('packsubmit_') && !KEEP_KEYS.has(key)) {
      localStorage.removeItem(key);
      removed.push(key);
    }
  });

  try {
    sessionStorage.clear();
  } catch (e) {
    // 忽略隐私模式下的限制
  }

  if (typeof window !== 'undefined' && window.caches?.keys) {
    try {
      const keys = await window.caches.keys();
      await Promise.all(keys.map((key) => window.caches.delete(key)));
    } catch (e) {
      // 浏览器不支持或被禁用时忽略
    }
  }

  return removed;
}
