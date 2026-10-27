<template>
  <div v-loading="loading">
    <div class="page-card">
      <div class="detail-header">
        <div class="detail-title">
          <h2>
            {{ detail.part_name || detail.proposal_no || '-' }}
            <StatusTag :status="detail.status" />
          </h2>
          <p class="text-muted">
            {{ detail.proposal_no }} · 版本 V{{ detail.version }} · 供应商
            {{ detail.supplier || '-' }}
          </p>
        </div>

        <div class="detail-actions">
          <el-button
            type="success"
            plain
            :loading="exporting === 'excel'"
            @click="handleExport('excel')"
          >
            导出 Excel
          </el-button>
          <el-button v-if="permissions.can_edit" type="primary" @click="goEdit">编辑</el-button>
          <el-button v-if="permissions.can_submit" type="success" @click="handleSubmit">
            提交评审
          </el-button>
          <el-button v-if="permissions.can_withdraw" type="warning" @click="handleWithdraw">
            撤回提交
          </el-button>
          <el-button v-if="permissions.can_review" type="warning" @click="reviewVisible = true">
            评审
          </el-button>
          <el-button v-if="permissions.can_archive" type="primary" plain @click="handleArchive">
            归档
          </el-button>
          <el-button v-if="permissions.can_change" type="warning" plain @click="handleChange">
            发起变更
          </el-button>
          <el-button @click="router.back()">返回</el-button>
        </div>
      </div>

      <el-alert
        v-if="Number(detail.status) === STATUS.REJECTED"
        class="mb-16"
        type="error"
        :closable="false"
        show-icon
        :title="`驳回意见：${detail.review_remark || '无'}`"
      />

      <el-alert
        v-if="Number(detail.status) === STATUS.ARCHIVED_OBSOLETE"
        class="mb-16"
        type="info"
        :closable="false"
        show-icon
        title="该方案已被变更替换并作废，仅作为历史记录保留"
      />

      <el-alert
        v-else-if="detail.change_target"
        class="mb-16"
        type="warning"
        :closable="false"
        show-icon
        title="该方案正在变更中"
      >
        变更方案：
        <el-link
          type="primary"
          :underline="false"
          @click="router.push(`/proposal/detail/${detail.change_target.id}`)"
        >
          {{ detail.change_target.proposal_no }}（V{{ detail.change_target.version }}）
        </el-link>
      </el-alert>

      <h4 class="section-title">流程信息</h4>
      <el-descriptions :column="3" border>
        <el-descriptions-item label="填写日期">{{ detail.write_date || '-' }}</el-descriptions-item>
        <el-descriptions-item label="创建时间">{{ detail.create_time || '-' }}</el-descriptions-item>
        <el-descriptions-item label="提交时间">{{ detail.submit_time || '-' }}</el-descriptions-item>
        <el-descriptions-item label="评审人">
          {{ detail.reviewer?.real_name || '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="评审时间">{{ detail.review_time || '-' }}</el-descriptions-item>
        <el-descriptions-item v-if="detail.change_source" label="变更来源">
          <el-link
            type="primary"
            :underline="false"
            @click="router.push(`/proposal/detail/${detail.change_source.id}`)"
          >
            {{ detail.change_source.proposal_no }}（V{{ detail.change_source.version }}）
          </el-link>
        </el-descriptions-item>
      </el-descriptions>

      <h4 class="section-title">客户资料</h4>
      <el-descriptions :column="3" border>
        <el-descriptions-item label="客户名称" :span="3">
          {{ customerLabel(detail.customer) }}
        </el-descriptions-item>
        <el-descriptions-item label="客户地址" :span="3">
          {{ detail.customer_address || '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="联系人">{{ detail.customer_contact || '-' }}</el-descriptions-item>
        <el-descriptions-item label="电话">{{ detail.customer_phone || '-' }}</el-descriptions-item>
        <el-descriptions-item label="传真">{{ detail.customer_fax || '-' }}</el-descriptions-item>
        <el-descriptions-item label="电子邮箱" :span="3">{{ detail.customer_email || '-' }}</el-descriptions-item>
      </el-descriptions>

      <h4 class="section-title">供应商资料</h4>
      <el-descriptions :column="3" border>
        <el-descriptions-item label="供应商名称">{{ detail.supplier || '-' }}</el-descriptions-item>
        <el-descriptions-item label="供应商代码">{{ detail.supplier_code || '-' }}</el-descriptions-item>
        <el-descriptions-item label="联系人">{{ detail.contact || '-' }}</el-descriptions-item>
        <el-descriptions-item label="供应商地址" :span="3">
          {{ detail.supplier_address || '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="电话">{{ detail.phone || '-' }}</el-descriptions-item>
        <el-descriptions-item label="传真">{{ detail.supplier_fax || '-' }}</el-descriptions-item>
        <el-descriptions-item label="电子邮箱">{{ detail.supplier_email || '-' }}</el-descriptions-item>
      </el-descriptions>

      <h4 class="section-title">产品信息</h4>
      <el-descriptions :column="3" border>
        <el-descriptions-item label="项目车型">{{ detail.project_model || '-' }}</el-descriptions-item>
        <el-descriptions-item label="零件名称">{{ detail.part_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="零件号">{{ detail.part_no || '-' }}</el-descriptions-item>
        <el-descriptions-item label="材料">{{ detail.part_material || '-' }}</el-descriptions-item>
        <el-descriptions-item label="产品净重(kg)">{{ detail.part_weight || 0 }}</el-descriptions-item>
        <el-descriptions-item label="产品外形尺寸(mm)">
          {{ sizeText(detail.part_size_l, detail.part_size_w, detail.part_size_h) }}
        </el-descriptions-item>
      </el-descriptions>

      <h4 class="section-title">包装信息</h4>
      <el-descriptions :column="3" border>
        <el-descriptions-item label="包装箱编号">{{ detail.pack_box_no || '-' }}</el-descriptions-item>
        <el-descriptions-item label="包装箱材料">{{ packMaterialLabel(detail.pack_material) }}</el-descriptions-item>
        <el-descriptions-item label="存放数量(件/箱)">{{ detail.packing_qty || 0 }}</el-descriptions-item>
        <el-descriptions-item label="包装箱外尺寸(mm)">
          {{ sizeText(detail.box_size_l, detail.box_size_w, detail.box_size_h) }}
        </el-descriptions-item>
        <el-descriptions-item label="毛重(kg)">{{ detail.gross_weight || 0 }}</el-descriptions-item>
        <el-descriptions-item label="包装箱是否返回">{{ yesNo(detail.returnable) }}</el-descriptions-item>
        <el-descriptions-item label="内衬是否返回">{{ yesNo(detail.dunnage_returnable) }}</el-descriptions-item>
      </el-descriptions>

      <h4 class="section-title">托盘信息</h4>
      <el-descriptions v-if="Number(detail.with_pallet) === 1" :column="3" border>
        <el-descriptions-item label="是否带托盘交付">{{ yesNo(detail.with_pallet) }}</el-descriptions-item>
        <el-descriptions-item label="满托盘外尺寸(mm)">
          {{ sizeText(detail.pallet_size_l, detail.pallet_size_w, detail.pallet_size_h) }}
        </el-descriptions-item>
        <el-descriptions-item label="每层包装箱数量">{{ detail.pallet_layer_qty || 0 }}</el-descriptions-item>
        <el-descriptions-item label="堆码层数">{{ detail.pallet_layer_count || 0 }}</el-descriptions-item>
        <el-descriptions-item label="每托盘零件数量">{{ detail.pallet_parts_qty || 0 }}</el-descriptions-item>
      </el-descriptions>
      <el-descriptions v-else :column="1" border>
        <el-descriptions-item label="是否带托盘交付">{{ unselectedText(detail.with_pallet, '否（不带托盘交付）') }}</el-descriptions-item>
      </el-descriptions>

      <h4 class="section-title">包装操作说明</h4>
      <p class="description-text">{{ detail.packaging_instruction || '暂无说明' }}</p>
    </div>

    <div class="page-card mt-16">
      <h4 class="section-title">包装示意图</h4>
      <el-row :gutter="16">
        <el-col v-for="group in imageGroups" :key="group.type" :xs="24" :md="12">
          <div class="image-block">
            <p class="image-block-title">{{ group.label }}（{{ group.list.length }}）</p>
            <FileUpload :model-value="group.list" :attach-type="group.type" disabled />
          </div>
        </el-col>
      </el-row>
    </div>

    <el-row :gutter="16" class="mt-16">
      <el-col :xs="24" :md="12">
        <div class="page-card">
          <h4 class="section-title">版本记录</h4>
          <el-timeline v-if="versions.length">
            <el-timeline-item
              v-for="(item, index) in versions"
              :key="index"
              :timestamp="item.create_time"
              placement="top"
              :type="timelineType(item.action)"
            >
              <p class="timeline-title">V{{ item.version }} · {{ item.action_name }}</p>
              <p class="text-muted">{{ item.operator }}：{{ item.content || '-' }}</p>
            </el-timeline-item>
          </el-timeline>
          <el-empty v-else description="暂无版本记录" :image-size="60" />
        </div>
      </el-col>

      <el-col :xs="24" :md="12">
        <div class="page-card">
          <h4 class="section-title">评审日志</h4>
          <el-timeline v-if="logs.length">
            <el-timeline-item
              v-for="item in logs"
              :key="item.id"
              :timestamp="item.create_time"
              placement="top"
            >
              <p class="timeline-title">
                {{ item.action_name }}
                <span class="text-muted">· {{ item.operator?.real_name || '-' }}</span>
              </p>
              <p class="text-muted">{{ item.content || '-' }}</p>
            </el-timeline-item>
          </el-timeline>
          <el-empty v-else description="暂无日志" :image-size="60" />
        </div>
      </el-col>
    </el-row>

    <ReviewDialog v-model="reviewVisible" :proposal="detail" @success="loadDetail" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import StatusTag from '@/components/StatusTag.vue';
import FileUpload from '@/components/FileUpload.vue';
import ReviewDialog from './ReviewDialog.vue';
import {
  changeProposal,
  exportProposal,
  getProposalDetail,
  submitProposal,
  withdrawProposal,
} from '@/api/proposal';
import { archiveProposals } from '@/api/admin';
import { resolveFileName } from '@/api/attachment';
import { getDictOptions } from '@/api/dict';
import {
  ATTACH_TYPE,
  ATTACH_TYPE_LABEL,
  DICT_TYPE,
  IMAGE_ATTACH_TYPES,
  PACK_MATERIAL_OPTIONS,
  STATUS,
} from '@/utils/constants';

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const exporting = ref('');
const detail = ref({});
const attachments = ref([]);
const permissions = ref({});
const logs = ref([]);
const versions = ref([]);
const reviewVisible = ref(false);
const customers = ref([]);

const customerLabel = (value) =>
  customers.value.find((item) => item.value === value)?.label || value || '-';


const packMaterialLabel = (value) =>
  PACK_MATERIAL_OPTIONS.find((item) => item.value === value)?.label || value || '-';

/**
 * 尺寸文字
 */
function sizeText(length, width, height) {
  const parts = [length, width, height].map((item) => Number(item || 0));

  return parts.every((item) => item <= 0) ? '-' : parts.join(' × ');
}

/**
 * 版本记录节点颜色
 * @param {string} action
 * @returns {string}
 */
function timelineType(action) {
  if (action === 'review_reject' || action === 'obsolete') return 'danger';
  if (action === 'review_pass' || action === 'archive') return 'success';

  return 'primary';
}

/**
 * 是 / 否
 */
function yesNo(value) {
  if (value === null || value === undefined || value === '') return '-';

  return Number(value) === 1 ? '是' : '否';
}

/**
 * 未选择的是/否字段展示
 * @param {*} value
 * @param {string} fallback 已选择“否”时的文案
 * @returns {string}
 */
function unselectedText(value, fallback) {
  if (value === null || value === undefined || value === '') return '未选择';

  return fallback;
}

/** 按类型分组的图片 */
const imageGroups = computed(() =>
  IMAGE_ATTACH_TYPES.map((type) => ({
    type,
    label: ATTACH_TYPE_LABEL[type],
    list: attachments.value.filter(
      (item) => (item.attach_type || ATTACH_TYPE.ATTACHMENT) === type,
    ),
  })),
);

async function loadDict() {
  const data = await getDictOptions({ type: DICT_TYPE.CUSTOMER });
  customers.value = data[DICT_TYPE.CUSTOMER] || [];
}

/**
 * 加载方案详情
 */
async function loadDetail() {
  loading.value = true;

  try {
    const data = await getProposalDetail(route.params.id);
    detail.value = data;
    attachments.value = data.attachments || [];
    permissions.value = data.permissions || {};
    logs.value = data.logs || [];
    versions.value = data.versions || [];
  } finally {
    loading.value = false;
  }
}

/**
 * 导出包装确认单
 * @param {'excel'|'pdf'} format
 */
async function handleExport(format) {
  if (exporting.value) return;

  exporting.value = format;

  try {
    const response = await exportProposal(detail.value.id, format);
    const fallback = `包装确认单_${detail.value.proposal_no}.${format === 'pdf' ? 'pdf' : 'xlsx'}`;
    const fileName = resolveFileName(response, fallback);
    const url = URL.createObjectURL(
      new Blob([response.data], { type: response.headers?.['content-type'] }),
    );
    const link = document.createElement('a');

    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    ElMessage.success('导出成功');
  } finally {
    exporting.value = '';
  }
}

function goEdit() {
  router.push(`/proposal/edit/${detail.value.id}`);
}

/**
 * 提交评审
 */
async function handleSubmit() {
  await ElMessageBox.confirm('确定提交该方案进入评审吗？提交后不可编辑。', '提交评审', {
    type: 'warning',
  });
  await submitProposal(detail.value.id);
  ElMessage.success('提交成功，等待评审');
  loadDetail();
}

/**
 * 撤回提交（待评审 -> 草稿）
 */
async function handleWithdraw() {
  await ElMessageBox.confirm(
    '撤回后方案将退回草稿状态，可继续编辑并重新提交，确定撤回吗？',
    '撤回提交',
    { type: 'warning' },
  );
  await withdrawProposal(detail.value.id);
  ElMessage.success('已撤回提交，方案已退回草稿');
  loadDetail();
}

/**
 * 归档方案（管理员）
 */
async function handleArchive() {
  await ElMessageBox.confirm('确定将该方案归档吗？归档后将不可再修改。', '方案归档', {
    type: 'warning',
  });
  await archiveProposals([detail.value.id]);
  ElMessage.success('归档成功');
  loadDetail();
}

/**
 * 发起变更（仅已归档方案，生成版本 +1 的变更草稿）
 */
async function handleChange() {
  await ElMessageBox.confirm(
    '发起变更后将生成一份新版本方案草稿，原归档方案继续有效，待新版本归档后原方案自动作废。确定发起变更吗？',
    '发起变更',
    { type: 'warning' },
  );
  const created = await changeProposal(detail.value.id);
  ElMessage.success('变更方案已创建，请确认内容后提交评审');
  router.push(`/proposal/edit/${created.id}`);
}

onMounted(async () => {
  await loadDict();
  await loadDetail();
});
</script>

<style scoped>
.detail-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.detail-title h2 {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 0 6px;
  font-size: 20px;
}

.detail-title p {
  margin: 0;
  font-size: 13px;
}

.detail-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.section-title {
  margin: 20px 0 10px;
  font-size: 15px;
}

.description-text {
  margin: 0;
  line-height: 1.8;
  color: #606266;
  white-space: pre-wrap;
}

.image-block {
  margin-bottom: 12px;
}

.image-block-title {
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 600;
  color: #606266;
}

.timeline-title {
  margin: 0 0 4px;
  font-weight: 600;
}

.timeline-title + p {
  margin: 0;
  font-size: 13px;
}
</style>
