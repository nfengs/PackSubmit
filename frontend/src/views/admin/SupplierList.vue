<template>
  <div class="page-card">
    <div class="page-toolbar">
      <el-input
        v-model="query.keyword"
        placeholder="供应商名称 / 代码"
        clearable
        style="width: 240px"
        @keyup.enter="handleSearch"
      />
      <el-select v-model="query.status" placeholder="状态" clearable style="width: 120px">
        <el-option label="启用" :value="1" />
        <el-option label="禁用" :value="0" />
      </el-select>
      <el-button type="primary" @click="handleSearch">查询</el-button>
      <el-button @click="handleReset">重置</el-button>
      <div class="spacer" />
      <el-button @click="handleTemplate">下载模板</el-button>
      <el-button type="warning" plain @click="openImport">批量导入</el-button>
      <el-button type="primary" @click="openCreate">
        <el-icon><Plus /></el-icon>
        新增供应商
      </el-button>
    </div>

    <el-table v-loading="loading" :data="list" border stripe>
      <el-table-column prop="name" label="供应商名称" min-width="160" show-overflow-tooltip />
      <el-table-column prop="code" label="供应商代码" width="140" />
      <el-table-column prop="address" label="供应商地址" min-width="260" show-overflow-tooltip />
      <el-table-column label="状态" width="90" align="center">
        <template #default="{ row }">
          <el-tag size="small" :type="Number(row.status) === 1 ? 'success' : 'info'">
            {{ Number(row.status) === 1 ? '启用' : '禁用' }}
          </el-tag>
        </template>
      </el-table-column>
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
        <span class="text-muted">暂无供应商档案</span>
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

    <el-dialog
      v-model="formVisible"
      :title="form.id ? '编辑供应商' : '新增供应商'"
      width="560px"
      destroy-on-close
    >
      <el-alert
        type="info"
        :closable="false"
        show-icon
        title="供应商名称需与供应商账号的“部门/供应商”一致，供应商才能使用该供应商提交方案"
        style="margin-bottom: 16px"
      />
      <el-form ref="formRef" :model="form" :rules="rules" label-width="110px">
        <el-form-item label="供应商名称" prop="name">
          <el-input v-model="form.name" maxlength="100" placeholder="请输入供应商名称" />
        </el-form-item>
        <el-form-item label="供应商代码" prop="code">
          <el-input v-model="form.code" maxlength="50" placeholder="请输入供应商代码" />
        </el-form-item>
        <el-form-item label="供应商地址" prop="address">
          <el-input v-model="form.address" maxlength="255" placeholder="请输入供应商地址" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="logVisible" :title="logTitle" width="680px">
      <el-table v-loading="logLoading" :data="logs" border size="small" max-height="420">
        <el-table-column prop="create_time" label="操作时间" width="170" align="center" />
        <el-table-column label="操作人" width="110" align="center">
          <template #default="{ row }">{{ row.operator?.real_name || '-' }}</template>
        </el-table-column>
        <el-table-column prop="action_name" label="操作" width="120" align="center" />
        <el-table-column prop="content" label="内容" min-width="260" show-overflow-tooltip />
        <template #empty>
          <span class="text-muted">暂无日志</span>
        </template>
      </el-table>

      <template #footer>
        <el-button @click="logVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="importVisible" title="批量导入供应商" width="560px" destroy-on-close>
      <el-alert
        type="info"
        :closable="false"
        show-icon
        title="Excel 列顺序：供应商名称、供应商代码、供应商地址（首行表头自动识别）。名称已存在则更新代码与地址。"
        style="margin-bottom: 16px"
      />
      <el-upload
        ref="importRef"
        drag
        action="#"
        :auto-upload="false"
        :limit="1"
        accept=".xlsx,.xls"
        :on-change="handleImportFileChange"
        :on-remove="handleImportFileRemove"
      >
        <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
        <div class="el-upload__text">将 Excel 拖到此处，或<em>点击选择文件</em></div>
        <template #tip>
          <div class="el-upload__tip">仅支持 .xlsx / .xls，文件不超过 10MB</div>
        </template>
      </el-upload>

      <div v-if="importResult" class="import-result">
        <p>
          导入完成：新增 <strong>{{ importResult.inserted }}</strong> 条，更新
          <strong>{{ importResult.updated }}</strong> 条，跳过
          <strong>{{ importResult.skipped }}</strong> 条
        </p>
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
import { Plus, UploadFilled } from '@element-plus/icons-vue';
import {
  getSupplierList,
  createSupplier,
  updateSupplier,
  deleteSupplier,
  checkSupplierName,
  getSupplierLogs,
  importSuppliers,
  downloadSupplierTemplate,
} from '@/api/admin';
import { resolveFileName } from '@/api/attachment';

