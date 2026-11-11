<script setup>
import { computed, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Lock, SwitchButton } from '@element-plus/icons-vue';

import { changePassword } from '@/api/auth';
import { useUserStore } from '@/store/user';
import { resetDynamicRoutes } from '@/router/permission';
import { ROLE_TEXT } from '@/utils/constants';
import versionInfo from '@/version.json';

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();

const activeMenu = computed(() => route.path);
const roleText = computed(() => ROLE_TEXT[userStore.user?.role] || '-');

const passwordVisible = ref(false);
const submitting = ref(false);
const passwordForm = reactive({
  old_password: '',
  new_password: '',
  confirm_password: '',
});

function openPassword() {
  passwordForm.old_password = '';
  passwordForm.new_password = '';
  passwordForm.confirm_password = '';
  passwordVisible.value = true;
}

async function submitPassword() {
  if (passwordForm.new_password !== passwordForm.confirm_password) {
    ElMessage.warning('两次输入的新密码不一致');
    return;
  }

  submitting.value = true;

  try {
    const data = await changePassword(passwordForm);
    if (data?.token) {
      localStorage.setItem('packsubmit_token', data.token);
      userStore.token = data.token;
    }
    ElMessage.success('密码修改成功，其他设备需重新登录');
    passwordVisible.value = false;
  } finally {
    submitting.value = false;
  }
}

async function handleCommand(command) {
  if (command === 'password') {
    openPassword();
    return;
  }

  if (command === 'logout') {
    await ElMessageBox.confirm('确认退出登录？', '提示', { type: 'warning' });
    await userStore.logout();
    resetDynamicRoutes(router);
    router.push('/login');
  }
}
</script>

<template>
  <el-container class="layout">
    <el-aside class="aside" width="224px">
      <div class="brand">
        <div class="brand-mark">PS</div>
        <div class="brand-text">
          <b>PackSubmit</b>
          <small>包装方案提交评审系统</small>
        </div>
      </div>

      <el-menu :default-active="activeMenu" router class="menu">
        <template v-for="menu in userStore.menus" :key="menu.path">
          <el-sub-menu v-if="menu.children && menu.children.length" :index="menu.path">
            <template #title>
              <el-icon><component :is="menu.icon || 'Menu'" /></el-icon>
              <span>{{ menu.title }}</span>
            </template>
            <el-menu-item v-for="child in menu.children" :key="child.path" :index="child.path">
              {{ child.title }}
            </el-menu-item>
          </el-sub-menu>

          <el-menu-item v-else :index="menu.path">
            <el-icon><component :is="menu.icon || 'Menu'" /></el-icon>
            <span>{{ menu.title }}</span>
          </el-menu-item>
        </template>
      </el-menu>

      <div class="aside-footer">
        <span>前端 v{{ versionInfo.version }}</span>
        <span>{{ versionInfo.released_at }}</span>
      </div>
    </el-aside>

    <el-container>
      <el-header class="header">
        <div class="header-title">{{ route.meta.title || '' }}</div>

        <el-dropdown trigger="click" @command="handleCommand">
          <span class="user">
            {{ userStore.displayName }}
            <el-tag size="small" effect="plain">{{ roleText }}</el-tag>
            <el-icon><component :is="'ArrowDown'" /></el-icon>
          </span>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="password" :icon="Lock">修改密码</el-dropdown-item>
              <el-dropdown-item command="logout" :icon="SwitchButton" divided>退出登录</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </el-header>

      <el-main class="main">
        <router-view />
      </el-main>
    </el-container>

    <el-dialog v-model="passwordVisible" title="修改密码" width="420px">
      <el-form :model="passwordForm" label-width="96px">
        <el-form-item label="原密码">
          <el-input v-model="passwordForm.old_password" type="password" show-password />
        </el-form-item>
        <el-form-item label="新密码">
          <el-input v-model="passwordForm.new_password" type="password" show-password
            placeholder="8-32 位，需同时包含字母和数字" />
        </el-form-item>
        <el-form-item label="确认新密码">
          <el-input v-model="passwordForm.confirm_password" type="password" show-password />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="passwordVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submitPassword">保存</el-button>
      </template>
    </el-dialog>
  </el-container>
</template>
