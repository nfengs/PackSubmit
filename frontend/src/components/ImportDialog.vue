<script setup>
import { computed, ref } from 'vue';
import { ElMessage } from 'element-plus';
import { saveBlob } from '@/api/attachment';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '批量导入' },
  tips: { type: String, default: '' },
  templateName: { type: String, default: '导入模板.xlsx' },
  templateLoader: { type: Function, default: null },
  uploader: { type: Function, required: true },
});

const emit = defineEmits(['update:modelValue', 'success']);

const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

const file = ref(null);
const uploading = ref(false);
const result = ref(null);

function onChange(uploadFile) {
  const raw = uploadFile.raw;
  const name = (raw?.name || '').toLowerCase();

  if (!name.endsWith('.xlsx') && !name.endsWith('.xls')) {
    ElMessage.warning('仅支持 .xlsx / .xls 文件');
    return;
  }

  if (raw.size > 10 * 1024 * 1024) {
    ElMessage.warning('文件不能超过 10MB');
    return;
  }

  file.value = raw;
}

async function downloadTemplate() {
  if (!props.templateLoader) return;
  const response = await props.templateLoader();
  await saveBlob(response, props.templateName);
}

async function submit() {
  if (!file.value) {
    ElMessage.warning('请先选择 Excel 文件');
    return;
  }

  uploading.value = true;
  result.value = null;

  try {
    result.value = await props.uploader(file.value);
    ElMessage.success('导入完成');
    emit('success');
  } finally {
    uploading.value = false;
  }
}

function close() {
  visible.value = false;
  file.value = null;
  result.value = null;
}
</script>

<template>
  <el-dialog v-model="visible" :title="title" width="640px" @closed="close">
    <el-alert v-if="tips" type="info" :closable="false" show-icon :title="tips" style="margin-bottom: 14px" />

    <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 12px">
      <el-upload :auto-upload="false" :show-file-list="false" accept=".xlsx,.xls" :on-change="onChange">
        <el-button>选择 Excel 文件</el-button>
      </el-upload>
      <span class="muted" style="font-size: 13px">{{ file ? file.name : '未选择文件' }}</span>
      <div class="spacer"></div>
      <el-button v-if="templateLoader" link type="primary" @click="downloadTemplate">下载模板</el-button>
    </div>

    <el-alert v-if="result" :type="result.errors?.length ? 'warning' : 'success'" :closable="false" show-icon
      style="margin-bottom: 10px"
      :title="`新增 ${result.created || 0} 条，更新 ${result.updated || 0} 条，跳过 ${result.skipped || 0} 条${result.drafts ? '，生成草稿 ' + result.drafts + ' 条' : ''}`" />

    <div v-if="result?.errors?.length" style="max-height: 220px; overflow: auto; font-size: 13px; line-height: 1.9">
      <div v-for="(item, index) in result.errors" :key="index" style="color: #c45656">{{ item }}</div>
    </div>

    <template #footer>
      <el-button @click="close">关闭</el-button>
      <el-button type="primary" :loading="uploading" @click="submit">开始导入</el-button>
    </template>
  </el-dialog>
</template>
