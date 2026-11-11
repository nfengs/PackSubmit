<script setup>
import { computed } from 'vue';
import { formatDateTime } from '@/utils/format';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '维护日志' },
  logs: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});
</script>

<template>
  <el-drawer v-model="visible" :title="title" size="560px">
    <div v-loading="loading">
      <el-timeline v-if="logs.length">
        <el-timeline-item v-for="item in logs" :key="item.id" :timestamp="formatDateTime(item.created_at)"
          placement="top">
          <div style="font-weight: 600">{{ item.action_name }}</div>
          <div class="muted" style="font-size: 13px; white-space: pre-wrap; line-height: 1.7">
            {{ item.content || '—' }}
          </div>
        </el-timeline-item>
      </el-timeline>
      <div v-else class="empty">暂无维护日志</div>
    </div>
  </el-drawer>
</template>
