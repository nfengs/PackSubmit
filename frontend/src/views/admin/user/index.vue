<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';

import {
  createUser,
  downloadUserTemplate,
  getUserLogs,
  getUsers,
  importUsers,
  resetUserPassword,
  updateUser,
  updateUserStatus,
} from '@/api/admin';
import { getSuppliers } from '@/api/masterdata';
import ImportDialog from '@/components/ImportDialog.vue';
import LogDrawer from '@/components/LogDrawer.vue';
import { ROLE, ROLE_TEXT } from '@/utils/constants';
import { formatDateTime } from '@/utils/format';

const loading = ref(false);
const rows = ref([]);
const total = ref(0);
const suppliers = ref([]);

const query = reactive({ page: 1, page_size: 20, keyword: '', role: '', status: '' });

const dialogVisible = ref(false);
const submitting = ref(false);
const editingId = ref('');
const form = reactive({
  username: '',
  password: '',
  real_name: '',
  role: ROLE.ADMIN,
  supplier_id: '',
  dept: '',
  phone: '',
});

const resetVisible = ref(false);
const resetTarget = ref(null);
const resetForm = reactive({ password: '' });

const importVisible = ref(false);
const logsVisible = ref(false);
const logsLoading = ref(false);
const logs = ref([]);
const logsTitle = ref('');

async function load() {
  loading.value = true;

  try {
    const params = { page: query.page, page_size: query.page_size };
    if (query.keyword) params.keyword = query.keyword;
    if (query.role !== '') params.role = query.role;
    if (query.status !== '') params.status = query.status;

    const data = await getUsers(params);
    rows.value = data.list || [];
    total.value = data.total || 0;
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  suppliers.value = (await getSuppliers()) || [];
  load();
});

function search() {
  query.page = 1;
  load();
}

function openCreate() {
  editingId.value = '';
  Object.assign(form, {
    username: '',
    password: '',
    real_name: '',
    role: ROLE.SUPPLIER,
    supplier_id: '',
    dept: '',
    phone: '',
  });
  dialogVisible.value = true;
}

function openEdit(row) {
  editingId.value = row.id;
  Object.assign(form, {
    username: row.username,
    password: '',
    real_name: row.real_name,
    role: row.role,
    supplier_id: row.supplier_id || '',
    dept: row.dept || '',
    phone: row.phone || '',
  });
  dialogVisible.value = true;
}

function onRoleChange(role) {
  if (role !== ROLE.SUPPLIER) {
    form.supplier_id = '';
    form.dept = '';
  }
}

function onSupplierChange(supplierId) {
  const supplier = suppliers.value.find((item) => item.id === supplierId);
  form.dept = supplier ? supplier.name : '';
}

async function submit() {
  if (!form.username.trim()) {
    ElMessage.warning('请填写登录账号');
    return;
  }

  if (!editingId.value && !form.password) {
    ElMessage.warning('请填写初始密码');
    return;
  }

  if (form.role === ROLE.SUPPLIER && !form.supplier_id) {
    ElMessage.warning('供应商角色必须选择所属供应商');
    return;
  }

  submitting.value = true;

  try {
    const payload = {
      username: form.username.trim(),
      real_name: form.real_name,
      role: form.role,
      supplier_id: form.supplier_id,
      dept: form.dept,
      phone: form.phone,
      password: form.password,
    };

    if (editingId.value) {
      await updateUser(editingId.value, payload);
    } else {
      await createUser(payload);
    }

    ElMessage.success('保存成功');
    dialogVisible.value = false;
    load();
  } finally {
    submitting.value = false;
  }
}

async function toggleStatus(row, enabled) {
  const status = enabled ? 1 : 0;

  try {
    await ElMessageBox.confirm(
      `确认${enabled ? '启用' : '禁用'}账号「${row.username}」？${enabled ? '' : '禁用后该账号已登录状态立即失效。'}`,
      '状态变更',
      { type: 'warning' },
    );
  } catch (e) {
    return;
  }

  await updateUserStatus(row.id, status);
  ElMessage.success('状态已更新');
  load();
}

function openReset(row) {
  resetTarget.value = row;
  resetForm.password = '';
  resetVisible.value = true;
}

async function submitReset() {
  if (!resetForm.password) {
    ElMessage.warning('请填写新密码');
    return;
  }

  await resetUserPassword(resetTarget.value.id, resetForm.password);
  ElMessage.success('密码已重置，该账号旧登录状态已失效');
  resetVisible.value = false;
}

async function openLogs(row) {
  logsTitle.value = `用户维护日志 · ${row.username}`;
  logsVisible.value = true;
  logsLoading.value = true;

  try {
    logs.value = (await getUserLogs(row.id)) || [];
  } finally {
    logsLoading.value = false;
  }
}
</script>

