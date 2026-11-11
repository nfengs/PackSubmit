<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';

import {
  checkCustomerName,
  createCustomer,
  deleteCustomer,
  getCustomerLogs,
  getCustomerPage,
  updateCustomer,
} from '@/api/admin';
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
const form = reactive({
  name: '',
  address: '',
  contact: '',
  phone: '',
  email: '',
  fax: '',
  enabled: true,
});

const logsVisible = ref(false);
const logsLoading = ref(false);
const logs = ref([]);
const logsTitle = ref('');

async function load() {
  loading.value = true;

  try {
    const data = await getCustomerPage({ page: query.page, page_size: query.page_size, keyword: query.keyword || undefined });
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
  Object.assign(form, { name: '', address: '', contact: '', phone: '', email: '', fax: '', enabled: true });
  dialogVisible.value = true;
}

function openEdit(row) {
  editingId.value = row.id;
  nameWarning.value = '';
  Object.assign(form, {
    name: row.name,
    address: row.address,
    contact: row.contact,
    phone: row.phone,
    email: row.email,
    fax: row.fax,
    enabled: row.status === 1,
  });
  dialogVisible.value = true;
}

async function checkName() {
  if (!form.name.trim()) return;

  const data = await checkCustomerName({ name: form.name.trim(), exclude_id: editingId.value || undefined });
  nameWarning.value = data.exists ? '客户名称已存在' : '';
}

async function submit() {
  if (!form.name.trim()) {
    ElMessage.warning('请填写客户名称');
    return;
  }

  submitting.value = true;

  try {
    const payload = {
      name: form.name.trim(),
      address: form.address,
      contact: form.contact,
      phone: form.phone,
      email: form.email,
      fax: form.fax,
      status: form.enabled ? 1 : 0,
    };

    if (editingId.value) {
      await updateCustomer(editingId.value, payload);
    } else {
      await createCustomer(payload);
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
    await ElMessageBox.confirm(`确认删除客户「${row.name}」？删除后不可恢复，历史方案快照不受影响。`, '删除确认', {
      type: 'warning',
    });
  } catch (e) {
    return;
  }

  await deleteCustomer(row.id);
  ElMessage.success('删除成功');
  load();
}

async function openLogs(row) {
  logsTitle.value = `客户维护日志 · ${row.name}`;
  logsVisible.value = true;
  logsLoading.value = true;

  try {
    logs.value = (await getCustomerLogs(row.id)) || [];
  } finally {
    logsLoading.value = false;
  }
}
</script>

<template>
  <div class="page-card">
    <div class="page-toolbar">
      <el-input v-model="query.keyword" placeholder="客户名称 / 联系人" clearable style="width: 220px"
        @keyup.enter="search" />
      <el-button type="primary" @click="search">查询</el-button>
      <div class="spacer"></div>
      <el-button type="primary" @click="openCreate">新增客户</el-button>
    </div>

    <el-table :data="rows" v-loading="loading" size="small">
      <el-table-column prop="name" label="客户名称" min-width="150" show-overflow-tooltip />
      <el-table-column prop="address" label="供货地址" min-width="200" show-overflow-tooltip />
      <el-table-column prop="contact" label="联系人" width="110" />
      <el-table-column prop="phone" label="电话" width="140" />
      <el-table-column prop="email" label="邮箱" min-width="170" show-overflow-tooltip />
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
        <div class="empty">暂无客户档案</div>
      </template>
    </el-table>

    <div style="display: flex; justify-content: flex-end; margin-top: 14px">
      <el-pagination v-model:current-page="query.page" v-model:page-size="query.page_size" :total="total"
        :page-sizes="[20, 50, 100]" layout="total, sizes, prev, pager, next" @current-change="load"
        @size-change="search" />
    </div>

    <el-dialog v-model="dialogVisible" :title="editingId ? '编辑客户' : '新增客户'" width="560px">
      <el-form label-width="100px">
        <el-form-item label="客户名称" required>
          <el-input v-model="form.name" maxlength="100" @blur="checkName" />
          <div v-if="nameWarning" style="color: #c45656; font-size: 12px">{{ nameWarning }}</div>
        </el-form-item>
        <el-form-item label="供货地址">
          <el-input v-model="form.address" maxlength="255" />
        </el-form-item>
        <el-form-item label="联系人">
          <el-input v-model="form.contact" maxlength="50" />
        </el-form-item>
        <el-form-item label="电话">
          <el-input v-model="form.phone" maxlength="30" />
        </el-form-item>
        <el-form-item label="邮箱">
          <el-input v-model="form.email" maxlength="100" />
        </el-form-item>
        <el-form-item label="传真">
          <el-input v-model="form.fax" maxlength="30" />
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

    <LogDrawer v-model="logsVisible" :title="logsTitle" :logs="logs" :loading="logsLoading" />
  </div>
</template>
