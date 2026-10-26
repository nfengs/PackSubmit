import { defineStore } from 'pinia';
import { login as loginApi, logout as logoutApi, getProfile } from '@/api/auth';
import { getToken, setToken, removeToken } from '@/utils/auth';
import { ROLE, ROLE_TEXT } from '@/utils/constants';

/**
 * 用户状态：token、用户信息、角色
 */
export const useUserStore = defineStore('user', {
  state: () => ({
    token: getToken(),
    user: null,
  }),

  getters: {
    /** 当前角色（数字） */
    role: (state) => Number(state.user?.role || 0),
    /** 是否管理员 */
    isAdmin: (state) => Number(state.user?.role || 0) === ROLE.ADMIN,
    /** 是否评审员 */
    isReviewer: (state) => Number(state.user?.role || 0) === ROLE.REVIEWER,
    /** 是否供应商 */
    isSubmitter: (state) => Number(state.user?.role || 0) === ROLE.SUBMITTER,
    /** 是否查看者（内部只读） */
    isViewer: (state) => Number(state.user?.role || 0) === ROLE.VIEWER,
    /** 角色名称 */
    roleName: (state) => ROLE_TEXT[Number(state.user?.role || 0)] || '',
    /** 显示名称 */
    displayName: (state) => state.user?.real_name || state.user?.username || '',
  },

  actions: {
    /**
     * 登录：token 写入 Pinia 与 localStorage
     * @param {{username:string,password:string}} payload
     */
    async login(payload) {
      const data = await loginApi(payload);
      this.token = data.token;
      this.user = data.user;
      setToken(data.token);
      return data;
    },

    /** 拉取当前用户信息（刷新页面后恢复会话） */
    async fetchProfile() {
      const data = await getProfile();
      this.user = data;
      return data;
    },

    /** 退出登录 */
    async logout() {
      try {
        await logoutApi();
      } catch (e) {
        // 忽略退出接口异常
      }
      this.reset();
    },

    /** 清空本地登录态 */
    reset() {
      this.token = '';
      this.user = null;
      removeToken();
    },
  },
});