<template>
  <div class="page-card">
    <div class="page-toolbar">
      <el-input v-model="query.keyword" placeholder="账号 / 姓名 / 部门" clearable style="width: 220px"
        @keyup.enter="search" />
      <el-select v-model="query.role" placeholder="角色" clearable style="width: 130px">
        <el-option v-for="(label, value) in ROLE_TEXT" :key="value" :label="label" :value="String(value)" />
      </el-select>
      <el-select v-model="query.status" placeholder="状态" clearable style="width: 120px">
        <el-option label="启用" value="1" />
        <el-option label="禁用" value="0" />
      </el-select>
      <el-button type="primary" @click="search">查询</el-button>
      <div class="spacer"></div>
      <el-button @click="importVisible = true">批量导入</el-button>
      <el-button type="primary" @click="openCreate">新增用户</el-button>
    </div>

    <el-table :data="rows" v-loading="loading" size="small">
      <el-table-column prop="username" label="登录账号" width="140" />
      <el-table-column prop="real_name" label="姓名" width="110" />
      <el-table-column label="角色" width="100">
        <template #default="{ row }">
          <el-tag size="small" effect="plain">{{ ROLE_TEXT[row.role] || '-' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="部门/供应商" min-width="160" show-overflow-tooltip>
        <template #default="{ row }">{{ row.supplier?.name || row.dept || '-' }}</template>
      </el-table-column>
      <el-table-column prop="phone" label="联系电话" width="130" />
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-switch :model-value="row.status === 1" @change="(value) => toggleStatus(row, value)" />
        </template>
      </el-table-column>
      <el-table-column label="创建时间" width="170">
        <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button link type="primary" @click="openReset(row)">重置密码</el-button>
          <el-button link type="primary" @click="openLogs(row)">日志</el-button>
        </template>
      </el-table-column>
      <template #empty>
        <div class="empty">暂无用户</div>
      </template>
    </el-table>

    <div style="display: flex; justify-content: flex-end; margin-top: 14px">
      <el-pagination v-model:current-page="query.page" v-model:page-size="query.page_size" :total="total"
        :page-sizes="[20, 50, 100]" layout="total, sizes, prev, pager, next" @current-change="load"
        @size-change="search" />
    </div>

    <el-dialog v-model="dialogVisible" :title="editingId ? '编辑用户' : '新增用户'" width="520px">
      <el-form label-width="100px">
        <el-form-item label="登录账号" required>
          <el-input v-model="form.username" :disabled="!!editingId" maxlength="50" />
        </el-form-item>
        <el-form-item v-if="!editingId" label="初始密码" required>
          <el-input v-model="form.password" type="password" show-password placeholder="8-32 位，含字母和数字" />
        </el-form-item>
        <el-form-item label="姓名">
          <el-input v-model="form.real_name" maxlength="50" />
        </el-form-item>
        <el-form-item label="角色" required>
          <el-select v-model="form.role" style="width: 100%" @change="onRoleChange">
            <el-option v-for="(label, value) in ROLE_TEXT" :key="value" :label="label" :value="Number(value)" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="form.role === ROLE.SUPPLIER" label="所属供应商" required>
          <el-select v-model="form.supplier_id" filterable style="width: 100%" @change="onSupplierChange">
            <el-option v-for="item in suppliers" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item v-else label="部门">
          <el-input v-model="form.dept" maxlength="100" />
        </el-form-item>
        <el-form-item label="联系电话">
          <el-input v-model="form.phone" maxlength="30" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submit">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="resetVisible" title="重置密码" width="420px">
      <el-form label-width="90px">
        <el-form-item label="账号">
          <el-input :model-value="resetTarget?.username" disabled />
        </el-form-item>
        <el-form-item label="新密码">
          <el-input v-model="resetForm.password" type="password" show-password placeholder="8-32 位，含字母和数字" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="resetVisible = false">取消</el-button>
        <el-button type="primary" @click="submitReset">确认重置</el-button>
      </template>
    </el-dialog>

    <ImportDialog v-model="importVisible" title="批量导入用户"
      tips="列顺序：登录账号、姓名、角色、部门/供应商、联系电话、初始密码。角色可填 1/2/3/4 或中文；账号已存在则更新，新账号必须填写初始密码。"
      template-name="用户导入模板.xlsx" :template-loader="downloadUserTemplate" :uploader="importUsers"
      @success="load" />

    <LogDrawer v-model="logsVisible" :title="logsTitle" :logs="logs" :loading="logsLoading" />
  </div>
</template>
