<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { ElMessage } from 'element-plus';

import { deleteAttachment, fetchAttachmentBlob, uploadAttachment } from '@/api/attachment';

const props = defineProps({
  label: { type: String, default: '图片' },
  hint: { type: String, default: '' },
  attachType: { type: String, required: true },
  attachment: { type: Object, default: null },
  proposalId: { type: String, default: '' },
  editable: { type: Boolean, default: false },
});

const emit = defineEmits(['changed', 'removed']);

const uploading = ref(false);
const previewUrl = ref('');
const previewVisible = ref(false);

const hasAttachment = computed(() => !!props.attachment);

async function loadPreview() {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = '';
  }

  if (!props.attachment) return;

  try {
    previewUrl.value = await fetchAttachmentBlob(props.attachment.id, 'preview');
  } catch (e) {
    previewUrl.value = '';
  }
}

watch(() => props.attachment?.id, loadPreview, { immediate: true });

onBeforeUnmount(() => {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
});

async function doUpload(options) {
  if (!props.proposalId) {
    ElMessage.warning('请先保存方案后再上传图片');
    return;
  }

  uploading.value = true;

  try {
    if (props.attachment) {
      await deleteAttachment(props.attachment.id);
      emit('removed', props.attachment);
    }

    const data = await uploadAttachment({
      file: options.file,
      proposalId: props.proposalId,
      attachType: props.attachType,
    });

    emit('changed', data.attachment);
    ElMessage.success(data.reused ? '上传成功（已复用服务器上的相同文件）' : '上传成功');
  } catch (e) {
    // 错误提示由 request 拦截器统一处理
  } finally {
    uploading.value = false;
  }
}

async function remove() {
  if (!props.attachment) return;

  await deleteAttachment(props.attachment.id);
  emit('removed', props.attachment);
  ElMessage.success('已删除');
}

function openPreview() {
  if (previewUrl.value) previewVisible.value = true;
}
</script>

<template>
  <div class="image-slot">
    <div class="slot-title">
      <span>{{ label }}</span>
      <el-tag v-if="hasAttachment" size="small" type="success" effect="plain">已上传</el-tag>
      <el-tag v-else size="small" type="info" effect="plain">待上传</el-tag>
    </div>

    <div class="preview" @click="openPreview">
      <img v-if="previewUrl" :src="previewUrl" :alt="label" />
      <span v-else class="muted" style="font-size: 12px">暂无图片</span>
    </div>

    <div class="slot-hint">{{ hint }}</div>

    <div v-if="editable" style="margin-top: 8px; display: flex; gap: 8px">
      <el-upload :show-file-list="false" :http-request="doUpload" accept="image/png,image/jpeg" :disabled="uploading">
        <el-button size="small" :loading="uploading">{{ hasAttachment ? '重新上传' : '上传图片' }}</el-button>
      </el-upload>
      <el-button v-if="hasAttachment" size="small" type="danger" plain @click="remove">删除</el-button>
    </div>

    <el-dialog v-model="previewVisible" :title="label" width="760px" append-to-body>
      <img v-if="previewUrl" :src="previewUrl" style="width: 100%" />
    </el-dialog>
  </div>
</template>
