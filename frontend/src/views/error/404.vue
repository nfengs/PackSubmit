<script setup>
import { useRouter } from 'vue-router';

const router = useRouter();
</script>

<template>
  <div class="wrapper">
    <div class="code">404</div>
    <h2>页面不存在</h2>
    <p class="muted">地址可能已变更或没有访问权限。</p>
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
  color: #8a93a6;
  letter-spacing: 2px;
}

h2 {
  margin: 0;
}

p {
  margin: 0 0 10px;
}
</style>
