<template>
  <div class="file-upload">
    <!-- 图片模式：上部方框（点击上传 / 预览），下部粘贴输入框 -->
    <div v-if="isImage" class="image-uploader">
      <div class="image-box" :style="boxStyle">
        <template v-if="files.length">
          <img
            v-if="thumbs[files[0].id]"
            :src="thumbs[files[0].id]"
            class="image-thumb"
            alt="图片"
            @click="openImage(files[0])"
          />
          <div v-else-if="thumbErrors[files[0].id]" class="image-thumb image-thumb--error">
            图片文件已丢失<br />请删除后重新上传
          </div>
          <div v-else class="image-thumb image-thumb--loading" />
          <div v-if="!disabled" class="image-action" title="删除" @click.stop="handleDelete(files[0])">
            <el-icon><Delete /></el-icon>
          </div>
        </template>

        <el-upload
          v-else-if="!disabled"
          class="image-upload-el"
          :show-file-list="false"
          :multiple="false"
          :disabled="uploading"
          :http-request="handleUpload"
          :before-upload="beforeUpload"
          :accept="acceptExt"
          :title="uploadTip"
        >
          <div class="upload-box" v-loading="uploading">
            <el-icon class="upload-icon"><Plus /></el-icon>
          </div>
        </el-upload>

        <div v-else class="image-empty">未上传</div>
      </div>

      <el-input
        v-if="!disabled && !reachedMax"
        class="paste-input"
        placeholder="将图片粘贴到此处上传"
        @beforeinput="blockTyping"
        @paste="handlePaste"
      />
    </div>

    <!-- 普通附件模式：表格 -->
    <template v-else>
      <div class="upload-actions">
        <el-upload
          v-if="!disabled"
          :show-file-list="false"
          :multiple="true"
          :http-request="handleUpload"
          :before-upload="beforeUpload"
          :accept="acceptExt"
        >
          <el-button type="primary" plain :loading="uploading">
            <el-icon class="mr-4"><Upload /></el-icon>
            {{ uploadText }}
          </el-button>
        </el-upload>
        <span class="upload-tip">{{ uploadTip }}</span>
      </div>

      <el-table :data="files" size="small" class="mt-16" border>
        <el-table-column prop="file_name" label="文件名" min-width="240" show-overflow-tooltip />
        <el-table-column prop="file_size_text" label="大小" width="100" align="center" />
        <el-table-column prop="create_time" label="上传时间" width="170" align="center" />
        <el-table-column label="操作" width="190" align="center">
          <template #default="{ row }">
            <el-button v-if="row.is_previewable" link type="primary" @click="handlePreview(row)">
              预览
            </el-button>
            <el-button link type="primary" @click="handleDownload(row)">下载</el-button>
            <el-button v-if="!disabled" link type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
        <template #empty>
          <span class="text-muted">{{ emptyText }}</span>
        </template>
      </el-table>
    </template>

    <el-dialog
      v-model="previewVisible"
      :title="previewFile?.file_name || '图片预览'"
      width="80%"
      top="5vh"
      destroy-on-close
      @closed="revokePreviewUrl"
    >
      <div v-loading="previewLoading" class="preview-body">
        <img v-if="previewType === 'image'" :src="previewUrl" class="preview-image" alt="预览" />
        <iframe v-else-if="previewType === 'pdf'" :src="previewUrl" class="preview-pdf" />
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Delete, Plus, Upload } from '@element-plus/icons-vue';
import {
  uploadAttachment,
  deleteAttachment,
  downloadAttachment,
  previewAttachment,
  resolveFileName,
} from '@/api/attachment';
import {
  ACCEPT_EXT,
  ACCEPT_EXT_LIST,
  ATTACH_TYPE,
  IMAGE_ATTACH_TYPES,
  IMAGE_EXT_LIST,
  MAX_FILE_SIZE,
  PREVIEW_EXT,
} from '@/utils/constants';

const props = defineProps({
  /** 附件列表 */
  modelValue: {
    type: Array,
    default: () => [],
  },
  /** 关联方案ID，新增时为 0/空表示暂存 */
  proposalId: {
    type: [Number, String],
    default: 0,
  },
  /** 附件类型（包装确认单图片分类） */
  attachType: {
    type: String,
    default: ATTACH_TYPE.ATTACHMENT,
  },
  /** 只读模式 */
  disabled: {
    type: Boolean,
    default: false,
  },
  /** 图片卡片边长（px） */
  size: {
    type: Number,
    default: 256,
  },
  /** 最多可上传数量，0 表示不限制（图片类默认 1 张） */
  maxCount: {
    type: Number,
    default: 0,
  },
});

