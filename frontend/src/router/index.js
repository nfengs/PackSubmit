import { createRouter, createWebHistory } from 'vue-router';

const constantRoutes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/login/index.vue'),
    meta: { title: '登录', public: true },
  },
  {
    path: '/',
    name: 'Layout',
    component: () => import('@/layout/index.vue'),
    redirect: '/dashboard',
  },
  {
    path: '/403',
    name: 'Forbidden',
    component: () => import('@/views/error/403.vue'),
    meta: { title: '无权限' },
  },
  // 兜底路由不在此注册：动态路由就绪后再挂载，避免 /dashboard 未注册时
  // 兜底重定向与目标路由互相递归（RangeError: Maximum call stack size exceeded）
];

const router = createRouter({
  history: createWebHistory(),
  routes: constantRoutes,
});

export default router;
