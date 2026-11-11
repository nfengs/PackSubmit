<script setup>
import { useRouter } from 'vue-router';

const router = useRouter();
</script>

<template>
  <div class="wrapper">
    <div class="code">403</div>
    <h2>没有访问权限</h2>
    <p class="muted">当前账号无权访问该页面，如需开通请联系管理员。</p>
    <el-button type="primary" @click="router.replace('/dashboard')">返回工作台</el-button>
  </div>
</template>

<style scoped>
.wrapper {
  min-height: 60vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.code {
  font-size: 64px;
  font-weight: 700;
  color: #1f5fbf;
  letter-spacing: 2px;
}

h2 {
  margin: 0;
}

p {
  margin: 0 0 10px;
}
</style>
