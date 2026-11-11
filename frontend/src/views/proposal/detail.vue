<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';

import {
  changeProposal,
  deleteProposal,
  exportProposalExcel,
  getProposal,
  submitProposal,
  withdrawProposal,
} from '@/api/proposal';
import { downloadAttachment, fetchAttachmentBlob, saveBlob } from '@/api/attachment';
import ReviewDialog from './ReviewDialog.vue';
import { formatDateTime, numberText } from '@/utils/format';
import {
  IMAGE_SLOTS,
  PACK_MATERIAL_TEXT,
  STATUS_TAG,
  STATUS_TEXT,
} from '@/utils/constants';

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const data = ref(null);
const imageUrls = ref({});
const reviewVisible = ref(false);

const permissions = computed(() => data.value?.permissions || {});
const otherAttachments = computed(
  () => (data.value?.attachments || []).filter((item) => item.attach_type === 'attachment'),
);

const yesNo = (value) => {
  if (value === 1) return '是';
  if (value === 0) return '否';
  return '-';
};

async function load() {
  loading.value = true;

  try {
    data.value = await getProposal(route.params.id);
    await loadImages();
  } finally {
    loading.value = false;
  }
}

async function loadImages() {
  Object.values(imageUrls.value).forEach((url) => URL.revokeObjectURL(url));
  imageUrls.value = {};

  for (const slot of IMAGE_SLOTS) {
    const attachment = (data.value?.attachments || []).find((item) => item.attach_type === slot.type);
    if (!attachment) continue;

    try {
      imageUrls.value[slot.type] = await fetchAttachmentBlob(attachment.id, 'preview');
    } catch (e) {
      imageUrls.value[slot.type] = '';
    }
  }
}

onBeforeUnmount(() => {
  Object.values(imageUrls.value).forEach((url) => URL.revokeObjectURL(url));
});

onMounted(load);

async function confirmRun(message, action, successMessage) {
  try {
    await ElMessageBox.confirm(message, '操作确认', { type: 'warning' });
  } catch (e) {
    return;
  }

  await action();
  ElMessage.success(successMessage);
  load();
}

const submit = () => confirmRun('确认提交该方案进入评审？', () => submitProposal(data.value.id), '已提交评审');
const withdraw = () => confirmRun('确认撤回该方案？撤回后回到草稿状态。', () => withdrawProposal(data.value.id), '已撤回');
const change = () => confirmRun('确认发起变更？将生成版本 +1 的变更草稿。', () => changeProposal(data.value.id), '已发起变更');
const remove = () =>
  confirmRun('删除后不可恢复（附件与日志保留），确认删除？', async () => {
    await deleteProposal(data.value.id);
    router.replace('/proposals');
  }, '删除成功');

