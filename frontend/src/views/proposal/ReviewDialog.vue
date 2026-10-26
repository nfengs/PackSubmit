<template>
  <el-dialog
    :model-value="modelValue"
    title="方案评审"
    width="820px"
    top="5vh"
    class="review-dialog"
    destroy-on-close
    @update:model-value="handleVisibleChange"
    @closed="resetForm"
  >
    <div v-loading="loading">
      <div class="review-target">
        <p class="review-title">{{ target.title || '-' }}</p>
        <p class="text-muted">
          {{ target.proposal_no || '-' }} · V{{ target.version || 1 }}
          <span v-if="target.submit_time"> · 提交于 {{ target.submit_time }}</span>
        </p>
      </div>

      <el-descriptions :column="2" size="small" border class="review-info">
        <el-descriptions-item label="零件名称">{{ target.part_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="零件号">{{ target.part_no || '-' }}</el-descriptions-item>
        <el-descriptions-item label="项目车型">{{ target.project_model || '-' }}</el-descriptions-item>
        <el-descriptions-item label="供应商">{{ target.supplier || '-' }}</el-descriptions-item>
        <el-descriptions-item label="包装箱编号">{{ target.pack_box_no || '-' }}</el-descriptions-item>
        <el-descriptions-item label="存放数量">
          {{ target.packing_qty || 0 }} 件/箱
        </el-descriptions-item>
        <el-descriptions-item label="包装箱外尺寸">
          {{ sizeText(target.box_size_l, target.box_size_w, target.box_size_h) }}
        </el-descriptions-item>
        <el-descriptions-item label="毛重">{{ target.gross_weight || 0 }} KG</el-descriptions-item>
      </el-descriptions>

      <div class="image-groups">
        <div v-for="group in imageGroups" :key="group.type" class="image-group">
          <p class="group-title">{{ group.label }}（{{ group.list.length }}）</p>
          <FileUpload :model-value="group.list" :attach-type="group.type" :size="110" disabled />
        </div>
      </div>

      <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
        <el-form-item label="评审结果" prop="status">
          <el-radio-group v-model="form.status">
            <el-radio-button :value="STATUS.APPROVED">通过</el-radio-button>
            <el-radio-button :value="STATUS.REJECTED">驳回</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="评审意见" prop="remark">
          <el-input
            v-model="form.remark"
            type="textarea"
            :rows="4"
            maxlength="500"
            show-word-limit
            :placeholder="form.status === STATUS.REJECTED ? '驳回时必填，请说明驳回原因' : '选填'"
          />
        </el-form-item>
      </el-form>
    </div>

    <template #footer>
      <el-button @click="handleVisibleChange(false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSubmit">提交评审</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { ElMessage } from 'element-plus';
import FileUpload from '@/components/FileUpload.vue';
import { getProposalDetail, reviewProposal } from '@/api/proposal';
import {
  ATTACH_TYPE,
  ATTACH_TYPE_LABEL,
  IMAGE_ATTACH_TYPES,
  STATUS,
} from '@/utils/constants';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  proposal: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['update:modelValue', 'success']);

const formRef = ref(null);
const submitting = ref(false);
const loading = ref(false);
/** 打开弹窗时拉取的完整方案数据（列表行不含附件与确认单字段） */
const detail = ref(null);

const target = computed(() => detail.value || props.proposal || {});

const attachments = computed(() => target.value.attachments || []);

const imageGroups = computed(() =>
  IMAGE_ATTACH_TYPES.map((type) => ({
    type,
    label: ATTACH_TYPE_LABEL[type],
    list: attachments.value.filter(
      (item) => (item.attach_type || ATTACH_TYPE.ATTACHMENT) === type,
    ),
  })),
);

const form = reactive({
  status: STATUS.APPROVED,
  remark: '',
});

const rules = computed(() => ({
  status: [{ required: true, message: '请选择评审结果', trigger: 'change' }],
  remark: [
    {
      required: Number(form.status) === STATUS.REJECTED,
      message: '驳回时必须填写评审意见',
      trigger: 'blur',
    },
    { max: 500, message: '评审意见不能超过 500 字', trigger: 'blur' },
  ],
}));

/**
 * 尺寸文字
 */
function sizeText(length, width, height) {
  const parts = [length, width, height].map((item) => Number(item || 0));

  return parts.every((item) => item <= 0) ? '-' : parts.join(' × ');
}

/**
 * 加载方案详情（含包装图片）
 */
async function loadDetail() {
  if (!props.proposal?.id) return;

  loading.value = true;

  try {
    detail.value = await getProposalDetail(props.proposal.id);
  } catch (error) {
    detail.value = null;
  } finally {
    loading.value = false;
  }
}

watch(
  () => props.modelValue,
  (visible) => {
    if (visible) {
      detail.value = null;
      loadDetail();
    }
  },
);

/**
 * 控制弹窗显隐
 * @param {boolean} visible
 */
function handleVisibleChange(visible) {
  emit('update:modelValue', visible);
}

/**
 * 提交评审
 */
async function handleSubmit() {
  const valid = await formRef.value.validate().catch(() => false);
  if (!valid) return;

  if (!props.proposal?.id) {
    ElMessage.error('方案信息缺失');
    return;
  }

  submitting.value = true;

  try {
    await reviewProposal(props.proposal.id, {
      status: Number(form.status),
      remark: form.remark,
    });
    ElMessage.success(Number(form.status) === STATUS.APPROVED ? '评审通过' : '已驳回');
    emit('update:modelValue', false);
    emit('success');
  } finally {
    submitting.value = false;
  }
}

/**
 * 关闭后重置
 */
function resetForm() {
  form.status = STATUS.APPROVED;
  form.remark = '';
  detail.value = null;
}
</script>

<style scoped>
.review-target {
  padding: 10px 14px;
  margin-bottom: 12px;
  background: #f5f7fa;
  border-radius: 6px;
}

.review-title {
  margin: 0 0 4px;
  font-weight: 600;
}

.review-target p:last-child {
  margin: 0;
  font-size: 12px;
}

.review-info {
  margin-bottom: 14px;
}

.image-groups {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 16px;
}

.image-group {
  width: calc(50% - 6px);
}

.group-title {
  margin: 0 0 6px;
  font-size: 12px;
  font-weight: 600;
  color: #606266;
}

.review-dialog :deep(.el-dialog__body) {
  max-height: 68vh;
  overflow-y: auto;
}
</style>
