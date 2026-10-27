<template>
  <div class="page-card">
    <!-- 筛选区 -->
    <div class="page-toolbar">
      <el-input
        v-model="query.keyword"
        placeholder="方案编号 / 零件名称 / 零件号"
        clearable
        style="width: 220px"
        @keyup.enter="handleSearch"
      />
      <el-select v-model="query.customer" placeholder="客户" clearable style="width: 150px">
        <el-option v-for="item in customers" :key="item.value" :label="item.label" :value="item.value" />
      </el-select>
      <el-select v-model="query.project" placeholder="项目" clearable filterable style="width: 170px">
        <el-option v-for="item in projects" :key="item" :label="item" :value="item" />
      </el-select>
      <el-select v-model="query.status" placeholder="状态" clearable style="width: 140px">
        <el-option v-for="item in statusOptions" :key="item.value" :label="item.label" :value="item.value" />
      </el-select>
      <el-button type="primary" @click="handleSearch">查询</el-button>
      <el-button @click="handleReset">重置</el-button>
      <div class="spacer" />
      <el-button v-if="canCreate" type="success" plain @click="openCopy">
        <el-icon><CopyDocument /></el-icon>
        发起变更
      </el-button>
    </div>

    <!-- 列表 -->
    <el-table v-loading="loading" :data="list" border stripe>
      <el-table-column prop="proposal_no" label="方案编号" width="170" />
      <el-table-column prop="part_name" label="零件名称" min-width="180" show-overflow-tooltip />
      <el-table-column prop="part_no" label="零件号" width="150" show-overflow-tooltip />
      <el-table-column label="客户" width="120">
        <template #default="{ row }">{{ customerLabel(row.customer) }}</template>
      </el-table-column>
      <el-table-column prop="version" label="版本" width="80" align="center">
        <template #default="{ row }">V{{ row.version }}</template>
      </el-table-column>
      <el-table-column label="状态" width="110" align="center">
        <template #default="{ row }">
          <StatusTag :status="row.status" />
        </template>
      </el-table-column>
      <el-table-column v-if="showOwner" label="供应商" width="110" show-overflow-tooltip>
        <template #default="{ row }">{{ row.supplier || '-' }}</template>
      </el-table-column>
      <el-table-column prop="update_time" label="更新时间" width="170" align="center" />
      <el-table-column label="操作" width="360" fixed="right" align="center">
        <template #default="{ row }">
          <el-button link type="primary" @click="router.push(`/proposal/detail/${row.id}`)">
            查看
          </el-button>
          <el-button v-if="canEdit(row)" link type="primary" @click="router.push(`/proposal/edit/${row.id}`)">
            编辑
          </el-button>
          <el-button v-if="canSubmit(row)" link type="success" @click="handleSubmit(row)">
            提交
          </el-button>
          <el-button v-if="canWithdraw(row)" link type="warning" @click="handleWithdraw(row)">
            撤回
          </el-button>
          <el-button v-if="canReview(row)" link type="warning" @click="openReview(row)">
            评审
          </el-button>
          <el-button v-if="canChange(row)" link type="warning" @click="handleChange(row)">
            发起变更
          </el-button>
          <el-button v-if="canDelete(row)" link type="danger" @click="handleDelete(row)">
            {{ Number(row.source_proposal_id) > 0 ? '取消变更并删除草稿' : '删除' }}
          </el-button>
        </template>
      </el-table-column>
      <template #empty>
        <span class="text-muted">暂无方案数据</span>
      </template>
    </el-table>

    <div class="pagination-wrap">
      <el-pagination
        v-model:current-page="query.page"
        v-model:page-size="query.page_size"
        :total="total"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next, jumper"
        background
        @size-change="handleSearch"
        @current-change="loadList"
      />
    </div>

    <el-dialog v-model="copyVisible" title="发起变更" width="560px">
      <el-alert
        type="info"
        :closable="false"
        show-icon
        title="仅已归档方案可发起变更：生成版本 +1 的新草稿，包装内容与图片一并复制，可编辑后提交评审"
        style="margin-bottom: 16px"
      />
      <el-select
        v-model="copySourceId"
        placeholder="输入方案编号 / 零件名称搜索已归档方案"
        filterable
        remote
        clearable
        :remote-method="searchCopySource"
        :loading="copyLoading"
        style="width: 100%"
      >
        <el-option
          v-for="item in copyOptions"
          :key="item.id"
          :label="`${item.proposal_no} · ${item.part_name || '未填写零件名称'}（V${item.version}）`"
          :value="item.id"
        />
      </el-select>

      <template #footer>
        <el-button @click="copyVisible = false">取消</el-button>
        <el-button type="primary" :loading="copying" :disabled="!copySourceId" @click="handleCopy">
          发起变更
        </el-button>
      </template>
    </el-dialog>

    <ReviewDialog v-model="reviewVisible" :proposal="reviewTarget" @success="loadList" />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { CopyDocument } from '@element-plus/icons-vue';
import StatusTag from '@/components/StatusTag.vue';
import ReviewDialog from './ReviewDialog.vue';
import {
  getProposalList,
  deleteProposal,
  submitProposal,
  withdrawProposal,
  changeProposal,
  getProjects,
} from '@/api/proposal';
import { getCustomers } from '@/api/masterData';
import { useUserStore } from '@/store/user';
import { STATUS, STATUS_OPTIONS } from '@/utils/constants';

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();

const statusOptions = STATUS_OPTIONS;
const loading = ref(false);
const list = ref([]);
const total = ref(0);
const customers = ref([]);
const projects = ref([]);

const reviewVisible = ref(false);
const reviewTarget = ref(null);

