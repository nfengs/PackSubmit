<template>
  <div class="page-card">
    <div class="page-toolbar">
      <el-input
        v-model="query.keyword"
        placeholder="方案编号 / 零件名称 / 零件号 / 项目车型"
        clearable
        style="width: 260px"
        @keyup.enter="handleSearch"
      />
      <el-input
        v-model="query.part_no"
        placeholder="零件号（模糊）"
        clearable
        style="width: 160px"
        @keyup.enter="handleSearch"
      />
      <el-select v-model="query.customer" placeholder="客户" clearable filterable style="width: 150px">
        <el-option v-for="item in customers" :key="item" :label="item" :value="item" />
      </el-select>
      <el-select v-model="query.supplier" placeholder="供应商" clearable filterable style="width: 150px">
        <el-option v-for="item in suppliers" :key="item" :label="item" :value="item" />
      </el-select>
      <el-select v-model="query.project" placeholder="项目车型" clearable filterable style="width: 170px">
        <el-option v-for="item in projects" :key="item" :label="item" :value="item" />
      </el-select>
      <el-select
        v-model="query.status"
        placeholder="状态（可多选）"
        multiple
        collapse-tags
        collapse-tags-tooltip
        clearable
        style="width: 190px"
      >
        <el-option v-for="item in statusOptions" :key="item.value" :label="item.label" :value="item.value" />
      </el-select>
      <el-select v-model="query.user_id" placeholder="供应商" clearable filterable style="width: 150px">
        <el-option v-for="item in submitters" :key="item.id" :label="item.real_name || item.username" :value="item.id" />
      </el-select>
      <el-select v-model="query.with_pallet" placeholder="是否带托盘" clearable style="width: 130px">
        <el-option label="带托盘" :value="1" />
        <el-option label="不带托盘" :value="0" />
      </el-select>
      <el-select v-model="query.date_field" style="width: 120px">
        <el-option v-for="item in dateFields" :key="item.value" :label="item.label" :value="item.value" />
      </el-select>
      <el-date-picker
        v-model="dateRange"
        type="daterange"
        range-separator="至"
        start-placeholder="开始日期"
        end-placeholder="结束日期"
        value-format="YYYY-MM-DD"
        style="width: 240px"
      />
      <el-button type="primary" @click="handleSearch">查询</el-button>
      <el-button @click="handleReset">重置</el-button>
    </div>

    <div class="page-toolbar">
      <el-button type="success" :loading="exporting" @click="handleExport">
        <el-icon><Download /></el-icon>
        导出明细 Excel
      </el-button>
      <el-popover placement="bottom-start" :width="360" trigger="click">
        <template #reference>
          <el-button>列设置（{{ visibleKeys.length }}/{{ allColumns.length }}）</el-button>
        </template>
        <div class="column-panel">
          <div class="column-panel-actions">
            <el-button link type="primary" size="small" @click="selectAllColumns">全选</el-button>
            <el-button link type="primary" size="small" @click="resetColumns">恢复默认</el-button>
          </div>
          <el-checkbox-group v-model="visibleKeys">
            <el-checkbox v-for="col in allColumns" :key="col.key" :value="col.key" class="column-item">
              {{ col.label }}
            </el-checkbox>
          </el-checkbox-group>
        </div>
      </el-popover>
      <div class="spacer" />
      <span class="text-muted">
        共 {{ total }} 条，当前页 {{ list.length }} 条；导出为当前筛选条件下的全部字段明细
      </span>
    </div>

    <el-table v-loading="loading" :data="list" border stripe>
      <el-table-column
        v-for="col in visibleColumns"
        :key="col.key"
        :label="col.label"
        :width="col.width"
        show-overflow-tooltip
      >
        <template #default="{ row }">
          <StatusTag v-if="col.type === 'status'" :status="row.status" />
          <span v-else>{{ cellText(row, col) }}</span>
        </template>
      </el-table-column>
      <template #empty>
        <span class="text-muted">没有符合筛选条件的方案</span>
      </template>
    </el-table>

    <div class="pagination-wrap">
      <el-pagination
        v-model:current-page="query.page"
        v-model:page-size="query.page_size"
        :total="total"
        :page-sizes="[20, 50, 100, 200]"
        layout="total, sizes, prev, pager, next, jumper"
        background
        @size-change="handleSearch"
        @current-change="loadList"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage } from 'element-plus';
import { Download } from '@element-plus/icons-vue';
import * as XLSX from 'xlsx';
import StatusTag from '@/components/StatusTag.vue';
import { getProposalDetailList, getUserList } from '@/api/admin';
import { getProjects } from '@/api/proposal';
import { getCustomers, getSuppliers } from '@/api/masterData';
import { STATUS_MAP, STATUS_OPTIONS, PACK_MATERIAL_OPTIONS } from '@/utils/constants';

