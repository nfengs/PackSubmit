<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { Lock, User } from '@element-plus/icons-vue';

import { useUserStore } from '@/store/user';

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();

const formRef = ref(null);
const loading = ref(false);

const form = reactive({
  username: '',
  password: '',
});

const rules = {
  username: [{ required: true, message: '请输入登录账号', trigger: 'blur' }],
  password: [{ required: true, message: '请输入登录密码', trigger: 'blur' }],
};

async function submit() {
  if (!formRef.value) return;

  const valid = await formRef.value.validate().catch(() => false);
  if (!valid) return;

  loading.value = true;

  try {
    await userStore.login({ username: form.username.trim(), password: form.password });
    ElMessage.success('登录成功');
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard';
    router.replace(redirect);
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="login-page">
    <div class="panel">
      <aside class="intro">
        <div class="logo">PS</div>
        <h1>包装方案提交评审系统</h1>
        <p>受控建单 · 在线填报 · 严格评审 · 全程留痕 · 变更可追溯</p>
        <ul>
          <li>供应商只需填报，方案草稿由零件号清单自动生成</li>
          <li>四类包装图片在线管理，一键导出标准包装确认单</li>
          <li>归档方案发起变更，新旧两版并存互不覆盖</li>
        </ul>
      </aside>

      <div class="form-wrap">
        <h2>账号登录</h2>
        <p class="muted">请使用管理员分配的账号登录</p>

        <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @keyup.enter="submit">
          <el-form-item label="登录账号" prop="username">
            <el-input v-model="form.username" size="large" placeholder="请输入账号" :prefix-icon="User" />
          </el-form-item>
          <el-form-item label="登录密码" prop="password">
            <el-input v-model="form.password" size="large" type="password" show-password
              placeholder="请输入密码" :prefix-icon="Lock" />
          </el-form-item>
          <el-button type="primary" size="large" class="submit" :loading="loading" @click="submit">
            登 录
          </el-button>
        </el-form>

        <div class="demo">
          演示账号：<code>admin / admin123</code> · <code>reviewer / reviewer123</code> ·
          <code>submitter / submitter123</code>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: radial-gradient(circle at 20% 20%, #1b3b66, #0b1a2e 60%);
  padding: 24px;
}

.panel {
  width: 980px;
  max-width: 100%;
  min-height: 520px;
  display: grid;
  grid-template-columns: 1.05fr 1fr;
  background: #fff;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 30px 60px rgba(4, 15, 32, 0.4);
}

.intro {
  background: linear-gradient(150deg, #10233f, #1c3f70);
  color: #fff;
  padding: 48px 40px;
}

.logo {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  background: linear-gradient(135deg, #2f7ae5, #1f5fbf);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  margin-bottom: 28px;
}

.intro h1 {
  font-size: 24px;
  margin: 0 0 12px;
}

.intro p {
  color: rgba(255, 255, 255, 0.72);
  line-height: 1.7;
  margin: 0 0 26px;
}

.intro ul {
  margin: 0;
  padding-left: 18px;
  color: rgba(255, 255, 255, 0.82);
  line-height: 2;
  font-size: 13px;
}

.form-wrap {
  padding: 56px 48px;
}

.form-wrap h2 {
  margin: 0 0 6px;
  font-size: 22px;
}

.form-wrap .muted {
  margin: 0 0 26px;
  font-size: 13px;
}

.submit {
  width: 100%;
  margin-top: 8px;
}

.demo {
  margin-top: 22px;
  font-size: 12px;
  color: #90a0b5;
  line-height: 1.8;
}

.demo code {
  background: #f2f5fa;
  padding: 1px 5px;
  border-radius: 4px;
  color: #4a5b73;
}

@media (max-width: 860px) {
  .panel {
    grid-template-columns: 1fr;
  }

  .intro {
    display: none;
  }
}
</style>
