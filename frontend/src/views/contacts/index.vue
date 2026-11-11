<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';

import { createContact, deleteContact, getContacts, updateContact } from '@/api/contact';
import { getSuppliers } from '@/api/masterdata';
import { useUserStore } from '@/store/user';
import { formatDateTime } from '@/utils/format';

const userStore = useUserStore();

const loading = ref(false);
const rows = ref([]);
const suppliers = ref([]);
const supplierFilter = ref('');
const dialogVisible = ref(false);
const submitting = ref(false);
const editingId = ref('');

const form = reactive({
  supplier_id: '',
  name: '',
  phone: '',
  email: '',
  fax: '',
  is_default: false,
});

async function load() {
  loading.value = true;

  try {
    rows.value = (await getContacts(supplierFilter.value ? { supplier_id: supplierFilter.value } : {})) || [];
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  if (userStore.isAdmin) {
    suppliers.value = (await getSuppliers()) || [];
  }
  load();
});

function openCreate() {
  editingId.value = '';
  Object.assign(form, {
    supplier_id: supplierFilter.value || '',
    name: '',
    phone: '',
    email: '',
    fax: '',
    is_default: false,
  });
  dialogVisible.value = true;
}

function openEdit(row) {
  editingId.value = row.id;
  Object.assign(form, {
    supplier_id: row.supplier_id,
    name: row.name,
    phone: row.phone,
    email: row.email,
    fax: row.fax,
    is_default: row.is_default === 1,
  });
  dialogVisible.value = true;
}

async function submit() {
  if (!form.name.trim()) {
    ElMessage.warning('请填写联系人姓名');
    return;
  }

  if (userStore.isAdmin && !form.supplier_id) {
    ElMessage.warning('请选择供应商');
    return;
  }

  submitting.value = true;

  const payload = {
    supplier_id: form.supplier_id,
    name: form.name.trim(),
    phone: form.phone,
    email: form.email,
    fax: form.fax,
    is_default: form.is_default ? 1 : 0,
  };

  try {
    if (editingId.value) {
      await updateContact(editingId.value, payload);
    } else {
      await createContact(payload);
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
    await ElMessageBox.confirm(`确认删除联系人「${row.name}」？`, '删除确认', { type: 'warning' });
  } catch (e) {
    return;
  }

  await deleteContact(row.id);
  ElMessage.success('删除成功');
  load();
}
</script>

<template>
  <div class="page-card">
    <div class="page-toolbar">
      <h3 class="section-title" style="margin: 0">供应商联系人</h3>
      <div class="spacer"></div>
      <el-select v-if="userStore.isAdmin" v-model="supplierFilter" placeholder="按供应商筛选" clearable filterable
        style="width: 200px" @change="load">
        <el-option v-for="item in suppliers" :key="item.id" :label="item.name" :value="item.id" />
      </el-select>
      <el-button type="primary" @click="openCreate">新增联系人</el-button>
    </div>

    <el-alert type="info" :closable="false" show-icon style="margin-bottom: 12px"
      title="同一供应商的账号共享联系人清单；方案选择联系人后会带出电话 / 邮箱 / 传真并保存为快照。" />

    <el-table :data="rows" v-loading="loading" size="small">
      <el-table-column v-if="userStore.isAdmin" prop="supplier_name" label="供应商" width="160" />
      <el-table-column prop="name" label="联系人" width="120" />
      <el-table-column prop="phone" label="电话" width="150" />
      <el-table-column prop="email" label="邮箱" min-width="180" show-overflow-tooltip />
      <el-table-column prop="fax" label="传真" width="150" />
      <el-table-column label="默认联系人" width="110">
        <template #default="{ row }">
          <el-tag v-if="row.is_default === 1" size="small" type="success" effect="plain">默认</el-tag>
          <span v-else class="muted">-</span>
        </template>
      </el-table-column>
      <el-table-column label="更新时间" width="170">
        <template #default="{ row }">{{ formatDateTime(row.updated_at) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="130">
        <template #default="{ row }">
          <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button link type="danger" @click="remove(row)">删除</el-button>
        </template>
      </el-table-column>
      <template #empty>
        <div class="empty">暂无联系人，请先新增</div>
      </template>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="editingId ? '编辑联系人' : '新增联系人'" width="460px">
      <el-form label-width="90px">
        <el-form-item v-if="userStore.isAdmin" label="供应商" required>
          <el-select v-model="form.supplier_id" filterable placeholder="请选择供应商" style="width: 100%">
            <el-option v-for="item in suppliers" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="联系人" required>
          <el-input v-model="form.name" maxlength="50" />
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
        <el-form-item label="设为默认">
          <el-switch v-model="form.is_default" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>
