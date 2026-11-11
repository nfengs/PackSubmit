<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage } from 'element-plus';
import * as XLSX from 'xlsx';

import { getProposalReport, getProjects } from '@/api/proposal';
import { getCustomers, getSuppliers } from '@/api/masterdata';
import { PACK_MATERIAL_TEXT, STATUS_OPTIONS } from '@/utils/constants';
import { formatDateTime, numberText } from '@/utils/format';

// 52 个字段与包装确认单保持一致
const COLUMNS = [
  { key: 'proposal_no', label: '方案编号', width: 150 },
  { key: 'version', label: '版本', width: 70, type: 'version' },
  { key: 'status_label', label: '状态', width: 120 },
  { key: 'supplier_name', label: '供应商', width: 140 },
  { key: 'supplier_code', label: '供应商代码', width: 130 },
  { key: 'supplier_address', label: '供应商地址', width: 200 },
  { key: 'contact', label: '供应商联系人', width: 120 },
  { key: 'phone', label: '供应商电话', width: 130 },
  { key: 'supplier_email', label: '供应商邮箱', width: 170 },
  { key: 'supplier_fax', label: '供应商传真', width: 130 },
  { key: 'customer_name', label: '客户', width: 120 },
  { key: 'customer_address', label: '客户地址', width: 190 },
  { key: 'customer_contact', label: '客户联系人', width: 110 },
  { key: 'customer_phone', label: '客户电话', width: 130 },
  { key: 'customer_email', label: '客户邮箱', width: 170 },
  { key: 'customer_fax', label: '客户传真', width: 130 },
  { key: 'project_model', label: '项目车型', width: 140 },
  { key: 'part_no', label: '零件号', width: 140 },
  { key: 'part_name', label: '零件名称', width: 170 },
  { key: 'part_material', label: '零件材料', width: 110 },
  { key: 'write_date', label: '填写日期', width: 110 },
  { key: 'spec', label: '规格尺寸', width: 150 },
  { key: 'quantity', label: '数量', width: 80 },
  { key: 'pack_box_no', label: '包装箱编号', width: 130 },
  { key: 'pack_material', label: '包装箱材料', width: 110, type: 'material' },
  { key: 'part_weight', label: '产品净重(kg)', width: 110, type: 'number' },
  { key: 'part_size_l', label: '产品外形长(mm)', width: 120, type: 'number' },
  { key: 'part_size_w', label: '产品外形宽(mm)', width: 120, type: 'number' },
  { key: 'part_size_h', label: '产品外形高(mm)', width: 120, type: 'number' },
  { key: 'box_size_l', label: '包装箱外长(mm)', width: 120, type: 'number' },
  { key: 'box_size_w', label: '包装箱外宽(mm)', width: 120, type: 'number' },
  { key: 'box_size_h', label: '包装箱外高(mm)', width: 120, type: 'number' },
  { key: 'gross_weight', label: '毛重(kg)', width: 100, type: 'number' },
  { key: 'returnable', label: '包装箱是否返回', width: 120, type: 'bool' },
  { key: 'dunnage_returnable', label: '内衬是否返回', width: 110, type: 'bool' },
  { key: 'with_pallet', label: '是否带托盘', width: 110, type: 'bool' },
  { key: 'packing_qty', label: '存放数量(件/箱)', width: 130 },
  { key: 'pallet_size_l', label: '满托盘外长(mm)', width: 120, type: 'number' },
  { key: 'pallet_size_w', label: '满托盘外宽(mm)', width: 120, type: 'number' },
  { key: 'pallet_size_h', label: '满托盘外高(mm)', width: 120, type: 'number' },
  { key: 'pallet_layer_qty', label: '每层包装箱数量', width: 130 },
  { key: 'pallet_layer_count', label: '堆码层数', width: 100 },
  { key: 'pallet_parts_qty', label: '每托盘零件数量', width: 130 },
  { key: 'packaging_instruction', label: '包装操作说明', width: 220 },
  { key: 'description', label: '方案说明', width: 180 },
  { key: 'creator_name', label: '创建人', width: 100 },
  { key: 'submit_time', label: '提交时间', width: 160, type: 'datetime' },
  { key: 'reviewer_name', label: '评审人', width: 100 },
  { key: 'review_time', label: '评审时间', width: 160, type: 'datetime' },
  { key: 'review_remark', label: '评审意见', width: 200 },
  { key: 'archive_time', label: '归档时间', width: 160, type: 'datetime' },
  { key: 'created_at', label: '创建时间', width: 160, type: 'datetime' },
];

