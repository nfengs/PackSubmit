<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';

import {
  checkSupplierName,
  createSupplier,
  deleteSupplier,
  downloadSupplierTemplate,
  getSupplierLogs,
  getSupplierPage,
  importSuppliers,
  updateSupplier,
} from '@/api/admin';
import ImportDialog from '@/components/ImportDialog.vue';
import LogDrawer from '@/components/LogDrawer.vue';
import { formatDateTime } from '@/utils/format';

const loading = ref(false);
const rows = ref([]);
const total = ref(0);
const query = reactive({ page: 1, page_size: 20, keyword: '' });

const dialogVisible = ref(false);
const submitting = ref(false);
const editingId = ref('');
const nameWarning = ref('');
const form = reactive({ name: '', code: '', address: '', enabled: true });

const importVisible = ref(false);
const logsVisible = ref(false);
const logsLoading = ref(false);
const logs = ref([]);
const logsTitle = ref('');

async function load() {
  loading.value = true;

  try {
    const data = await getSupplierPage({ page: query.page, page_size: query.page_size, keyword: query.keyword || undefined });
    rows.value = data.list || [];
    total.value = data.total || 0;
  } finally {
    loading.value = false;
  }
}

onMounted(load);

function search() {
  query.page = 1;
  load();
}

function openCreate() {
  editingId.value = '';
  nameWarning.value = '';
  Object.assign(form, { name: '', code: '', address: '', enabled: true });
  dialogVisible.value = true;
}

function openEdit(row) {
  editingId.value = row.id;
  nameWarning.value = '';
  Object.assign(form, { name: row.name, code: row.code, address: row.address, enabled: row.status === 1 });
  dialogVisible.value = true;
}

async function checkName() {
  if (!form.name.trim()) return;

  const data = await checkSupplierName({ name: form.name.trim(), exclude_id: editingId.value || undefined });
  nameWarning.value = data.exists ? '供应商名称已存在' : '';
}

async function submit() {
  if (!form.name.trim()) {
    ElMessage.warning('请填写供应商名称');
    return;
  }

  submitting.value = true;

  try {
    const payload = {
      name: form.name.trim(),
      code: form.code,
      address: form.address,
      status: form.enabled ? 1 : 0,
    };

    if (editingId.value) {
      await updateSupplier(editingId.value, payload);
    } else {
      await createSupplier(payload);
    }

    ElMessage.success('保存成功');
    dialogVisible.value = false;
    load();
  } finally {
    submitting.value = false;
  }
}

async function remove(row) {
  try {
    await ElMessageBox.confirm(
      `确认删除供应商「${row.name}」？删除会同时清理其联系人与指定零件号（方案与账号占用时不允许删除）。`,
      '删除确认',
      { type: 'warning' },
    );
  } catch (e) {
    return;
  }

  await deleteSupplier(row.id);
  ElMessage.success('删除成功');
  load();
}

async function openLogs(row) {
  logsTitle.value = `供应商维护日志 · ${row.name}`;
  logsVisible.value = true;
  logsLoading.value = true;

  try {
    logs.value = (await getSupplierLogs(row.id)) || [];
  } finally {
    logsLoading.value = false;
  }
}
</script>

<template>
  <div class="page-card">
    <div class="page-toolbar">
      <el-input v-model="query.keyword" placeholder="供应商名称 / 代码" clearable style="width: 220px"
        @keyup.enter="search" />
      <el-button type="primary" @click="search">查询</el-button>
      <div class="spacer"></div>
      <el-button @click="importVisible = true">批量导入</el-button>
      <el-button type="primary" @click="openCreate">新增供应商</el-button>
    </div>

    <el-alert type="info" :closable="false" show-icon style="margin-bottom: 12px"
      title="供应商档案仅维护名称、代码、地址；联系人由供应商在「联系人维护」中自行维护。改名会同步方案归属与零件号清单。" />

    <el-table :data="rows" v-loading="loading" size="small">
      <el-table-column prop="name" label="供应商名称" min-width="160" show-overflow-tooltip />
      <el-table-column prop="code" label="供应商代码" width="140" />
      <el-table-column prop="address" label="地址" min-width="220" show-overflow-tooltip />
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag size="small" :type="row.status === 1 ? 'success' : 'info'" effect="plain">
            {{ row.status === 1 ? '启用' : '禁用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="更新时间" width="170">
        <template #default="{ row }">{{ formatDateTime(row.updated_at) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="170" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button link type="primary" @click="openLogs(row)">日志</el-button>
          <el-button link type="danger" @click="remove(row)">删除</el-button>
        </template>
      </el-table-column>
      <template #empty>
        <div class="empty">暂无供应商档案</div>
      </template>
    </el-table>

    <div style="display: flex; justify-content: flex-end; margin-top: 14px">
      <el-pagination v-model:current-page="query.page" v-model:page-size="query.page_size" :total="total"
        :page-sizes="[20, 50, 100]" layout="total, sizes, prev, pager, next" @current-change="load"
        @size-change="search" />
    </div>

    <el-dialog v-model="dialogVisible" :title="editingId ? '编辑供应商' : '新增供应商'" width="520px">
      <el-form label-width="100px">
        <el-form-item label="供应商名称" required>
          <el-input v-model="form.name" maxlength="100" @blur="checkName" />
          <div v-if="nameWarning" style="color: #c45656; font-size: 12px">{{ nameWarning }}</div>
        </el-form-item>
        <el-form-item label="供应商代码">
          <el-input v-model="form.code" maxlength="50" />
        </el-form-item>
        <el-form-item label="地址">
          <el-input v-model="form.address" maxlength="255" />
        </el-form-item>
        <el-form-item label="启用">
          <el-switch v-model="form.enabled" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submit">保存</el-button>
      </template>
    </el-dialog>

    <ImportDialog v-model="importVisible" title="批量导入供应商"
      tips="列顺序：供应商名称、供应商代码、供应商地址。名称已存在则更新代码与地址，不存在则新增。"
      template-name="供应商导入模板.xlsx" :template-loader="downloadSupplierTemplate" :uploader="importSuppliers"
      @success="load" />

    <LogDrawer v-model="logsVisible" :title="logsTitle" :logs="logs" :loading="logsLoading" />
  </div>
</template>