const loading = ref(false);
const saving = ref(false);
const list = ref([]);
const total = ref(0);

/** 批量导入 */
const importVisible = ref(false);
const importRef = ref(null);
const importFile = ref(null);
const importing = ref(false);
const importResult = ref(null);

const query = reactive({
  keyword: '',
  status: '',
  page: 1,
  page_size: 10,
});

/** 维护日志 */
const logVisible = ref(false);
const logLoading = ref(false);
const logs = ref([]);
const logTarget = ref({});
const logTitle = computed(() => `供应商日志 · ${logTarget.value.name || ''}`);

const formVisible = ref(false);
const formRef = ref(null);
const form = reactive({
  id: 0,
  name: '',
  code: '',
  address: '',
  status: 1,
});

/**
 * 供应商名称查重（忽略首尾空格与大小写）
 */
async function validateNameUnique(rule, value, callback) {
  const name = String(value || '').trim();
  if (!name) {
    callback();
    return;
  }

  try {
    const data = await checkSupplierName(name, form.id || 0);
    if (data?.exists) {
      callback(new Error('供应商名称已存在'));
      return;
    }
  } catch (e) {
    // 查重接口异常时不阻塞保存，交给后端兜底
  }

  callback();
}

const rules = {
  name: [
    { required: true, message: '请输入供应商名称', trigger: 'blur' },
    { validator: validateNameUnique, trigger: 'blur' },
  ],
};

async function loadList() {
  loading.value = true;

  try {
    const data = await getSupplierList({ ...query });
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
  query.status = '';
  query.page = 1;
  loadList();
}

function openCreate() {
  Object.assign(form, {
    id: 0,
    name: '',
    code: '',
    address: '',
    status: 1,
  });
  formVisible.value = true;
}

function openEdit(row) {
  Object.assign(form, {
    id: row.id,
    name: row.name,
    code: row.code || '',
    address: row.address || '',
    status: Number(row.status),
  });
  formVisible.value = true;
}

async function handleSave() {
  const valid = await formRef.value.validate().catch(() => false);
  if (!valid) return;

  saving.value = true;

  try {
    const payload = {
      name: String(form.name || '').trim(),
      code: form.code,
      address: form.address,
      status: Number(form.status),
    };

    if (form.id) {
      await updateSupplier(form.id, payload);
      ElMessage.success('供应商更新成功');
    } else {
      await createSupplier(payload);
      ElMessage.success('供应商新增成功');
    }

    formVisible.value = false;
    loadList();
  } finally {
    saving.value = false;
  }
}

async function handleDelete(row) {
  await ElMessageBox.confirm(
    `确定删除供应商「${row.name}」吗？已被方案使用或仍有供应商账号在用的供应商不允许删除。`,
    '删除供应商',
    { type: 'warning' },
  );
  await deleteSupplier(row.id);
  ElMessage.success('供应商已删除');
  loadList();
}

/**
 * 查询供应商维护日志
 * @param {object} row
 */
async function openLogs(row) {
  logTarget.value = row;
  logs.value = [];
  logVisible.value = true;
  logLoading.value = true;

  try {
    const data = await getSupplierLogs(row.id);
    logs.value = data || [];
  } finally {
    logLoading.value = false;
  }
}

function openImport() {
  importResult.value = null;
  importFile.value = null;
  importRef.value?.clearFiles();
  importVisible.value = true;
}

function handleImportFileChange(file) {
  importFile.value = file.raw;
}

function handleImportFileRemove() {
  importFile.value = null;
}

async function handleImport() {
  if (!importFile.value) {
    ElMessage.warning('请选择要导入的 Excel 文件');
    return;
  }

  importing.value = true;

  try {
    const formData = new FormData();
    formData.append('file', importFile.value);

    const data = await importSuppliers(formData);
    importResult.value = data;

    ElMessage.success(`导入完成：新增 ${data.inserted} 条，更新 ${data.updated} 条，跳过 ${data.skipped} 条`);
    importFile.value = null;
    importRef.value?.clearFiles();
    loadList();
  } finally {
    importing.value = false;
  }
}

async function handleTemplate() {
  const response = await downloadSupplierTemplate();
  const name = resolveFileName(response, '供应商导入模板.xlsx');
  const url = URL.createObjectURL(response.data);
  const link = document.createElement('a');
  link.href = url;
  link.download = name;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

onMounted(loadList);
</script>

<style scoped>
.row-log-btn {
  margin-left: 8px;
}

.import-result {
  margin-top: 14px;
  padding: 10px 12px;
  background: #f7f8fa;
  border-radius: 6px;
  font-size: 13px;
}

.import-result ul {
  margin: 8px 0 0;
  padding-left: 18px;
  color: #e6a23c;
}
</style>