async function exportExcel() {
  const response = await exportProposalExcel(data.value.id);
  const name = `包装确认单-${data.value.supplier_name || ''}-${data.value.part_no || ''}-${data.value.part_name || ''}.xlsx`;
  await saveBlob(response, name.replace(/[\\/:*?"<>|]/g, ''));
}
</script>

<template>
  <div v-loading="loading">
    <template v-if="data">
      <div class="page-card">
        <div class="page-toolbar">
          <el-button @click="router.push('/proposals')" text>返回列表</el-button>
          <h2 style="margin: 0; font-size: 18px">{{ data.proposal_no }}</h2>
          <el-tag :type="STATUS_TAG[data.status]">{{ data.status_label || STATUS_TEXT[data.status] }}</el-tag>
          <el-tag type="info" effect="plain">V{{ data.version }}</el-tag>
          <div class="spacer"></div>

          <el-button v-if="permissions.edit" type="primary" @click="router.push(`/proposals/${data.id}/edit`)">
            编辑
          </el-button>
          <el-button v-if="permissions.submit" type="success" @click="submit">提交评审</el-button>
          <el-button v-if="permissions.withdraw" @click="withdraw">撤回</el-button>
          <el-button v-if="permissions.review" type="warning" @click="reviewVisible = true">评审</el-button>
          <el-button v-if="permissions.change" type="primary" plain @click="change">发起变更</el-button>
          <el-button v-if="permissions.delete" type="danger" plain @click="remove">删除</el-button>
          <el-button v-if="permissions.export" @click="exportExcel">导出确认单</el-button>
        </div>

        <el-alert v-if="data.status === 5" type="error" :closable="false" show-icon style="margin-bottom: 12px"
          title="该方案已因变更作废，仅供查看与导出。" />

        <el-alert v-if="data.source" type="info" :closable="false" show-icon style="margin-bottom: 12px">
          <template #title>
            变更来源：
            <el-link type="primary" @click="router.push(`/proposals/${data.source.id}`)">
              {{ data.source.proposal_no }}（V{{ data.source.version }}）
            </el-link>
          </template>
        </el-alert>

        <el-alert v-if="data.active_change" type="warning" :closable="false" show-icon style="margin-bottom: 12px">
          <template #title>
            变更中：
            <el-link type="primary" @click="router.push(`/proposals/${data.active_change.id}`)">
              {{ data.active_change.proposal_no }}（V{{ data.active_change.version }}，{{ data.active_change.status_label }}）
            </el-link>
          </template>
        </el-alert>

        <el-alert v-if="data.status === 3 && data.review_remark" type="error" :closable="false" show-icon
          style="margin-bottom: 12px" :title="`驳回意见：${data.review_remark}`" />
      </div>

      <div class="page-card">
        <h3 class="section-title">基本信息</h3>
        <el-descriptions :column="3" border size="small">
          <el-descriptions-item label="填写日期">{{ data.write_date || '-' }}</el-descriptions-item>
          <el-descriptions-item label="客户">{{ data.customer_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="项目车型">{{ data.project_model || '-' }}</el-descriptions-item>
          <el-descriptions-item label="零件号">{{ data.part_no || '-' }}</el-descriptions-item>
          <el-descriptions-item label="零件名称">{{ data.part_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="零件材料">{{ data.part_material || '-' }}</el-descriptions-item>
          <el-descriptions-item label="供应商">{{ data.supplier_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="供应商代码">{{ data.supplier_code || '-' }}</el-descriptions-item>
          <el-descriptions-item label="创建人">{{ data.creator_name || '-' }}</el-descriptions-item>
        </el-descriptions>
      </div>

      <div class="page-card">
        <h3 class="section-title">客户资料</h3>
        <el-descriptions :column="3" border size="small">
          <el-descriptions-item label="客户地址">{{ data.customer_address || '-' }}</el-descriptions-item>
          <el-descriptions-item label="联系人">{{ data.customer_contact || '-' }}</el-descriptions-item>
          <el-descriptions-item label="电话">{{ data.customer_phone || '-' }}</el-descriptions-item>
          <el-descriptions-item label="邮箱">{{ data.customer_email || '-' }}</el-descriptions-item>
          <el-descriptions-item label="传真">{{ data.customer_fax || '-' }}</el-descriptions-item>
        </el-descriptions>
      </div>

      <div class="page-card">
        <h3 class="section-title">供应商资料</h3>
        <el-descriptions :column="3" border size="small">
          <el-descriptions-item label="供应商地址">{{ data.supplier_address || '-' }}</el-descriptions-item>
          <el-descriptions-item label="联系人">{{ data.contact || '-' }}</el-descriptions-item>
          <el-descriptions-item label="电话">{{ data.phone || '-' }}</el-descriptions-item>
          <el-descriptions-item label="邮箱">{{ data.supplier_email || '-' }}</el-descriptions-item>
          <el-descriptions-item label="传真">{{ data.supplier_fax || '-' }}</el-descriptions-item>
        </el-descriptions>
      </div>

      <div class="page-card">
        <h3 class="section-title">包装信息</h3>
        <el-descriptions :column="3" border size="small">
          <el-descriptions-item label="规格尺寸">{{ data.spec || '-' }}</el-descriptions-item>
          <el-descriptions-item label="数量">{{ data.quantity }}</el-descriptions-item>
          <el-descriptions-item label="产品净重(kg)">{{ numberText(data.part_weight) }}</el-descriptions-item>
          <el-descriptions-item label="产品外形尺寸">
            {{ numberText(data.part_size_l) }} * {{ numberText(data.part_size_w) }} * {{ numberText(data.part_size_h) }} mm
          </el-descriptions-item>
          <el-descriptions-item label="包装箱编号">{{ data.pack_box_no || '-' }}</el-descriptions-item>
          <el-descriptions-item label="包装箱材料">{{ PACK_MATERIAL_TEXT[data.pack_material] || '-' }}</el-descriptions-item>
          <el-descriptions-item label="包装箱外尺寸">
            {{ numberText(data.box_size_l) }} * {{ numberText(data.box_size_w) }} * {{ numberText(data.box_size_h) }} mm
          </el-descriptions-item>
          <el-descriptions-item label="毛重(kg)">{{ numberText(data.gross_weight) }}</el-descriptions-item>
          <el-descriptions-item label="存放数量">{{ data.packing_qty }}</el-descriptions-item>
          <el-descriptions-item label="包装箱是否返回">{{ yesNo(data.returnable) }}</el-descriptions-item>
          <el-descriptions-item label="内衬是否返回">{{ yesNo(data.dunnage_returnable) }}</el-descriptions-item>
          <el-descriptions-item label="是否带托盘交付">{{ yesNo(data.with_pallet) }}</el-descriptions-item>
        </el-descriptions>

        <el-descriptions v-if="data.with_pallet === 1" :column="3" border size="small" style="margin-top: 12px">
          <el-descriptions-item label="满托盘外尺寸">
            {{ numberText(data.pallet_size_l) }} * {{ numberText(data.pallet_size_w) }} * {{ numberText(data.pallet_size_h) }} mm
          </el-descriptions-item>
          <el-descriptions-item label="每层包装箱数量">{{ data.pallet_layer_qty }}</el-descriptions-item>
          <el-descriptions-item label="堆码层数">{{ data.pallet_layer_count }}</el-descriptions-item>
          <el-descriptions-item label="每托盘零件数量">{{ data.pallet_parts_qty }}</el-descriptions-item>
        </el-descriptions>

        <div style="margin-top: 12px">
          <div class="muted" style="font-size: 13px; margin-bottom: 6px">包装操作说明</div>
          <div style="white-space: pre-wrap; line-height: 1.7">{{ data.packaging_instruction || '（未填写）' }}</div>
        </div>
      </div>

      <div class="page-card">
        <h3 class="section-title">包装图片</h3>
        <div class="image-grid">
          <div v-for="slot in IMAGE_SLOTS" :key="slot.type" class="image-slot">
            <div class="slot-title">
              <span>{{ slot.label }}</span>
              <el-tag v-if="imageUrls[slot.type]" size="small" type="success" effect="plain">已上传</el-tag>
              <el-tag v-else size="small" type="info" effect="plain">未上传</el-tag>
            </div>
            <div class="preview">
              <el-image v-if="imageUrls[slot.type]" :src="imageUrls[slot.type]"
                :preview-src-list="[imageUrls[slot.type]]" preview-teleported fit="contain"
                style="width: 100%; height: 100%" />
              <span v-else class="muted" style="font-size: 12px">暂无图片</span>
            </div>
          </div>
        </div>

        <div v-if="otherAttachments.length" style="margin-top: 14px">
          <div class="muted" style="font-size: 13px; margin-bottom: 6px">其他附件</div>
          <div v-for="item in otherAttachments" :key="item.id" style="margin-bottom: 6px">
            <el-link type="primary" @click="downloadAttachment(item.id, item.file_name)">
              {{ item.file_name }}
            </el-link>
            <span class="muted" style="margin-left: 8px; font-size: 12px">
              {{ formatDateTime(item.created_at) }}
            </span>
          </div>
        </div>
      </div>

      <div class="page-card">
        <h3 class="section-title">流程记录</h3>

        <el-timeline>
          <el-timeline-item v-for="item in data.history" :key="`${item.action}-${item.created_at}`"
            :timestamp="formatDateTime(item.created_at)" placement="top">
            <div style="font-weight: 600">{{ item.action_name }} · V{{ item.version }}</div>
            <div class="muted" style="font-size: 13px">
              {{ item.user_name }} · {{ item.content || '—' }}
            </div>
          </el-timeline-item>
        </el-timeline>

        <el-table :data="data.logs" size="small" style="margin-top: 10px">
          <el-table-column label="时间" width="170">
            <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
          </el-table-column>
          <el-table-column prop="user_name" label="操作人" width="110" />
          <el-table-column prop="action_name" label="动作" width="130" />
          <el-table-column label="版本" width="70">
            <template #default="{ row }">V{{ row.version }}</template>
          </el-table-column>
          <el-table-column prop="content" label="内容" min-width="220" show-overflow-tooltip />
          <template #empty>
            <div class="empty">暂无记录</div>
          </template>
        </el-table>
      </div>

      <ReviewDialog v-model="reviewVisible" :proposal="data" @success="load" />
    </template>
  </div>
</template>