/** 图片卡片尺寸 */
const boxStyle = computed(() => ({
  width: `${props.size}px`,
  height: `${props.size}px`,
}));

/** 是否为图片类附件 */
const isImage = computed(() => IMAGE_ATTACH_TYPES.includes(props.attachType));
const acceptExt = computed(() =>
  isImage.value ? IMAGE_EXT_LIST.map((ext) => `.${ext}`).join(',') : ACCEPT_EXT,
);
const uploadText = computed(() => (isImage.value ? '上传图片' : '上传附件'));
const uploadTip = computed(() =>
  isImage.value
    ? '仅支持 png / jpg 图片，每个位置仅可上传 1 张，不超过 10MB'
    : '支持 pdf / dwg / xlsx / docx / png / jpg，单个文件不超过 10MB',
);
const emptyText = computed(() => (isImage.value ? '暂无图片' : '暂无附件'));

const emit = defineEmits(['update:modelValue', 'change']);

const uploading = ref(false);
const previewVisible = ref(false);
const previewLoading = ref(false);
const previewUrl = ref('');
const previewType = ref('');
const previewFile = ref(null);
/** 预览地址是否来自缩略图缓存（是则不释放） */
let previewFromThumb = false;

const files = ref([...(props.modelValue || [])]);
/** 图片缩略图：附件ID => blob 地址 */
const thumbs = reactive({});
/** 缩略图加载失败（服务器物理文件缺失）：附件ID => true */
const thumbErrors = reactive({});

/** 可上传数量上限：图片类默认每个位置 1 张，0 表示不限制 */
const maxFiles = computed(() => (props.maxCount > 0 ? props.maxCount : isImage.value ? 1 : 0));

/** 是否已达上传上限 */
const reachedMax = computed(() => maxFiles.value > 0 && files.value.length >= maxFiles.value);

/**
 * 加载图片缩略图（预览接口需要鉴权，不能直接用 img src）
 * @param {Array} list
 */
async function loadThumbs(list) {
  if (!isImage.value) return;

  for (const file of list) {
    if (!file?.id || thumbs[file.id]) continue;

    try {
      const response = await previewAttachment(file.id);
      thumbs[file.id] = URL.createObjectURL(
        new Blob([response.data], {
          type: response.headers?.['content-type'] || 'image/jpeg',
        }),
      );
      delete thumbErrors[file.id];
    } catch (error) {
      // 单张失败不影响其他图片（多为服务器物理文件已被清理）
      thumbErrors[file.id] = true;
    }
  }
}

/**
 * 释放单个缩略图
 * @param {number} id
 */
function revokeThumb(id) {
  if (thumbs[id]) {
    URL.revokeObjectURL(thumbs[id]);
    delete thumbs[id];
  }

  delete thumbErrors[id];
}

// 父组件异步加载附件后同步到内部列表
watch(
  () => props.modelValue,
  (value) => {
    files.value = [...(value || [])];
    loadThumbs(files.value);
  },
  { deep: true },
);

onMounted(() => loadThumbs(files.value));

onBeforeUnmount(() => {
  Object.keys(thumbs).forEach((id) => revokeThumb(id));
  revokePreviewUrl();
});

/**
 * 同步附件列表到父组件
 * @param {Array} list
 */
function updateFiles(list) {
  files.value = list;
  emit('update:modelValue', list);
  emit('change', list);
}

/**
 * 校验文件是否为可正常解码的图片（防止改后缀伪装）
 * @param {File} file
 * @returns {Promise<boolean>}
 */
function isRealImage(file) {
  return new Promise((resolve) => {
    const url = URL.createObjectURL(file);
    const image = new Image();

    image.onload = () => {
      URL.revokeObjectURL(url);
      resolve(image.width > 0 && image.height > 0);
    };
    image.onerror = () => {
      URL.revokeObjectURL(url);
      resolve(false);
    };
    image.src = url;
  });
}

/**
 * 上传前校验扩展名、大小与图片真实性
 * @param {File} file
 */
async function beforeUpload(file) {
  if (reachedMax.value) {
    ElMessage.warning(
      maxFiles.value === 1 ? '每个位置只能上传 1 张图片' : `最多只能上传 ${maxFiles.value} 个文件`,
    );
    return false;
  }

  const ext = (file.name.split('.').pop() || '').toLowerCase();

  if (isImage.value && !IMAGE_EXT_LIST.includes(ext)) {
    ElMessage.error('图片只能上传 png、jpg 格式');
    return false;
  }

  if (!ACCEPT_EXT_LIST.includes(ext)) {
    ElMessage.error('仅支持 pdf、dwg、xlsx、docx、png、jpg 格式');
    return false;
  }

  if (file.size > MAX_FILE_SIZE) {
    ElMessage.error('单个文件不能超过 10MB');
    return false;
  }

  if (IMAGE_EXT_LIST.includes(ext) && !(await isRealImage(file))) {
    ElMessage.error('文件内容不是有效的图片，请上传 png、jpg 格式的图片');
    return false;
  }

  return true;
}

