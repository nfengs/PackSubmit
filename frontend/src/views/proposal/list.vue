<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';

import {
  archiveProposals,
  changeProposal,
  deleteProposal,
  exportProposalExcel,
  getProjects,
  getProposals,
  submitProposal,
  withdrawProposal,
} from '@/api/proposal';
import { saveBlob } from '@/api/attachment';
import { getCustomers, getSuppliers } from '@/api/masterdata';
import ReviewDialog from './ReviewDialog.vue';
import { formatDateTime } from '@/utils/format';
import { ROLE, STATUS_OPTIONS, STATUS_TAG, STATUS_TEXT } from '@/utils/constants';
import { useUserStore } from '@/store/user';

const route = useRoute();
const router = useRouter();

// 零件号 / 零件名称点击进入详情
function openDetail(row) {
  router.push(`/proposals/${row.id}`);
}
const userStore = useUserStore();

const isAllMode = computed(() => route.path.startsWith('/admin'));

const loading = ref(false);
const rows = ref([]);
const total = ref(0);
const selection = ref([]);
const projects = ref([]);
const customers = ref([]);
const suppliers = ref([]);

const query = reactive({
  page: 1,
  page_size: 20,
  keyword: '',
  part_no: '',
  customer: '',
  supplier: '',
  project: '',
  status: [],
  with_pallet: '',
  date_field: 'created_at',
  start_date: '',
  end_date: '',
});

const canFilterSupplier = computed(() => userStore.user?.role !== ROLE.SUPPLIER);

const dateRange = ref([]);

const reviewVisible = ref(false);
const reviewTarget = ref(null);

async function load() {
  loading.value = true;

  try {
    const params = {
      page: query.page,
      page_size: query.page_size,
      date_field: query.date_field,
    };

    if (query.keyword) params.keyword = query.keyword;
    if (query.part_no) params.part_no = query.part_no;
    if (query.customer) params.customer = query.customer;
    if (query.supplier) params.supplier = query.supplier;
    if (query.project) params.project = query.project;
    if (query.status.length) params.status = query.status.join(',');
    if (query.with_pallet !== '') params.with_pallet = query.with_pallet;
    if (query.start_date) params.start_date = query.start_date;
    if (query.end_date) params.end_date = query.end_date;

    const data = await getProposals(params);
    rows.value = data.list || [];
    total.value = data.total || 0;
  } finally {
    loading.value = false;
  }
}

async function loadOptions() {
  try {
    const [projectList, customerList] = await Promise.all([getProjects(), getCustomers()]);
    projects.value = projectList || [];
    customers.value = customerList || [];

    if (canFilterSupplier.value) {
      suppliers.value = (await getSuppliers()) || [];
    }
  } catch (e) {
    // 下拉数据失败不阻塞列表
  }
}

function search() {
  query.page = 1;
  load();
}

function reset() {
  Object.assign(query, {
    page: 1,
    keyword: '',
    part_no: '',
    customer: '',
    supplier: '',
    project: '',
    status: [],
    with_pallet: '',
    date_field: 'created_at',
    start_date: '',
    end_date: '',
  });
  dateRange.value = [];
  load();
}

watch(dateRange, (value) => {
  query.start_date = value?.[0] || '';
  query.end_date = value?.[1] || '';
});

watch(
  () => route.query.status,
  (value) => {
    const next = value === undefined || value === '' ? [] : String(value).split(',').filter(Boolean);

    if (next.join(',') !== query.status.join(',')) {
      query.status = next;
      query.page = 1;
      load();
    }
  },
);

function handleAction(command, row) {
  const actions = {
    edit: () => router.push(`/proposals/${row.id}/edit`),
    submit: () => runRowAction(row, '确认提交该方案进入评审？', () => submitProposal(row.id), '已提交评审'),
    withdraw: () => runRowAction(row, '确认撤回该方案？撤回后回到草稿状态。', () => withdrawProposal(row.id), '已撤回'),
    review: () => {
      reviewTarget.value = row;
      reviewVisible.value = true;
    },
    change: () =>
      runRowAction(row, '确认发起变更？将生成版本 +1 的变更草稿，原方案继续有效。', () => changeProposal(row.id), '已发起变更'),
    delete: () =>
      runRowAction(row, '删除后不可恢复（附件与日志保留），确认删除？', () => deleteProposal(row.id), '删除成功'),
    export: () => exportRow(row),
  };

  if (actions[command]) actions[command]();
}

async function runRowAction(row, message, action, successMessage) {
  try {
    await ElMessageBox.confirm(message, '操作确认', { type: 'warning' });
  } catch (e) {
    return;
  }

  await action();
  ElMessage.success(successMessage);
  load();
}

