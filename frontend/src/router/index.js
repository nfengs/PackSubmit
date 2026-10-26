import { createRouter, createWebHistory } from 'vue-router';
import AppLayout from '@/components/AppLayout.vue';
import { getToken } from '@/utils/auth';
import { useUserStore } from '@/store/user';
import { ROLE } from '@/utils/constants';

/**
 * 静态路由：所有登录用户可访问
 */
export const constantRoutes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { title: '登录', public: true, hidden: true },
  },
  {
    path: '/',
    name: 'Layout',
    component: AppLayout,
    redirect: '/home',
    children: [
      {
        path: 'home',
        name: 'Home',
        component: () => import('@/views/Home.vue'),
        meta: { title: '工作台', icon: 'HomeFilled' },
      },
      {
        path: 'proposal/list',
        name: 'ProposalList',
        component: () => import('@/views/proposal/List.vue'),
        meta: { title: '方案列表', icon: 'Document' },
      },
      {
        path: 'proposal/edit/:id',
        name: 'ProposalEdit',
        component: () => import('@/views/proposal/Form.vue'),
        meta: { title: '编辑方案', hidden: true, roles: [ROLE.SUBMITTER, ROLE.ADMIN] },
      },
      {
        path: 'proposal/detail/:id',
        name: 'ProposalDetail',
        component: () => import('@/views/proposal/Detail.vue'),
        meta: { title: '方案详情', hidden: true },
      },
      {
        path: 'supplier-contacts',
        name: 'SupplierContactList',
        component: () => import('@/views/supplier/ContactList.vue'),
        meta: { title: '联系人维护', icon: 'Phone', roles: [ROLE.SUBMITTER, ROLE.ADMIN] },
      },
    ],
  },
  {
    path: '/403',
    name: 'Forbidden',
    component: () => import('@/views/error/403.vue'),
    meta: { title: '无权限', hidden: true },
  },
];

/**
 * 管理员动态路由（仅 role=3 注册）
 */
export const adminRoute = {
  path: '/admin',
  name: 'Admin',
  component: AppLayout,
  redirect: '/admin/users',
  meta: { title: '系统管理', icon: 'Setting', roles: [ROLE.ADMIN] },
  children: [
    {
      path: 'users',
      name: 'AdminUserList',
      component: () => import('@/views/admin/UserList.vue'),
      meta: { title: '用户管理', icon: 'User', roles: [ROLE.ADMIN] },
    },
    {
      path: 'dicts',
      name: 'AdminDictList',
      component: () => import('@/views/admin/DictList.vue'),
      meta: { title: '字典维护', icon: 'Collection', roles: [ROLE.ADMIN] },
    },
    {
      path: 'customers',
      name: 'AdminCustomerList',
      component: () => import('@/views/admin/CustomerList.vue'),
      meta: { title: '客户维护', icon: 'UserFilled', roles: [ROLE.ADMIN] },
    },
    {
      path: 'suppliers',
      name: 'AdminSupplierList',
      component: () => import('@/views/admin/SupplierList.vue'),
      meta: { title: '供应商维护', icon: 'OfficeBuilding', roles: [ROLE.ADMIN] },
    },
    {
      path: 'parts',
      name: 'AdminPartList',
      component: () => import('@/views/admin/PartList.vue'),
      meta: { title: '零件号维护', icon: 'Tickets', roles: [ROLE.ADMIN] },
    },
    {
      path: 'proposals',
      name: 'AdminProposalList',
      component: () => import('@/views/admin/AllProposal.vue'),
      meta: { title: '全量方案', icon: 'Files', roles: [ROLE.ADMIN] },
    },
    {
      path: 'proposal-detail',
      name: 'AdminProposalDetail',
      component: () => import('@/views/admin/ProposalReport.vue'),
      meta: { title: '全量详细方案', icon: 'DataAnalysis', roles: [ROLE.ADMIN] },
    },
    {
      path: 'logs',
      name: 'AdminLogList',
      component: () => import('@/views/admin/LogList.vue'),
      meta: { title: '审计日志', icon: 'Tickets', roles: [ROLE.ADMIN] },
    },
  ],
};

const router = createRouter({
  history: createWebHistory(),
  routes: constantRoutes,
  scrollBehavior: () => ({ top: 0 }),
});

let dynamicRegistered = false;

/**
 * 按角色动态注册路由
 * @param {number} role
 */
export function registerDynamicRoutes(role) {
  if (dynamicRegistered) return;

  if (Number(role) === ROLE.ADMIN) {
    router.addRoute(adminRoute);
  }

  // 兜底 404 最后注册，避免覆盖动态路由
  router.addRoute({
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('@/views/error/404.vue'),
    meta: { title: '页面不存在', hidden: true },
  });

  dynamicRegistered = true;
}

/**
 * 退出登录时移除动态路由
 */
export function resetDynamicRoutes() {
  if (router.hasRoute('Admin')) router.removeRoute('Admin');
  if (router.hasRoute('NotFound')) router.removeRoute('NotFound');
  dynamicRegistered = false;
}

// 全局路由守卫
router.beforeEach(async (to) => {
  const token = getToken();

  // 公开页面
  if (to.meta.public) {
    if (token && to.path === '/login') {
      return { path: '/home' };
    }
    return true;
  }

  // 未登录
  if (!token) {
    return { path: '/login', query: { redirect: to.fullPath } };
  }

  const userStore = useUserStore();

  // 刷新页面后恢复用户信息并注册动态路由
  if (!userStore.user) {
    try {
      await userStore.fetchProfile();
    } catch (e) {
      userStore.reset();
      return { path: '/login', query: { redirect: to.fullPath } };
    }
    registerDynamicRoutes(userStore.role);
    return { path: to.fullPath, replace: true };
  }

  // 已登录但动态路由尚未注册（例如刚登录）
  if (!dynamicRegistered) {
    registerDynamicRoutes(userStore.role);
    return { path: to.fullPath, replace: true };
  }

  // 按钮/页面级角色校验（前端体验层，后端仍强制校验）
  if (to.meta.roles && !to.meta.roles.map(Number).includes(Number(userStore.role))) {
    return { path: '/403' };
  }

  return true;
});

export default router;