/** 明细字段定义：表格列与导出列共用 */
const ALL_COLUMNS = [
  { key: 'proposal_no', label: '方案编号', width: 170 },
  { key: 'version', label: '版本', width: 70, type: 'version' },
  { key: 'status', label: '状态', width: 130, type: 'status' },
  { key: 'user_name', label: '供应商', width: 100, type: 'user' },
  { key: 'part_no', label: '零件号', width: 140 },
  { key: 'part_name', label: '零件名称', width: 170 },
  { key: 'project_model', label: '项目车型', width: 140 },
  { key: 'customer', label: '客户', width: 130 },
  { key: 'supplier', label: '供应商', width: 130 },
  { key: 'write_date', label: '填写日期', width: 110 },
  { key: 'spec', label: '规格尺寸', width: 150 },
  { key: 'quantity', label: '数量', width: 80 },
  { key: 'part_material', label: '零件材料', width: 110 },
  { key: 'part_weight', label: '产品净重(kg)', width: 120 },
  { key: 'part_size_l', label: '产品外形长(mm)', width: 130 },
  { key: 'part_size_w', label: '产品外形宽(mm)', width: 130 },
  { key: 'part_size_h', label: '产品外形高(mm)', width: 130 },
  { key: 'box_size_l', label: '包装箱外长(mm)', width: 130 },
  { key: 'box_size_w', label: '包装箱外宽(mm)', width: 130 },
  { key: 'box_size_h', label: '包装箱外高(mm)', width: 130 },
  { key: 'gross_weight', label: '毛重(kg)', width: 100 },
  { key: 'pack_box_no', label: '包装箱编号', width: 130 },
  { key: 'pack_material', label: '包装箱材料', width: 110, type: 'packMaterial' },
  { key: 'returnable', label: '包装箱是否返回', width: 130, type: 'yesno' },
  { key: 'dunnage_returnable', label: '内衬是否返回', width: 120, type: 'yesno' },
  { key: 'with_pallet', label: '是否带托盘交付', width: 130, type: 'yesno' },
  { key: 'packing_qty', label: '存放数量(件/箱)', width: 130 },
  { key: 'pallet_size_l', label: '满托盘长(mm)', width: 120 },
  { key: 'pallet_size_w', label: '满托盘宽(mm)', width: 120 },
  { key: 'pallet_size_h', label: '满托盘高(mm)', width: 120 },
  { key: 'pallet_layer_qty', label: '每层包装数量', width: 120 },
  { key: 'pallet_layer_count', label: '堆码层数', width: 100 },
  { key: 'pallet_parts_qty', label: '每托盘零件数量', width: 130 },
  { key: 'customer_address', label: '客户地址', width: 220 },
  { key: 'customer_contact', label: '客户联系人', width: 110 },
  { key: 'customer_phone', label: '客户电话', width: 130 },
  { key: 'customer_email', label: '客户邮箱', width: 170 },
  { key: 'customer_fax', label: '客户传真', width: 130 },
  { key: 'supplier_code', label: '供应商代码', width: 120 },
  { key: 'contact', label: '供应商联系人', width: 120 },
  { key: 'phone', label: '供应商电话', width: 130 },
  { key: 'supplier_address', label: '供应商地址', width: 220 },
  { key: 'supplier_email', label: '供应商邮箱', width: 170 },
  { key: 'supplier_fax', label: '供应商传真', width: 130 },
  { key: 'packaging_instruction', label: '包装操作说明', width: 220 },
  { key: 'submit_time', label: '提交时间', width: 170 },
  { key: 'reviewer_name', label: '评审人', width: 100, type: 'reviewer' },
  { key: 'review_time', label: '评审时间', width: 170 },
  { key: 'review_remark', label: '评审意见', width: 180 },
  { key: 'archive_time', label: '归档时间', width: 170 },
  { key: 'create_time', label: '创建时间', width: 170 },
  { key: 'update_time', label: '更新时间', width: 170 },
];

/** 默认展示列 */
const DEFAULT_KEYS = [
  'proposal_no', 'version', 'status', 'user_name', 'part_no', 'part_name',
  'customer', 'supplier', 'spec', 'quantity', 'with_pallet', 'packing_qty',
  'submit_time', 'archive_time', 'create_time',
];

const DATE_FIELDS = [
  { label: '创建时间', value: 'create_time' },
  { label: '提交时间', value: 'submit_time' },
  { label: '评审时间', value: 'review_time' },
  { label: '归档时间', value: 'archive_time' },
];

const allColumns = ALL_COLUMNS;
const dateFields = DATE_FIELDS;
const statusOptions = STATUS_OPTIONS;
const visibleKeys = ref([...DEFAULT_KEYS]);
const visibleColumns = computed(() => allColumns.filter((col) => visibleKeys.value.includes(col.key)));

const loading = ref(false);
const exporting = ref(false);
const list = ref([]);
const total = ref(0);
const customers = ref([]);
const suppliers = ref([]);
const projects = ref([]);
const submitters = ref([]);
const dateRange = ref([]);

