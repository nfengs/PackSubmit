<template>
  <div class="page-card">
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
      <el-date-picker
        v-model="dateRange"
        type="daterange"
        range-separator="至"
        start-placeholder="创建开始"
        end-placeholder="创建结束"
        value-format="YYYY-MM-DD"
        style="width: 240px"
      />
      <el-button type="primary" @click="handleSearch">查询</el-button>
      <el-button @click="handleReset">重置</el-button>
    </div>

    <div class="page-toolbar">
      <el-button
        type="warning"
        :disabled="selection.length === 0"
        :loading="archiving"
        @click="handleArchive"
      >
        批量归档（{{ selection.length }}）
      </el-button>
      <el-button type="success" :loading="exporting" @click="handleExport">
        <el-icon><Download /></el-icon>
        导出 Excel
      </el-button>
      <div class="spacer" />
      <span class="text-muted">共 {{ total }} 条方案，仅“评审通过”的方案可归档</span>
    </div>

    <el-table
      v-loading="loading"
      :data="list"
      border
      stripe
      @selection-change="handleSelectionChange"
    >
      <el-table-column type="selection" width="50" :selectable="canSelect" />
      <el-table-column prop="proposal_no" label="方案编号" width="170" />
      <el-table-column prop="part_name" label="零件名称" min-width="180" show-overflow-tooltip />
      <el-table-column prop="part_no" label="零件号" width="150" show-overflow-tooltip />
      <el-table-column label="客户" width="120">
        <template #default="{ row }">{{ customerLabel(row.customer) }}</template>
      </el-table-column>
      <el-table-column label="版本" width="80" align="center">
        <template #default="{ row }">V{{ row.version }}</template>
      </el-table-column>
      <el-table-column label="状态" width="110" align="center">
        <template #default="{ row }">
          <StatusTag :status="row.status" />
        </template>
      </el-table-column>
      <el-table-column label="供应商" width="110">
        <template #default="{ row }">{{ row.user?.real_name || '-' }}</template>
      </el-table-column>
      <el-table-column prop="create_time" label="创建时间" width="170" align="center" />
      <el-table-column label="操作" width="140" fixed="right" align="center">
        <template #default="{ row }">
          <el-button link type="primary" @click="router.push(`/proposal/detail/${row.id}`)">
            查看
          </el-button>
          <el-button
            v-if="Number(row.status) === STATUS.DRAFT"
            link
            type="danger"
            @click="handleDelete(row)"
          >
            删除
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
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        background
        @size-change="handleSearch"
        @current-change="loadList"
      />
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Download } from '@element-plus/icons-vue';
import * as XLSX from 'xlsx';
import StatusTag from '@/components/StatusTag.vue';
import { getAllProposals, archiveProposals } from '@/api/admin';
import { getProjects, deleteProposal } from '@/api/proposal';
import { getCustomers } from '@/api/masterData';
import { STATUS, STATUS_OPTIONS, STATUS_MAP } from '@/utils/constants';

const router = useRouter();

const loading = ref(false);
const exporting = ref(false);
const archiving = ref(false);
const list = ref([]);
const total = ref(0);
const selection = ref([]);
const customers = ref([]);
const projects = ref([]);
const statusOptions = STATUS_OPTIONS;
const dateRange = ref([]);

const query = reactive({
  keyword: '',
  customer: '',
  project: '',
  status: '',
  start_date: '',
  end_date: '',
  page: 1,
  page_size: 10,
});

const customerLabel = (value) =>
  customers.value.find((item) => item.value === value)?.label || value || '-';

/** 仅已通过的方案可勾选归档 */
const canSelect = (row) => Number(row.status) === STATUS.APPROVED;

async function loadDict() {
  const [customerList, projectList] = await Promise.all([getCustomers(), getProjects()]);
  customers.value = (customerList || []).map((item) => ({ label: item.name, value: item.name }));
  projects.value = projectList || [];
}

function buildParams() {
  return {
    ...query,
    start_date: dateRange.value?.[0] || '',
    end_date: dateRange.value?.[1] || '',
  };
}

async function loadList() {
  loading.value = true;

  try {
    const data = await getAllProposals(buildParams());
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
  dateRange.value = [];
  query.page = 1;
  loadList();
}

function handleSelectionChange(rows) {
  selection.value = rows;
}

/**
 * 删除方案（仅草稿；管理员可删除导入生成的草稿）
 * @param {object} row
 */
async function handleDelete(row) {
  await ElMessageBox.confirm(
    `确定删除方案「${row.part_name || row.proposal_no}」吗？删除后不可恢复，重新导入零件号可再次生成草稿。`,
    '删除方案',
    { type: 'warning' },
  );
  await deleteProposal(row.id);
  ElMessage.success('删除成功');
  loadList();
}

async function handleArchive() {
  if (selection.value.length === 0) {
    ElMessage.warning('请选择要归档的方案');
    return;
  }

  await ElMessageBox.confirm(
    `确定归档选中的 ${selection.value.length} 条方案吗？仅“评审通过”的方案会被归档。`,
    '批量归档',
    { type: 'warning' },
  );

  archiving.value = true;

  try {
    const data = await archiveProposals(selection.value.map((item) => item.id));
    ElMessage.success(`已归档 ${data.count} 条方案`);
    selection.value = [];
    loadList();
  } finally {
    archiving.value = false;
  }
}

/**
 * 导出 Excel：分页拉取全部数据后前端生成 xlsx
 */
async function handleExport() {
  exporting.value = true;

  try {
    const pageSize = 200;
    const rows = [];
    let page = 1;

    while (page <= 50) {
      const data = await getAllProposals({ ...buildParams(), page, page_size: pageSize });
      const current = data.list || [];
      rows.push(...current);

      if (current.length < pageSize || rows.length >= data.total) break;
      page += 1;
    }

    if (rows.length === 0) {
      ElMessage.warning('没有可导出的数据');
      return;
    }

    const exportRows = rows.map((row) => ({
      方案编号: row.proposal_no,
      零件名称: row.part_name || '',
      零件号: row.part_no || '',
      客户: customerLabel(row.customer),
      规格尺寸: row.spec || '',
      数量: row.quantity ?? 0,
      供应商: row.supplier || '',
      供应商: row.user?.real_name || '',
      版本: `V${row.version}`,
      状态: STATUS_MAP[Number(row.status)]?.label || '',
      提交时间: row.submit_time || '',
      评审人: row.reviewer?.real_name || '',
      评审时间: row.review_time || '',
      评审意见: row.review_remark || '',
      创建时间: row.create_time || '',
    }));

    const worksheet = XLSX.utils.json_to_sheet(exportRows);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, '方案汇总');
    const today = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(workbook, `包装方案汇总_${today}.xlsx`);
    ElMessage.success(`已导出 ${rows.length} 条方案`);
  } finally {
    exporting.value = false;
  }
}

onMounted(async () => {
  await loadDict();
  await loadList();
});
</script>