async function exportRow(row) {
  const response = await exportProposalExcel(row.id);
  const name = `包装确认单-${row.supplier_name || ''}-${row.part_no || ''}-${row.part_name || ''}.xlsx`;
  await saveBlob(response, name.replace(/[\\/:*?"<>|]/g, ''));
}

async function batchArchive() {
  if (!selection.value.length) {
    ElMessage.warning('请先勾选需要归档的方案');
    return;
  }

  try {
    await ElMessageBox.confirm(`确认归档选中的 ${selection.value.length} 条已通过方案？`, '批量归档', {
      type: 'warning',
    });
  } catch (e) {
    return;
  }

  const data = await archiveProposals(selection.value.map((item) => item.id));
  ElMessage.success(`已归档 ${data.count} 条方案`);
  selection.value = [];
  load();
}

function selectable(row) {
  return row.status === 2;
}

function onSelectionChange(rows) {
  selection.value = rows;
}

onMounted(() => {
  if (route.query.status !== undefined && route.query.status !== '') {
    query.status = String(route.query.status).split(',').filter(Boolean);
  }

  loadOptions();
  load();
});
</script>

<template>
  <div class="page-card">
    <div class="page-toolbar">
      <el-input v-model="query.keyword" placeholder="方案编号 / 零件名称 / 零件号" clearable style="width: 240px"
        @keyup.enter="search" />
      <el-input v-model="query.part_no" placeholder="零件号（模糊）" clearable style="width: 160px"
        @keyup.enter="search" />
      <el-select v-model="query.customer" placeholder="客户" clearable filterable style="width: 150px">
        <el-option v-for="item in customers" :key="item.id" :label="item.name" :value="item.name" />
      </el-select>
      <el-select v-if="canFilterSupplier" v-model="query.supplier" placeholder="供应商" clearable filterable
        style="width: 170px">
        <el-option v-for="item in suppliers" :key="item.id" :label="item.name" :value="item.name" />
      </el-select>
      <el-select v-model="query.project" placeholder="项目车型" clearable filterable style="width: 170px">
        <el-option v-for="item in projects" :key="item" :label="item" :value="item" />
      </el-select>
      <el-select v-model="query.status" placeholder="状态（多选）" multiple collapse-tags clearable style="width: 200px">
        <el-option v-for="item in STATUS_OPTIONS" :key="item.value" :label="item.label" :value="String(item.value)" />
      </el-select>
      <el-select v-model="query.with_pallet" placeholder="是否带托盘" clearable style="width: 140px">
        <el-option label="是" value="1" />
        <el-option label="否" value="0" />
      </el-select>
      <el-select v-model="query.date_field" style="width: 130px">
        <el-option label="创建时间" value="created_at" />
        <el-option label="提交时间" value="submit_time" />
        <el-option label="评审时间" value="review_time" />
        <el-option label="归档时间" value="archive_time" />
      </el-select>
      <el-date-picker v-model="dateRange" type="daterange" value-format="YYYY-MM-DD" start-placeholder="开始日期"
        end-placeholder="结束日期" style="width: 240px" />
      <el-button type="primary" @click="search">查询</el-button>
      <el-button @click="reset">重置</el-button>
      <div class="spacer"></div>
      <el-button v-if="isAllMode" type="success" @click="batchArchive">批量归档</el-button>
    </div>

    <el-table :data="rows" v-loading="loading" size="small" :row-key="(row) => row.id"
      @selection-change="onSelectionChange">
      <el-table-column v-if="isAllMode" type="selection" width="46" :selectable="selectable" />
      <el-table-column prop="project_model" label="项目车型" width="150" show-overflow-tooltip />
      <el-table-column prop="part_no" label="零件号" width="150" show-overflow-tooltip>
        <template #default="{ row }">
          <span class="link-cell" @click="openDetail(row)">{{ row.part_no || '-' }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="part_name" label="零件名称" min-width="160" show-overflow-tooltip>
        <template #default="{ row }">
          <span class="link-cell" @click="openDetail(row)">{{ row.part_name || '-' }}</span>
        </template>
      </el-table-column>
      <el-table-column label="版本" width="70">
        <template #default="{ row }">V{{ row.version }}</template>
      </el-table-column>
      <el-table-column prop="proposal_no" label="方案编号" width="150" />
      <el-table-column label="状态" width="120">
        <template #default="{ row }">
          <el-tag size="small" :type="STATUS_TAG[row.status]" effect="light">
            {{ row.status_label || STATUS_TEXT[row.status] }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="customer_name" label="客户" width="110" show-overflow-tooltip />
      <el-table-column prop="supplier_name" label="供应商" width="130" show-overflow-tooltip />
      <el-table-column prop="creator_name" label="创建人" width="100" />
      <el-table-column label="提交时间" width="160">
        <template #default="{ row }">{{ formatDateTime(row.submit_time) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="100" fixed="right">
        <template #default="{ row }">
          <el-dropdown trigger="click" @command="(command) => handleAction(command, row)">
            <el-button link type="primary">更多<el-icon><component :is="'ArrowDown'" /></el-icon></el-button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item v-if="row.permissions?.edit" command="edit">编辑</el-dropdown-item>
                <el-dropdown-item v-if="row.permissions?.submit" command="submit">提交评审</el-dropdown-item>
                <el-dropdown-item v-if="row.permissions?.withdraw" command="withdraw">撤回</el-dropdown-item>
                <el-dropdown-item v-if="row.permissions?.review" command="review">评审</el-dropdown-item>
                <el-dropdown-item v-if="row.permissions?.change" command="change">发起变更</el-dropdown-item>
                <el-dropdown-item v-if="row.permissions?.delete" command="delete" divided>删除</el-dropdown-item>
                <el-dropdown-item v-if="row.permissions?.export" command="export">导出确认单</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </template>
      </el-table-column>
      <template #empty>
        <div class="empty">暂无符合条件的方案</div>
      </template>
    </el-table>

    <div style="display: flex; justify-content: flex-end; margin-top: 14px">
      <el-pagination v-model:current-page="query.page" v-model:page-size="query.page_size" :total="total"
        :page-sizes="[10, 20, 50, 100]" layout="total, sizes, prev, pager, next"
        @current-change="load" @size-change="search" />
    </div>

    <ReviewDialog v-model="reviewVisible" :proposal="reviewTarget" @success="load" />
  </div>
</template>

<style scoped>
.link-cell {
  color: var(--el-color-primary);
  cursor: pointer;
}

.link-cell:hover {
  text-decoration: underline;
}
</style>
