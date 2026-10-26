<template>
  <el-container class="layout">
    <el-aside :width="collapsed ? '64px' : '220px'" class="layout-aside">
      <div class="logo">
        <el-icon :size="22"><Box /></el-icon>
        <span v-show="!collapsed" class="logo-text">YFPOCQ 包装方案评审</span>
      </div>

      <el-menu
        :default-active="activeMenu"
        :collapse="collapsed"
        :collapse-transition="false"
        router
        background-color="#1f2d3d"
        text-color="#c0c4cc"
        active-text-color="#409eff"
        class="layout-menu"
      >
        <template v-for="group in menuGroups" :key="group.title || 'main'">
          <div v-if="group.title && !collapsed" class="menu-group-title">{{ group.title }}</div>
          <el-menu-item v-for="item in group.items" :key="item.path" :index="item.path">
            <el-icon><component :is="item.icon || 'Document'" /></el-icon>
            <template #title>{{ item.title }}</template>
          </el-menu-item>
        </template>
      </el-menu>
    </el-aside>

    <el-container>
      <el-header class="layout-header">
        <div class="header-left">
          <el-icon class="collapse-btn" @click="collapsed = !collapsed">
            <Expand v-if="collapsed" />
            <Fold v-else />
          </el-icon>
          <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/home' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>{{ route.meta?.title || '工作台' }}</el-breadcrumb-item>
          </el-breadcrumb>
        </div>

        <div class="header-right">
          <el-tag size="small" effect="plain">{{ userStore.roleName }}</el-tag>
          <el-dropdown @command="handleCommand">
            <span class="user-trigger">
              {{ userStore.displayName }}
              <el-icon><ArrowDown /></el-icon>
            </span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="profile">个人信息</el-dropdown-item>
                <el-dropdown-item command="password">修改密码</el-dropdown-item>
                <el-dropdown-item command="logout" divided>退出登录</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>

      <el-main class="layout-main">
        <router-view />
      </el-main>
    </el-container>
  </el-container>

  <el-dialog v-model="profileVisible" title="个人信息" width="420px">
    <el-descriptions :column="1" border>
      <el-descriptions-item label="登录账号">{{ userStore.user?.username }}</el-descriptions-item>
      <el-descriptions-item label="姓名">{{ userStore.user?.real_name }}</el-descriptions-item>
      <el-descriptions-item label="角色">{{ userStore.roleName }}</el-descriptions-item>
      <el-descriptions-item label="部门/供应商">{{ userStore.user?.dept || '-' }}</el-descriptions-item>
      <el-descriptions-item label="联系电话">{{ userStore.user?.phone || '-' }}</el-descriptions-item>
    </el-descriptions>
  </el-dialog>

  <el-dialog
    v-model="passwordVisible"
    title="修改密码"
    width="420px"
    destroy-on-close
    @closed="resetPasswordForm"
  >
    <el-form
      ref="passwordFormRef"
      :model="passwordForm"
      :rules="passwordRules"
      label-width="90px"
    >
      <el-form-item label="原密码" prop="old_password">
        <el-input
          v-model="passwordForm.old_password"
          type="password"
          show-password
          placeholder="请输入原密码"
        />
      </el-form-item>
      <el-form-item label="新密码" prop="new_password">
        <el-input
          v-model="passwordForm.new_password"
          type="password"
          show-password
          maxlength="32"
          placeholder="6~32 位字符"
        />
      </el-form-item>
      <el-form-item label="确认密码" prop="confirm_password">
        <el-input
          v-model="passwordForm.confirm_password"
          type="password"
          show-password
          maxlength="32"
          placeholder="请再次输入新密码"
        />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="passwordVisible = false">取消</el-button>
      <el-button type="primary" :loading="passwordSaving" @click="handleChangePassword">
        确定
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useUserStore } from '@/store/user';
import { constantRoutes, resetDynamicRoutes } from '@/router';
import { changePassword } from '@/api/auth';

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();

const collapsed = ref(false);
const profileVisible = ref(false);

const passwordVisible = ref(false);
const passwordSaving = ref(false);
const passwordFormRef = ref(null);
const passwordForm = reactive({
  old_password: '',
  new_password: '',
  confirm_password: '',
});