const copyVisible = ref(false);
const copyLoading = ref(false);
const copying = ref(false);
const copyOptions = ref([]);
const copySourceId = ref(null);

const query = reactive({
  keyword: '',
  customer: '',
  project: '',
  status: route.query.status !== undefined && route.query.status !== '' ? Number(route.query.status) : '',
  page: 1,
  page_size: 10,
});

const canCreate = computed(() => !userStore.isReviewer && !userStore.isViewer);
const showOwner = computed(() => !userStore.isSubmitter);

/**
 * 按钮权限：编辑（供应商/管理员，草稿或驳回）
 */
function canEdit(row) {
  return (
    !userStore.isReviewer &&
    (userStore.isAdmin || userStore.user?.id === row.user_id) &&
    [STATUS.DRAFT, STATUS.REJECTED].includes(Number(row.status))
  );
}

function canSubmit(row) {
  return canEdit(row);
}

/**
 * 按钮权限：撤回（供应商/管理员，待评审）
 */
function canWithdraw(row) {
  return (
    !userStore.isReviewer &&
    (userStore.isAdmin || userStore.user?.id === row.user_id) &&
    Number(row.status) === STATUS.PENDING
  );
}

function canReview(row) {
  return (
    (userStore.isReviewer || userStore.isAdmin) && Number(row.status) === STATUS.PENDING
  );
}

function canDelete(row) {
  return (
    (userStore.isAdmin || userStore.user?.id === row.user_id) &&
    Number(row.status) === STATUS.DRAFT &&
    // 导入生成的方案不允许删除，只有变更草稿可删除（终止变更）
    (userStore.isAdmin || Number(row.source_proposal_id) > 0)
  );
}

/**
 * 按钮权限：发起变更（供应商/管理员，已归档）
 */
function canChange(row) {
  return (
    !userStore.isReviewer &&
    (userStore.isAdmin || userStore.user?.id === row.user_id) &&
    Number(row.status) === STATUS.ARCHIVED
  );
}

const customerLabel = (value) =>
  customers.value.find((item) => item.value === value)?.label || value || '-';

async function loadDict() {
  const customerList = await getCustomers();
  customers.value = (customerList || []).map((item) => ({ label: item.name, value: item.name }));
}

async function loadProjects() {
  const data = await getProjects();
  projects.value = data || [];
}

async function loadList() {
  loading.value = true;

  try {
    const data = await getProposalList({ ...query });
    list.value = data.list || [];
    total.value = data.total || 0;
  } finally {
    loading.value = false;
  }
}

function handleSearch() {
  query.page = 1;
  loadList();
}

function handleReset() {
  query.keyword = '';
  query.customer = '';
  query.project = '';
  query.status = '';
  query.page = 1;
  loadList();
}

/**
 * 搜索可复制的源方案
 * @param {string} keyword
 */
async function searchCopySource(keyword = '') {
  copyLoading.value = true;

  try {
    const data = await getProposalList({
      keyword,
      status: STATUS.ARCHIVED,
      page: 1,
      page_size: 20,
    });
    copyOptions.value = data.list || [];
  } finally {
    copyLoading.value = false;
  }
}

function openCopy() {
  copySourceId.value = null;
  copyOptions.value = [];
  copyVisible.value = true;
  searchCopySource('');
}

/**
 * 对选中的已归档方案发起变更，并跳转到编辑页
 */
async function handleCopy() {
  if (!copySourceId.value) return;

  copying.value = true;

  try {
    const created = await changeProposal(copySourceId.value);
    copyVisible.value = false;
    ElMessage.success('变更方案已创建，请确认内容后提交评审');
    router.push(`/proposal/edit/${created.id}`);
  } finally {
    copying.value = false;
  }
}

/**
 * 行内发起变更
 * @param {object} row
 */
async function handleChange(row) {
  await ElMessageBox.confirm(
    `确定对方案「${row.part_name || row.proposal_no}」发起变更吗？将生成版本 V${Number(row.version) + 1} 的变更草稿。`,
    '发起变更',
    { type: 'warning' },
  );
  const created = await changeProposal(row.id);
  ElMessage.success('变更方案已创建，请确认内容后提交评审');
  router.push(`/proposal/edit/${created.id}`);
}

async function handleSubmit(row) {
  await ElMessageBox.confirm(
    `确定提交方案「${row.part_name || row.proposal_no}」进入评审吗？提交后不可再编辑。`,
    '提交评审',
    { type: 'warning' },
  );
  await submitProposal(row.id);
  ElMessage.success('提交成功，等待评审');
  loadList();
}

async function handleWithdraw(row) {
  await ElMessageBox.confirm(
    `确定撤回方案「${row.part_name || row.proposal_no}」的提交吗？撤回后将退回草稿，可继续编辑。`,
    '撤回提交',
    { type: 'warning' },
  );
  await withdrawProposal(row.id);
  ElMessage.success('已撤回提交，方案退回草稿');
  loadList();
}

async function handleDelete(row) {
  const isChange = Number(row.source_proposal_id) > 0;

  await ElMessageBox.confirm(
    isChange
      ? `确定取消本次变更并删除变更草稿「${row.part_name || row.proposal_no}」吗？删除后原归档方案继续有效。`
      : `确定删除方案「${row.part_name || row.proposal_no}」吗？删除后不可恢复。`,
    isChange ? '取消变更并删除草稿' : '删除方案',
    { type: 'warning' },
  );
  await deleteProposal(row.id);
  ElMessage.success(isChange ? '已取消变更并删除草稿' : '删除成功');
  loadList();
}

function openReview(row) {
  reviewTarget.value = row;
  reviewVisible.value = true;
}

onMounted(() => {
  loadDict();
  loadProjects();
  loadList();
});
</script>
