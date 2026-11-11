<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { ElMessage } from 'element-plus';

import { reviewProposal } from '@/api/proposal';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  proposal: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'success']);

const submitting = ref(false);
const form = reactive({ status: 2, remark: '' });

const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

watch(
  () => props.modelValue,
  (value) => {
    if (value) {
      form.status = 2;
      form.remark = '';
    }
  },
);

async function submit() {
  if (!props.proposal) return;

  if (form.status === 3 && !form.remark.trim()) {
    ElMessage.warning('驳回时必须填写评审意见');
    return;
  }

  submitting.value = true;

  try {
    await reviewProposal(props.proposal.id, { status: form.status, remark: form.remark.trim() });
    ElMessage.success('评审完成');
    visible.value = false;
    emit('success');
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <el-dialog v-model="visible" title="方案评审" width="560px">
    <el-descriptions v-if="proposal" :column="2" size="small" border style="margin-bottom: 16px">
      <el-descriptions-item label="方案编号">{{ proposal.proposal_no }}</el-descriptions-item>
      <el-descriptions-item label="版本">V{{ proposal.version }}</el-descriptions-item>
      <el-descriptions-item label="零件号">{{ proposal.part_no }}</el-descriptions-item>
      <el-descriptions-item label="零件名称">{{ proposal.part_name }}</el-descriptions-item>
    </el-descriptions>

    <el-form label-width="90px">
      <el-form-item label="评审结果">
        <el-radio-group v-model="form.status">
          <el-radio :value="2">通过</el-radio>
          <el-radio :value="3">驳回</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="评审意见">
        <el-input
          v-model="form.remark"
          type="textarea"
          :rows="5"
          maxlength="500"
          show-word-limit
          :placeholder="form.status === 3 ? '驳回时必须填写评审意见' : '可选填'"
        />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="submit">提交评审</el-button>
    </template>
  </el-dialog>
</template>