const query = reactive({
  keyword: '',
  part_no: '',
  customer: '',
  supplier: '',
  project: '',
  status: [],
  user_id: '',
  with_pallet: '',
  date_field: 'create_time',
  page: 1,
  page_size: 20,
});

const PACK_MATERIAL_LABEL = PACK_MATERIAL_OPTIONS.reduce((map, item) => {
  map[item.value] = item.label;

  return map;
}, {});

/**
 * 是/否字段文字
 * @param {*} value
 */
function yesNoText(value) {
  if (value === null || value === undefined || value === '') return '未选择';

  return Number(value) === 1 ? '是' : '否';
}

/**
 * 单元格文字（表格与导出共用）
 * @param {object} row
 * @param {object} col
 */
function cellText(row, col) {
  switch (col.type) {
    case 'version':
      return `V${row.version ?? 1}`;
    case 'status':
      return STATUS_MAP[Number(row.status)]?.label || '-';
    case 'user':
      return row.user?.real_name || row.user?.username || '-';
    case 'reviewer':
      return row.reviewer?.real_name || row.reviewer?.username || '-';
    case 'packMaterial':
      return PACK_MATERIAL_LABEL[row.pack_material] || '-';
    case 'yesno':
      return yesNoText(row[col.key]);
    default: {
      const value = row[col.key];

      return value === null || value === undefined || value === '' ? '-' : value;
    }
  }
}

function buildParams() {
  const params = {
    keyword: query.keyword,
    part_no: query.part_no,
    customer: query.customer,
    supplier: query.supplier,
    project: query.project,
    user_id: query.user_id || 0,
    with_pallet: query.with_pallet === 0 || query.with_pallet === 1 ? query.with_pallet : '',
    date_field: query.date_field,
    start_date: dateRange.value?.[0] || '',
    end_date: dateRange.value?.[1] || '',
    page: query.page,
    page_size: query.page_size,
  };

  if (query.status?.length) {
    params.status = query.status.join(',');
  }

  return params;
}

async function loadOptions() {
  const [customerList, supplierList, projectList, userData] = await Promise.all([
    getCustomers(),
    getSuppliers(),
    getProjects(),
    getUserList({ role: 1, page_size: 100 }),
  ]);

  customers.value = (customerList || []).map((item) => item.name).filter(Boolean);
  suppliers.value = (supplierList || []).map((item) => item.name).filter(Boolean);
  projects.value = projectList || [];
  submitters.value = (userData?.list || []).map((item) => ({
    id: item.id,
    real_name: item.real_name,
    username: item.username,
  }));
}

async function loadList() {
  loading.value = true;

  try {
    const data = await getProposalDetailList(buildParams());
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
  query.part_no = '';
  query.customer = '';
  query.supplier = '';
  query.project = '';
  query.status = [];
  query.user_id = '';
  query.with_pallet = '';
  query.date_field = 'create_time';
  dateRange.value = [];
  query.page = 1;
  loadList();
}

function selectAllColumns() {
  visibleKeys.value = allColumns.map((col) => col.key);
}

function resetColumns() {
  visibleKeys.value = [...DEFAULT_KEYS];
}

/**
 * 导出明细：按当前筛选条件分页拉取全部数据，全字段写入 Excel
 */
async function handleExport() {
  exporting.value = true;

  try {
    const pageSize = 500;
    const maxPage = 100;
    const rows = [];
    let page = 1;

    while (page <= maxPage) {
      const data = await getProposalDetailList({ ...buildParams(), page, page_size: pageSize });
      const current = data.list || [];
      rows.push(...current);

      if (current.length < pageSize || rows.length >= (data.total || 0)) break;
      page += 1;
    }

    if (rows.length === 0) {
      ElMessage.warning('没有可导出的数据');
      return;
    }

    const exportRows = rows.map((row) => {
      const item = {};

      allColumns.forEach((col) => {
        item[col.label] = cellText(row, col);
      });

      return item;
    });

    const worksheet = XLSX.utils.json_to_sheet(exportRows);
    worksheet['!cols'] = allColumns.map((col) => ({ wch: Math.min(40, Math.max(10, col.label.length * 2 + 6)) }));

    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, '全量详细方案');
    const today = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(workbook, `全量详细方案_${today}.xlsx`);
    ElMessage.success(`已导出 ${rows.length} 条明细`);
  } finally {
    exporting.value = false;
  }
}

onMounted(async () => {
  await loadOptions();
  await loadList();
});
</script>

<style scoped>
.column-panel {
  max-height: 320px;
  overflow-y: auto;
}

.column-panel-actions {
  display: flex;
  gap: 12px;
  margin-bottom: 6px;
}

.column-item {
  display: inline-flex;
  width: 45%;
  margin-right: 0;
}
</style>