/**
 * 统一上传（选择文件与粘贴截图共用）
 * @param {File} file
 * @param {(percent:number)=>void} [onProgress]
 * @returns {Promise<object|null>} 成功返回附件数据，超出数量限制返回 null
 */
async function doUpload(file, onProgress) {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('attach_type', props.attachType);

  if (Number(props.proposalId) > 0) {
    formData.append('proposal_id', String(props.proposalId));
  }

  uploading.value = true;

  try {
    const data = await uploadAttachment(formData, (event) => {
      const percent = event.total ? Math.round((event.loaded / event.total) * 100) : 0;
      onProgress?.(percent);
    });

    // 并发上传等异常情况下超出数量上限，回滚刚上传的文件
    if (maxFiles.value > 0 && files.value.length >= maxFiles.value) {
      await deleteAttachment(data.id).catch(() => {});
      ElMessage.warning(
        maxFiles.value === 1 ? '每个位置只能上传 1 张图片' : `最多只能上传 ${maxFiles.value} 个文件`,
      );

      return null;
    }

    updateFiles([...files.value, data]);
    await loadThumbs([data]);
    ElMessage.success(data.reused ? '上传成功（已复用服务器上的相同文件）' : '上传成功');

    return data;
  } finally {
    uploading.value = false;
  }
}

/**
 * 自定义上传（el-upload 选择文件入口）
 * @param {object} options
 */
async function handleUpload(options) {
  try {
    const data = await doUpload(options.file, (percent) => options.onProgress({ percent }));

    if (data) {
      options.onSuccess(data);
    } else {
      options.onError(new Error('超出上传数量限制'));
    }
  } catch (error) {
    options.onError(error);
  }
}

/**
 * 阻止在粘贴输入框中键入任何内容（保留光标与粘贴事件）
 * @param {InputEvent} event
 */
function blockTyping(event) {
  event.preventDefault();
}

/**
 * 从剪贴板提取图片文件
 * 兼容两种来源：直接复制的图片文件、截图软件截取的位图
 * @param {DataTransfer} clipboard
 * @returns {File|null}
 */
function extractImageFile(clipboard) {
  // 情况 1：直接复制的图片文件（从文件管理器复制）
  for (const file of clipboard.files || []) {
    if ((file.type || '').startsWith('image/')) {
      return file;
    }
  }

  // 情况 2：截图软件截图（剪贴板为位图）
  for (const item of clipboard.items || []) {
    if (item.kind === 'file' && (item.type || '').startsWith('image/')) {
      const file = item.getAsFile();

      if (file) {
        return file;
      }
    }
  }

  return null;
}

/**
 * 规范化粘贴得到的图片文件（截图可能无扩展名，或扩展名与内容不符）
 * @param {File} file
 * @returns {File|null}
 */
function normalizePastedImage(file) {
  const type = (file.type || '').toLowerCase();
  const extMap = { 'image/png': 'png', 'image/jpeg': 'jpg', 'image/jpg': 'jpg' };
  const ext = extMap[type];

  if (!ext) {
    ElMessage.error('截图格式不支持，请使用 png / jpg 图片');

    return null;
  }

  const currentExt = (file.name.split('.').pop() || '').toLowerCase();

  if (IMAGE_EXT_LIST.includes(currentExt)) {
    return file;
  }

  return new File([file], `screenshot-${Date.now()}.${ext}`, { type });
}

/**
 * 粘贴上传（仅图片模式，每个位置仍限 1 张）
 * 支持直接复制的图片文件与截图软件截图
 * @param {ClipboardEvent} event
 */
async function handlePaste(event) {
  if (!isImage.value || props.disabled || uploading.value) return;

  const clipboard = event.clipboardData;

  if (!clipboard) return;

  const file = extractImageFile(clipboard);

  // 剪贴板不是图片时保持默认粘贴行为
  if (!file) return;

  event.preventDefault();

  if (reachedMax.value) {
    ElMessage.warning('每个位置只能上传 1 张图片，请先删除已有图片');

    return;
  }

  const pasted = normalizePastedImage(file);

  if (!pasted) return;

  if (!(await beforeUpload(pasted))) return;

  try {
    await doUpload(pasted);
  } catch (error) {
    ElMessage.error(error?.message || '图片上传失败');
  }
}