const DEFAULT_KEYS = [
  'proposal_no',
  'version',
  'status_label',
  'customer_name',
  'project_model',
  'part_no',
  'part_name',
  'supplier_name',
  'creator_name',
  'write_date',
  'quantity',
  'pack_box_no',
  'submit_time',
  'review_time',
  'archive_time',
];

const STORAGE_KEY = 'packsubmit_report_columns_v1';
const ALL_KEYS = COLUMNS.map((item) => item.key);

function loadVisibleKeys() {
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');

    if (Array.isArray(saved) && saved.length) {
      const valid = saved.filter((key) => ALL_KEYS.includes(key));
      if (valid.length) return valid;
    }
  } catch (e) {
    // 忽略损坏的本地配置
  }

  return [...DEFAULT_KEYS];
}

const loading = ref(false);
const exporting = ref(false);
const rows = ref([]);
const total = ref(0);
const visibleKeys = ref(loadVisibleKeys());
const columnsVisible = ref(false);
const draftKeys = ref([...visibleKeys.value]);

const projects = ref([]);
const customers = ref([]);
const suppliers = ref([]);
const dateRange = ref([]);

const query = reactive({
  page: 1,
  page_size: 50,
  keyword: '',
  part_no: '',
  customer: '',
  supplier: '',
  project: '',
  status: [],
  with_pallet: '',
  date_field: 'created_at',
});

const visibleColumns = computed(() => COLUMNS.filter((item) => visibleKeys.value.includes(item.key)));

function cellText(column, row) {
  const value = row[column.key];

  switch (column.type) {
    case 'version':
      return value ? `V${value}` : '-';
    case 'bool':
      return value === 1 ? '是' : value === 0 ? '否' : '-';
    case 'material':
      return PACK_MATERIAL_TEXT[value] || '-';
    case 'number':
      return numberText(value);
    case 'datetime':
      return formatDateTime(value);
    default:
      return value === null || value === undefined || value === '' ? '-' : String(value);
  }
}

function buildParams(extra = {}) {
  const params = { page: query.page, page_size: query.page_size, ...extra };

  if (query.keyword) params.keyword = query.keyword;
  if (query.part_no) params.part_no = query.part_no;
  if (query.customer) params.customer = query.customer;
  if (query.supplier) params.supplier = query.supplier;
  if (query.project) params.project = query.project;
  if (query.status.length) params.status = query.status.join(',');
  if (query.with_pallet !== '') params.with_pallet = query.with_pallet;

  if (dateRange.value && dateRange.value.length === 2) {
    params.date_field = query.date_field;
    params.start_date = dateRange.value[0];
    params.end_date = dateRange.value[1];
  }

  return params;
}

async function load() {
  loading.value = true;

  try {
    const data = await getProposalReport(buildParams());
    rows.value = data.list || [];
    total.value = data.total || 0;
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  const [projectList, customerList, supplierList] = await Promise.all([
    getProjects().catch(() => []),
    getCustomers().catch(() => []),
    getSuppliers().catch(() => []),
  ]);

  projects.value = projectList || [];
  customers.value = customerList || [];
  suppliers.value = supplierList || [];
  load();
});

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
  });
  dateRange.value = [];
  load();
}

function openColumns() {
  draftKeys.value = [...visibleKeys.value];
  columnsVisible.value = true;
}

function applyColumns() {
  if (!draftKeys.value.length) {
    ElMessage.warning('至少保留一列');
    return;
  }

  visibleKeys.value = [...draftKeys.value];
  localStorage.setItem(STORAGE_KEY, JSON.stringify(visibleKeys.value));
  columnsVisible.value = false;
}