const passwordRules = {
  old_password: [{ required: true, message: '请输入原密码', trigger: 'blur' }],
  new_password: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    {
      pattern: /^(?=.*[A-Za-z])(?=.*\d)[^\s]{8,32}$/,
      message: '密码需 8-32 位，且同时包含字母和数字',
      trigger: 'blur',
    },
  ],
  confirm_password: [
    { required: true, message: '请再次输入新密码', trigger: 'blur' },
    {
      validator: (rule, value, callback) => {
        if (value !== passwordForm.new_password) {
          callback(new Error('两次输入的新密码不一致'));
          return;
        }
        callback();
      },
      trigger: 'blur',
    },
  ],
};

/**
 * 菜单数据：静态路由 + 已注册的管理员动态路由
 */
const menuGroups = computed(() => {
  const toMenu = (item) => ({
    path: router.resolve({ name: item.name }).path,
    title: item.meta?.title,
    icon: item.meta?.icon,
  });

  const role = Number(userStore.role);
  const allowed = (item) => !item.meta?.hidden
    && (!item.meta?.roles || item.meta.roles.map(Number).includes(role));

  const root = constantRoutes.find((item) => item.path === '/');
  const groups = [
    {
      title: '',
      items: (root?.children || []).filter(allowed).map(toMenu),
    },
  ];

  if (userStore.isAdmin) {
    const admin = router.getRoutes().find((item) => item.path === '/admin');
    if (admin) {
      groups.push({
        title: '系统管理',
        items: (admin.children || []).filter((item) => !item.meta?.hidden).map(toMenu),
      });
    }
  }

  return groups;
});

/**
 * 详情/编辑页高亮到方案列表
 */
const activeMenu = computed(() => {
  if (route.path.startsWith('/proposal/')) return '/proposal/list';
  return route.path;
});

/**
 * 顶部下拉操作
 * @param {'profile'|'password'|'logout'} command
 */
async function handleCommand(command) {
  if (command === 'profile') {
    profileVisible.value = true;
    return;
  }

  if (command === 'password') {
    passwordVisible.value = true;
    return;
  }

  if (command === 'logout') {
    await ElMessageBox.confirm('确定要退出登录吗？', '提示', { type: 'warning' });
    await userStore.logout();
    resetDynamicRoutes();
    router.replace('/login');
  }
}

function resetPasswordForm() {
  passwordForm.old_password = '';
  passwordForm.new_password = '';
  passwordForm.confirm_password = '';
  passwordFormRef.value?.clearValidate();
}

/**
 * 提交修改密码
 */
async function handleChangePassword() {
  const valid = await passwordFormRef.value.validate().catch(() => false);
  if (!valid) return;

  passwordSaving.value = true;

  try {
    const data = await changePassword({ ...passwordForm });

    // 改密后旧 token 已失效，保存后端补发的新 token 以保持当前会话
    if (data?.token) {
      userStore.applyToken(data.token);
    }

    ElMessage.success('密码修改成功，其他设备需重新登录');
    passwordVisible.value = false;
  } finally {
    passwordSaving.value = false;
  }
}
</script>

<style scoped>
.layout {
  height: 100vh;
}

.layout-aside {
  background: #1f2d3d;
  transition: width 0.2s;
  overflow-x: hidden;
}

.logo {
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: #fff;
  font-weight: 600;
  letter-spacing: 1px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.logo-text {
  white-space: nowrap;
}

.layout-menu {
  border-right: none;
}

.menu-group-title {
  padding: 14px 20px 6px;
  font-size: 12px;
  color: #7a8899;
}

.layout-header {
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fff;
  border-bottom: 1px solid #ebeef5;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 14px;
}

.collapse-btn {
  cursor: pointer;
  font-size: 18px;
  color: #606266;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-trigger {
  display: flex;
  align-items: center;
  gap: 4px;
  cursor: pointer;
  color: #303133;
  font-size: 14px;
}

.layout-main {
  background: #f5f7fa;
  padding: 16px;
  overflow-y: auto;
}
</style>
