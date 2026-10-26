<template>
  <el-tag :type="info.type" effect="light" size="small">{{ info.label }}</el-tag>
</template>

<script setup>
import { computed } from 'vue';
import { STATUS_MAP } from '@/utils/constants';

const props = defineProps({
  status: {
    type: [Number, String],
    default: 0,
  },
});

const info = computed(
  () => STATUS_MAP[Number(props.status)] || { label: '未知', type: 'info' },
);
</script>
