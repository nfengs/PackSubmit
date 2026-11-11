import { defineStore } from 'pinia';
import { getProfile, login as loginApi, logout as logoutApi } from '@/api/auth';
import { getToken, removeToken, setToken } from '@/utils/token';

export const useUserStore = defineStore('user', {
  state: () => ({
    token: getToken(),
    user: null,
    menus: [],
    routes: [],
    permissions: [],
  }),
  getters: {
    isAdmin: (state) => state.user?.role === 3,
    displayName: (state) => state.user?.real_name || state.user?.username || '',
    hasPerm: (state) => (code) => {
      const codes = Array.isArray(code) ? code : [code];
      return codes.some((item) => state.permissions.includes(item));
    },
  },
  actions: {
    applySession(data) {
      if (data?.token) {
        setToken(data.token);
        this.token = data.token;
      }
      this.user = data?.user || null;
      this.menus = data?.menus || [];
      this.routes = data?.routes || [];
      this.permissions = data?.permissions || [];
    },
    async login(payload) {
      const data = await loginApi(payload);
      this.applySession(data);
      return data;
    },
    async fetchProfile() {
      const data = await getProfile();
      this.applySession(data);
      return data;
    },
    async logout() {
      try {
        await logoutApi();
      } catch (e) {
        // 忽略退出接口异常，本地状态仍需清理
      }
      this.clear();
    },
    clear() {
      removeToken();
      this.token = '';
      this.user = null;
      this.menus = [];
      this.routes = [];
      this.permissions = [];
    },
  },
});