function resetColumns() {
  draftKeys.value = [...DEFAULT_KEYS];
}

async function exportExcel() {
  exporting.value = true;

  try {
    const all = [];
    let page = 1;

    while (true) {
      const data = await getProposalReport(buildParams({ page, page_size: 500 }));
      const list = data.list || [];
      all.push(...list);

      if (!list.length || all.length >= (data.total || 0)) break;
      page += 1;
    }

    if (!all.length) {
      ElMessage.warning('当前筛选条件下没有数据');
      return;
    }

    const header = COLUMNS.map((item) => item.label);
    const body = all.map((row) => COLUMNS.map((column) => cellText(column, row)));
    const sheet = XLSX.utils.aoa_to_sheet([header, ...body]);

    sheet['!cols'] = COLUMNS.map((column) => ({ wch: Math.min(40, Math.max(10, Math.round((column.width || 120) / 7))) }));

    const book = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(book, sheet, '全量详细方案');

    const today = new Date();
    const stamp = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
    XLSX.writeFile(book, `全量详细方案_${stamp}.xlsx`);
  } finally {
    exporting.value = false;
  }
}
</script>

<template>
  <div class="page-card">
    <div class="page-toolbar">
      <el-input v-model="query.keyword" placeholder="方案编号 / 零件名称 / 零件号 / 项目" clearable
        style="width: 260px" @keyup.enter="search" />
      <el-input v-model="query.part_no" placeholder="零件号（模糊）" clearable style="width: 160px"
        @keyup.enter="search" />
      <el-select v-model="query.customer" placeholder="客户" clearable filterable style="width: 150px">
        <el-option v-for="item in customers" :key="item.id" :label="item.name" :value="item.name" />
      </el-select>
      <el-select v-model="query.supplier" placeholder="供应商" clearable filterable style="width: 170px">
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
      <el-button @click="openColumns">列设置</el-button>
      <el-button type="primary" :loading="exporting" @click="exportExcel">导出明细</el-button>
    </div>

    <el-alert type="info" :closable="false" show-icon style="margin-bottom: 12px"
      title="明细只读，共 52 个字段；导出按当前筛选条件拉取全部数据，不受「列设置」影响。" />

    <el-table :data="rows" v-loading="loading" size="small" border style="width: 100%">
      <el-table-column v-for="column in visibleColumns" :key="column.key" :label="column.label"
        :width="column.width" show-overflow-tooltip>
        <template #default="{ row }">{{ cellText(column, row) }}</template>
      </el-table-column>
      <template #empty>
        <div class="empty">暂无符合条件的方案</div>
      </template>
    </el-table>

    <div style="display: flex; justify-content: flex-end; margin-top: 14px">
      <el-pagination v-model:current-page="query.page" v-model:page-size="query.page_size" :total="total"
        :page-sizes="[20, 50, 100, 200, 500]" layout="total, sizes, prev, pager, next" @current-change="load"
        @size-change="search" />
    </div>

    <el-dialog v-model="columnsVisible" title="列设置" width="720px">
      <div style="margin-bottom: 10px; display: flex; gap: 8px">
        <el-button size="small" @click="draftKeys = [...ALL_KEYS]">全选</el-button>
        <el-button size="small" @click="draftKeys = [...DEFAULT_KEYS]">恢复默认</el-button>
        <el-button size="small" @click="draftKeys = []">清空</el-button>
        <span class="muted" style="font-size: 12px; align-self: center">
          已选 {{ draftKeys.length }} / {{ ALL_KEYS.length }} 列（配置保存在本机浏览器）
        </span>
      </div>

      <el-checkbox-group v-model="draftKeys">
        <el-checkbox v-for="column in COLUMNS" :key="column.key" :value="column.key"
          style="width: 160px; margin-right: 0">
          {{ column.label }}
        </el-checkbox>
      </el-checkbox-group>

      <template #footer>
        <el-button @click="columnsVisible = false">取消</el-button>
        <el-button type="primary" @click="applyColumns">应用</el-button>
      </template>
    </el-dialog>
  </div>
</template>
