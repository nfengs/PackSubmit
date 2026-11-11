import { ElMessage } from 'element-plus';
import { useUserStore } from '@/store/user';

const viewModules = import.meta.glob('../views/**/*.vue');

let routesReady = false;
const dynamicNames = [];

function resolveView(component) {
  const key = `../views/${component}.vue`;
  if (viewModules[key]) return viewModules[key];
  console.error(`[router] 未找到页面组件：${key}`);
  return () => import('@/views/error/403.vue');
}

export function markRoutesStale() {
  routesReady = false;
}

export function resetDynamicRoutes(router) {
  dynamicNames.forEach((name) => {
    if (router.hasRoute(name)) router.removeRoute(name);
  });
  dynamicNames.length = 0;
  routesReady = false;
}

export function setupRouterGuard(router) {
  router.beforeEach(async (to) => {
    if (to.meta?.public) return true;

    const userStore = useUserStore();

    if (!userStore.token) {
      return { path: '/login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} };
    }

    if (!routesReady) {
      try {
        if (!userStore.routes.length) await userStore.fetchProfile();

        userStore.routes.forEach((route) => {
          if (!route.component || router.hasRoute(route.name)) return;
          router.addRoute('Layout', {
            path: route.path,
            name: route.name,
            component: resolveView(route.component),
            meta: {
              title: route.title,
              permission: route.permission,
              hidden: route.hidden,
            },
          });
          dynamicNames.push(route.name);
        });

        routesReady = true;
        return { ...to, replace: true };
      } catch (e) {
        userStore.clear();
        return { path: '/login' };
      }
    }

    const permission = to.meta?.permission;
    if (permission && !userStore.hasPerm(permission)) {
      ElMessage.error('无访问权限');
      return { path: '/403' };
    }

    return true;
  });

  router.afterEach((to) => {
    document.title = to.meta?.title ? `${to.meta.title} · PackSubmit` : 'PackSubmit 包装方案提交评审系统';
  });
}
