import axios from 'axios';
import { ElMessage } from 'element-plus';
import { getToken, removeToken, setToken } from '@/utils/token';

const service = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  timeout: 60000,
});

service.interceptors.request.use((config) => {
  const token = getToken();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

let refreshing = null;

function refreshToken() {
  if (!refreshing) {
    refreshing = axios
      .post(`${service.defaults.baseURL}/auth/refresh`, null, {
        headers: { Authorization: `Bearer ${getToken()}` },
        timeout: 15000,
      })
      .then((response) => {
        const body = response.data || {};
        const token = body.data?.token;
        if (body.code === 0 && token) {
          setToken(token);
          return token;
        }
        throw new Error(body.message || '登录已失效');
      })
      .finally(() => {
        refreshing = null;
      });
  }
  return refreshing;
}

function redirectToLogin() {
  removeToken();
  const { pathname, search } = window.location;
  if (pathname.startsWith('/login')) return;
  window.location.href = `/login?redirect=${encodeURIComponent(pathname + search)}`;
}

service.interceptors.response.use(
  (response) => {
    if (response.config.responseType === 'blob') return response;
    const body = response.data || {};
    if (body.code === 0) return body.data;
    ElMessage.error(body.message || '请求失败');
    return Promise.reject(new Error(body.message || '请求失败'));
  },
  async (error) => {
    const status = error.response?.status;

    // 401 先静默续期一次并重放请求
    if (status === 401 && error.config && !error.config._retried) {
      const url = String(error.config.url || '');
      if (!url.includes('/auth/login') && !url.includes('/auth/refresh') && getToken()) {
        error.config._retried = true;
        try {
          const token = await refreshToken();
          error.config.headers = { ...(error.config.headers || {}), Authorization: `Bearer ${token}` };
          return await service(error.config);
        } catch (e) {
          redirectToLogin();
          return Promise.reject(error);
        }
      }
      redirectToLogin();
      return Promise.reject(error);
    }

    const message = error.response?.data?.message || error.message || '网络异常';
    if (status !== 401) ElMessage.error(message);
    return Promise.reject(error);
  },
);

export default service;
