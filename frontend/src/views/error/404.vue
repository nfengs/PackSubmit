<template>
  <div class="error-page">
    <el-result icon="warning" title="404" sub-title="页面不存在或您没有访问权限">
      <template #extra>
        <el-button type="primary" @click="router.replace('/home')">返回工作台</el-button>
      </template>
    </el-result>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';

const router = useRouter();
</script>

<style scoped>
.error-page {
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
