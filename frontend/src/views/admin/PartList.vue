<template>
  <div class="page-card">
    <div class="page-toolbar">
      <el-input
        v-model="query.keyword"
        placeholder="零件号 / 零件名称 / 项目"
        clearable
        style="width: 220px"
        @keyup.enter="handleSearch"
      />
      <el-select v-model="query.supplier" placeholder="供应商" clearable filterable style="width: 170px">
        <el-option v-for="item in suppliers" :key="item.id" :label="item.name" :value="item.name" />
      </el-select>
      <el-button type="primary" @click="handleSearch">查询</el-button>
      <el-button @click="handleReset">重置</el-button>
      <div class="spacer" />
      <el-button @click="handleTemplate">下载模板</el-button>
      <el-button type="warning" plain @click="openBatch">批量修改</el-button>
      <el-button type="primary" @click="openImport">
        <el-icon><Upload /></el-icon>
        导入 Excel
      </el-button>
    </div>

    <el-alert
      type="info"
      :closable="false"
      show-icon
      title="导入零件号时会自动为对应供应商生成方案草稿；Excel 列为：供应商名称、项目名称、零件号、零件名称"
      style="margin-bottom: 12px"
    />

    <el-table v-loading="loading" :data="list" border stripe>
      <el-table-column prop="supplier_name" label="供应商名称" min-width="130" show-overflow-tooltip />
      <el-table-column prop="project_name" label="项目名称" min-width="140" show-overflow-tooltip />
      <el-table-column prop="part_no" label="零件号" min-width="150" show-overflow-tooltip />
      <el-table-column prop="part_name" label="零件名称" min-width="180" show-overflow-tooltip />
      <el-table-column label="更新时间" width="230" align="center">
        <template #default="{ row }">
          <span>{{ row.update_time || '-' }}</span>
          <el-button link type="primary" class="row-log-btn" @click="openLogs(row)">日志</el-button>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="130" fixed="right" align="center">
        <template #default="{ row }">
          <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
      <template #empty>
        <span class="text-muted">暂无指定零件号，请先导入 Excel</span>
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

    <el-dialog v-model="editVisible" title="修改零件号信息" width="480px">
      <el-form label-width="90px">
        <el-form-item label="供应商名称">
          <el-input :model-value="editForm.supplier_name" disabled />
        </el-form-item>
        <el-form-item label="零件号">
          <el-input :model-value="editForm.part_no" disabled />
        </el-form-item>
        <el-form-item label="项目名称">
          <el-input
            v-model="editForm.project_name"
            maxlength="150"
            show-word-limit
            placeholder="请输入项目名称"
          />
        </el-form-item>
        <el-form-item label="零件名称">
          <el-input
            v-model="editForm.part_name"
            maxlength="200"
            show-word-limit
            placeholder="请输入零件名称"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="editVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSaveEdit">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="logVisible" :title="logTitle" width="680px">
      <el-table v-loading="logLoading" :data="logs" border size="small" max-height="420">
        <el-table-column prop="create_time" label="修改时间" width="170" align="center" />
        <el-table-column label="操作人" width="110" align="center">
          <template #default="{ row }">{{ row.operator?.real_name || '-' }}</template>
        </el-table-column>
        <el-table-column prop="action_name" label="操作" width="130" align="center" />
        <el-table-column prop="content" label="修改内容" min-width="240" show-overflow-tooltip />
        <template #empty>
          <span class="text-muted">暂无修改日志</span>
        </template>
      </el-table>

      <template #footer>
        <el-button @click="logVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="batchVisible" title="批量修改零件号信息" width="820px" destroy-on-close>
      <el-alert
        type="info"
        :closable="false"
        show-icon
        title="Excel 格式与导入一致：供应商名称、项目名称、零件号、零件名称。仅修改已存在的供应商 + 零件号，不新增记录"
        style="margin-bottom: 16px"
      />
      <el-upload
        ref="batchUploadRef"
        drag
        accept=".xlsx,.xls"
        :auto-upload="false"
        :limit="1"
        :on-change="handleBatchFileChange"
        :on-remove="handleBatchFileRemove"
        :on-exceed="handleBatchExceed"
      >
        <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
        <div class="el-upload__text">将 Excel 拖到此处，或<em>点击选择文件</em></div>
        <template #tip>
          <div class="el-upload__tip">
            仅支持 .xlsx / .xls，文件不超过 10MB；同一供应商 + 零件号唯一，重复行会自动识别
          </div>
        </template>
      </el-upload>

      <div v-if="batchPreviewRows.length" class="batch-preview">
        <p>
          共 <strong>{{ batchPreviewRows.length }}</strong> 行：可修改
          <strong>{{ batchSummary.update }}</strong> 条，无变化
          <strong>{{ batchSummary.unchanged }}</strong> 条，零件号不存在
          <strong>{{ batchSummary.not_found }}</strong> 条，异常
          <strong>{{ batchSummary.error }}</strong> 条
        </p>
        <el-table :data="batchPreviewRows" border size="small" max-height="320" style="margin-top: 10px">
          <el-table-column prop="line" label="行" width="55" align="center" />
          <el-table-column prop="supplier_name" label="供应商" width="120" show-overflow-tooltip />
          <el-table-column prop="part_no" label="零件号" width="140" show-overflow-tooltip />
          <el-table-column label="状态" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="batchStatusType(row.status)" size="small">
                {{ batchStatusText(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="变更内容" min-width="300" show-overflow-tooltip>
            <template #default="{ row }">{{ row.changes || row.message || '-' }}</template>
          </el-table-column>
        </el-table>
      </div>

      <template #footer>
        <el-button @click="batchVisible = false">取消</el-button>
        <el-button
          type="primary"
          :loading="batchParsing"
          :disabled="!batchFile"
          @click="handleBatchPreview"
        >
          校验并预览
        </el-button>
        <el-button
          type="danger"
          :loading="batchApplying"
          :disabled="!batchSummary.update"
          @click="handleBatchApply"
        >
          确认修改（{{ batchSummary.update }}）
        </el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="importVisible" title="导入指定零件号" width="560px" destroy-on-close>
      <el-alert
        type="info"
        :closable="false"
        show-icon
        title="Excel 第一列供应商名称、第二列项目名称、第三列零件号、第四列零件名称（首行表头会自动跳过）"
        style="margin-bottom: 16px"
      />
      <el-upload
        ref="uploadRef"
        drag
        accept=".xlsx,.xls"
        :auto-upload="false"
        :limit="1"
        :on-change="handleFileChange"
        :on-remove="handleFileRemove"
        :on-exceed="handleExceed"
      >
        <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
        <div class="el-upload__text">将 Excel 拖到此处，或<em>点击选择文件</em></div>
        <template #tip>
          <div class="el-upload__tip">
            仅支持 .xlsx / .xls，文件不超过 10MB；同一供应商 + 零件号会更新项目与零件名称，并为尚无方案的零件生成草稿
          </div>
        </template>
      </el-upload>

      <div v-if="importResult" class="import-result">
        <p>
          导入完成：新增 <strong>{{ importResult.inserted }}</strong> 条，更新
          <strong>{{ importResult.updated }}</strong> 条，跳过
          <strong>{{ importResult.skipped }}</strong> 条，生成方案草稿
          <strong>{{ importResult.drafts || 0 }}</strong> 条
        </p>
        <ul v-if="importResult.warnings && importResult.warnings.length" class="import-warning">
          <li v-for="(item, index) in importResult.warnings" :key="index">{{ item }}</li>
        </ul>
        <ul v-if="importResult.errors && importResult.errors.length">
          <li v-for="(item, index) in importResult.errors" :key="index">{{ item }}</li>
        </ul>
      </div>

      <template #footer>
        <el-button @click="importVisible = false">关闭</el-button>
        <el-button type="primary" :loading="importing" @click="handleImport">开始导入</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Upload, UploadFilled } from '@element-plus/icons-vue';
import {
  getPartList,
  importParts,
  deletePart,
  downloadPartTemplate,
  updatePart,
  getPartLogs,
  previewBatchUpdateParts,
  batchUpdateParts,
} from '@/api/admin';
import { getSuppliers } from '@/api/masterData';
import { resolveFileName } from '@/api/attachment';

const loading = ref(false);
const list = ref([]);
const total = ref(0);
const suppliers = ref([]);

const query = reactive({
  keyword: '',
  supplier: '',
  page: 1,
  page_size: 10,
});

const importVisible = ref(false);
const importing = ref(false);
const importFile = ref(null);
const importResult = ref(null);
const uploadRef = ref(null);

/** 批量修改弹窗 */
const batchVisible = ref(false);
const batchParsing = ref(false);
const batchApplying = ref(false);
const batchFile = ref(null);
const batchUploadRef = ref(null);
const batchPreviewRows = ref([]);
const batchSummary = ref({ update: 0, unchanged: 0, not_found: 0, error: 0 });

/** 编辑弹窗 */
const editVisible = ref(false);
const saving = ref(false);
const editForm = reactive({
  id: 0,
  supplier_name: '',
  part_no: '',
  project_name: '',
  part_name: '',
});

/** 修改日志弹窗 */
const logVisible = ref(false);
const logLoading = ref(false);
const logs = ref([]);
const logPart = ref({});
const logTitle = computed(
  () => `修改日志 · ${logPart.value.supplier_name || ''} / ${logPart.value.part_no || ''}`,
);

async function loadList() {
  loading.value = true;

  try {
    const data = await getPartList({ ...query });
    list.value = data.list || [];
    total.value = data.total || 0;
  } finally {
    loading.value = false;
  }
}

async function loadSuppliers() {
  const data = await getSuppliers();
  suppliers.value = data || [];
}

function handleSearch() {
  query.page = 1;
  loadList();
}

function handleReset() {
  query.keyword = '';
  query.supplier = '';
  query.page = 1;
  loadList();
}

function openImport() {
  importFile.value = null;
  importResult.value = null;
  importVisible.value = true;
}

/**
 * 打开批量修改弹窗
 */
function openBatch() {
  batchFile.value = null;
  batchPreviewRows.value = [];
  batchSummary.value = { update: 0, unchanged: 0, not_found: 0, error: 0 };
  batchVisible.value = true;
}

function handleBatchFileChange(uploadFile) {
  batchFile.value = uploadFile.raw || null;
  batchPreviewRows.value = [];
  batchSummary.value = { update: 0, unchanged: 0, not_found: 0, error: 0 };
}

function handleBatchFileRemove() {
  batchFile.value = null;
  batchPreviewRows.value = [];
  batchSummary.value = { update: 0, unchanged: 0, not_found: 0, error: 0 };
}

function handleBatchExceed(files) {
  batchUploadRef.value?.clearFiles();
  batchUploadRef.value?.handleStart(files[0]);
  batchFile.value = files[0];
  batchPreviewRows.value = [];
  batchSummary.value = { update: 0, unchanged: 0, not_found: 0, error: 0 };
}

/**
 * 上传 Excel 并校验预览（不写库）
 */
async function handleBatchPreview() {
  if (!batchFile.value) return;

  batchParsing.value = true;

  try {
    const formData = new FormData();
    formData.append('file', batchFile.value);
    const data = await previewBatchUpdateParts(formData);
    batchPreviewRows.value = data.rows || [];
    batchSummary.value = data.summary || { update: 0, unchanged: 0, not_found: 0, error: 0 };
  } finally {
    batchParsing.value = false;
  }
}

/**
 * 二次确认后提交批量修改
 */
async function handleBatchApply() {
  const rows = batchPreviewRows.value
    .filter((item) => item.status === 'update')
    .map((item) => ({
      supplier_name: item.supplier_name,
      part_no: item.part_no,
      project_name: item.project_name,
      part_name: item.part_name,
    }));

  if (rows.length === 0) {
    ElMessage.warning('没有可修改的内容');
    return;
  }

  await ElMessageBox.confirm(
    `即将批量修改 ${rows.length} 条零件号的项目名称/零件名称，修改后不可撤销，确定继续吗？`,
    '二次确认',
    { type: 'warning', confirmButtonText: '确认修改', cancelButtonText: '取消' },
  );

  batchApplying.value = true;

  try {
    const data = await batchUpdateParts(rows);
    ElMessage.success(`批量修改完成：更新 ${data.updated} 条`);
    batchVisible.value = false;
    loadList();
  } finally {
    batchApplying.value = false;
  }
}

/**
 * 批量修改预览状态文字
 * @param {string} status
 * @returns {string}
 */
function batchStatusText(status) {
  return {
    update: '可修改',
    unchanged: '无变化',
    not_found: '不存在',
    error: '异常',
  }[status] || '异常';
}

/**
 * 批量修改预览状态颜色
 * @param {string} status
 * @returns {string}
 */
function batchStatusType(status) {
  return {
    update: 'warning',
    unchanged: 'info',
    not_found: 'danger',
    error: 'danger',
  }[status] || 'info';
}

function handleFileChange(uploadFile) {
  importFile.value = uploadFile.raw || null;
  importResult.value = null;
}

function handleFileRemove() {
  importFile.value = null;
}

function handleExceed(files) {
  uploadRef.value?.clearFiles();
  uploadRef.value?.handleStart(files[0]);
  importFile.value = files[0];
}

async function handleImport() {
  if (!importFile.value) {
    ElMessage.warning('请选择要导入的 Excel 文件');
    return;
  }

  const formData = new FormData();
  formData.append('file', importFile.value);

  importing.value = true;

  try {
    importResult.value = await importParts(formData);
    ElMessage.success('导入完成');
    query.page = 1;
    loadList();
  } finally {
    importing.value = false;
  }
}

async function handleTemplate() {
  const response = await downloadPartTemplate();
  const name = resolveFileName(response, '指定零件号导入模板.xlsx');
  const url = URL.createObjectURL(response.data);
  const link = document.createElement('a');
  link.href = url;
  link.download = name;
  link.click();
  URL.revokeObjectURL(url);
}

/**
 * 打开编辑弹窗
 * @param {object} row
 */
function openEdit(row) {
  editForm.id = row.id;
  editForm.supplier_name = row.supplier_name;
  editForm.part_no = row.part_no;
  editForm.project_name = row.project_name || '';
  editForm.part_name = row.part_name || '';
  editVisible.value = true;
}

/**
 * 保存项目名称 / 零件名称修改
 */
async function handleSaveEdit() {
  saving.value = true;

  try {
    await updatePart(editForm.id, {
      project_name: editForm.project_name,
      part_name: editForm.part_name,
    });
    ElMessage.success('修改成功');
    editVisible.value = false;
    loadList();
  } finally {
    saving.value = false;
  }
}

/**
 * 查询该零件号的修改日志
 * @param {object} row
 */
async function openLogs(row) {
  logPart.value = row;
  logs.value = [];
  logVisible.value = true;
  logLoading.value = true;

  try {
    const data = await getPartLogs(row.id);
    logs.value = data || [];
  } finally {
    logLoading.value = false;
  }
}

async function handleDelete(row) {
  await ElMessageBox.confirm(
    `确定删除「${row.supplier_name} / ${row.part_no}」吗？删除后供应商将无法再选择该零件号。`,
    '删除指定零件号',
    { type: 'warning' },
  );
  await deletePart(row.id);
  ElMessage.success('已删除');
  loadList();
}

onMounted(() => {
  loadSuppliers();
  loadList();
});
</script>

<style scoped>
.row-log-btn {
  margin-left: 8px;
}

.batch-preview {
  margin-top: 16px;
  font-size: 13px;
  color: #606266;
}

.batch-preview > p {
  margin: 0;
}

.import-result {
  margin-top: 16px;
  padding: 12px 16px;
  font-size: 13px;
  line-height: 1.7;
  background: #f7f9fc;
  border-radius: 6px;
}

.import-result p {
  margin: 0 0 6px;
}

.import-result ul {
  max-height: 160px;
  padding-left: 18px;
  margin: 0;
  overflow: auto;
  color: #f56c6c;
}

.import-result ul + ul {
  margin-top: 6px;
}

.import-result .import-warning {
  color: #e6a23c;
}
</style>
