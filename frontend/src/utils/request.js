import axios from 'axios';
import { ElMessage } from 'element-plus';
import { getToken, removeToken, setToken } from './auth';

/**
 * 统一 axios 实例
 * baseURL 通过环境变量注入，禁止在页面内硬编码接口地址
 */
const service = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  timeout: 30000,
});

// 请求拦截：自动携带 token
service.interceptors.request.use(
  (config) => {
    const token = getToken();
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error),
);

/**
 * 跳转登录页（避免与 router 相互引用）
 */
function redirectToLogin() {
  removeToken();
  const { pathname, search } = window.location;
  if (pathname.startsWith('/login')) return;
  const redirect = encodeURIComponent(pathname + search);
  window.location.href = `/login?redirect=${redirect}`;
}

/** 刷新 token 的共享 Promise，避免并发请求重复刷新 */
let refreshing = null;

/**
 * 使用当前 token 换取新 token
 * @returns {Promise<string>}
 */
function refreshToken() {
  if (!refreshing) {
    refreshing = axios
      .post(`${service.defaults.baseURL}/auth/refresh`, null, {
        headers: { Authorization: `Bearer ${getToken()}` },
        timeout: 15000,
      })
      .then((response) => {
        const payload = response.data || {};
        const newToken = payload.data?.token;

        if (payload.code === 1 && newToken) {
          setToken(newToken);
          return newToken;
        }

        throw new Error(payload.msg || '登录状态已失效');
      })
      .finally(() => {
        refreshing = null;
      });
  }

  return refreshing;
}

/**
 * 401 时静默续期并重放原请求
 * @param {object} config 原请求配置
 * @returns {Promise<any>|null} 重放结果；null 表示无法续期
 */
async function retryWithRefresh(config) {
  if (!config || config._retried || !getToken()) return null;

  const url = String(config.url || '');

  if (url.includes('/auth/refresh') || url.includes('/auth/login')) return null;

  config._retried = true;

  try {
    const token = await refreshToken();
    config.headers = { ...(config.headers || {}), Authorization: `Bearer ${token}` };
    return await service(config);
  } catch (e) {
    return null;
  }
}

// 响应拦截：统一处理 code / 401 / 403
service.interceptors.response.use(
  (response) => {
    // 文件流直接返回原始响应
    if (response.config.responseType === 'blob') {
      return response;
    }

    const res = response.data || {};

    if (res.code === 1) {
      return res.data;
    }

    ElMessage.error(res.msg || '操作失败');
    return Promise.reject(new Error(res.msg || '操作失败'));
  },
  async (error) => {
    const status = error.response?.status;
    const data = error.response?.data;

    // 登录态失效：先尝试静默续期并重放请求
    if (status === 401) {
      const retried = await retryWithRefresh(error.config);

      if (retried) return retried;
    }

    // 文件流请求（导出/下载/预览）出错时，后端返回的 JSON 被包成了 Blob，需要解析后再提示
    if (data instanceof Blob) {
      const text = await data.text();
      let blobMsg = '';

      try {
        blobMsg = JSON.parse(text)?.msg || '';
      } catch (e) {
        blobMsg = '';
      }

      if (status === 401) {
        ElMessage.warning(blobMsg || '登录已失效，请重新登录');
        redirectToLogin();
        return Promise.reject(error);
      }

      if (status === 403) {
        ElMessage.error(blobMsg || '无权限执行该操作');
        return Promise.reject(error);
      }

      ElMessage.error(blobMsg || (status === 404 ? '文件或接口不存在' : '操作失败，请稍后重试'));
      return Promise.reject(error);
    }

    const msg = data?.msg || error.message || '网络异常，请稍后再试';

    if (status === 401) {
      ElMessage.warning(data?.msg || '登录已失效，请重新登录');
      redirectToLogin();
    } else if (status === 403) {
      ElMessage.error(data?.msg || '无权限执行该操作');
    } else {
      ElMessage.error(msg);
    }

    return Promise.reject(error);
  },
);

export default service;