/**
 * 下载附件
 * @param {object} row
 */
async function handleDownload(row) {
  const response = await downloadAttachment(row.id);
  const fileName = resolveFileName(response, row.file_name);
  const url = URL.createObjectURL(new Blob([response.data]));
  const link = document.createElement('a');

  link.href = url;
  link.download = fileName;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

/**
 * 点击图片放大查看
 * @param {object} file
 */
function openImage(file) {
  if (!thumbs[file.id]) return;

  previewFile.value = null;
  previewType.value = 'image';
  previewUrl.value = thumbs[file.id];
  previewFromThumb = true;
  previewLoading.value = false;
  previewVisible.value = true;
}

/**
 * 预览附件（pdf / 图片，表格模式使用）
 * @param {object} row
 */
async function handlePreview(row) {
  const ext = (row.file_ext || '').toLowerCase();

  if (!PREVIEW_EXT.includes(ext)) {
    ElMessage.warning('该类型文件不支持在线预览，请下载后查看');
    return;
  }

  previewFile.value = row;
  previewType.value = ext === 'pdf' ? 'pdf' : 'image';
  previewVisible.value = true;
  previewLoading.value = true;
  previewFromThumb = false;

  try {
    const response = await previewAttachment(row.id);
    revokePreviewUrl();
    previewUrl.value = URL.createObjectURL(
      new Blob([response.data], {
        type: response.headers?.['content-type'] || 'application/octet-stream',
      }),
    );
  } catch (error) {
    previewVisible.value = false;
  } finally {
    previewLoading.value = false;
  }
}

/**
 * 释放预览 blob 地址（缩略图缓存地址不释放）
 */
function revokePreviewUrl() {
  if (previewFromThumb) {
    previewUrl.value = '';
    previewFromThumb = false;
    return;
  }

  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = '';
  }
}

/**
 * 删除附件
 * @param {object} row
 */
async function handleDelete(row) {
  const label = isImage.value ? '图片' : `附件「${row.file_name}」`;

  await ElMessageBox.confirm(`确定删除${label}吗？`, '提示', { type: 'warning' });
  await deleteAttachment(row.id);
  revokeThumb(row.id);
  updateFiles(files.value.filter((item) => item.id !== row.id));
  ElMessage.success('已删除');
}
</script>

<style scoped>
.upload-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.upload-tip {
  font-size: 12px;
  color: #909399;
}

.mr-4 {
  margin-right: 4px;
}

/* ---------- 图片卡片 ---------- */
.image-uploader {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
}

.image-box {
  position: relative;
  border: 1px solid #dcdfe6;
  border-radius: 6px;
  overflow: hidden;
  background: #f5f7fa;
}

.image-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  font-size: 12px;
  color: #c0c4cc;
  background: #fafafa;
}

.image-thumb {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  cursor: zoom-in;
}

.image-thumb--loading {
  background: linear-gradient(90deg, #f2f3f5 25%, #e9ebef 37%, #f2f3f5 63%);
  background-size: 400% 100%;
  animation: image-skeleton 1.4s ease infinite;
}

.image-thumb--error {
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  font-size: 12px;
  line-height: 1.6;
  color: #f56c6c;
  background: #fef0f0;
  cursor: default;
}

@keyframes image-skeleton {
  0% {
    background-position: 100% 50%;
  }
  100% {
    background-position: 0 50%;
  }
}

.image-action {
  position: absolute;
  top: 0;
  right: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  color: #fff;
  background: rgba(0, 0, 0, 0.55);
  border-bottom-left-radius: 6px;
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.2s;
}

.image-box:hover .image-action {
  opacity: 1;
}

.image-upload-el {
  display: block;
  width: 100%;
  height: 100%;
}

.image-upload-el :deep(.el-upload) {
  display: block;
  width: 100%;
  height: 100%;
}

.upload-box {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: #a8abb2;
  background: #fafafa;
  border: 1px dashed #c0c4cc;
  border-radius: 6px;
  cursor: pointer;
  transition: border-color 0.2s, color 0.2s;
}

.upload-box:hover {
  color: var(--el-color-primary);
  border-color: var(--el-color-primary);
}

.upload-icon {
  font-size: 26px;
}

.paste-input {
  width: 256px;
  max-width: 100%;
}

.paste-input :deep(.el-input__inner) {
  font-size: 12px;
  text-align: center;
  cursor: text;
}

/* ---------- 预览 ---------- */
.preview-body {
  min-height: 300px;
}

.preview-image {
  display: block;
  max-width: 100%;
  margin: 0 auto;
}

.preview-pdf {
  width: 100%;
  height: 70vh;
  border: none;
}
</style>
