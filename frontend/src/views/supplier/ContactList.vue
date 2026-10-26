<template>
  <div class="page-card">
    <h3 class="page-title">供应商联系人</h3>
    <p class="page-tip">
      维护本供应商的联系人信息（电话 / 邮箱 / 传真）。方案表单填写「供应商资料」时从这里选择联系人，选中后自动带出电话、邮箱与传真。
    </p>

    <div class="page-toolbar">
      <el-select
        v-if="isAdmin"
        v-model="query.supplier_id"
        placeholder="选择供应商"
        clearable
        filterable
        style="width: 220px"
        @change="loadList"
      >
        <el-option v-for="item in suppliers" :key="item.id" :label="item.name" :value="item.id" />
      </el-select>
      <el-input
        v-model="query.keyword"
        placeholder="联系人 / 电话 / 邮箱"
        clearable
        style="width: 220px"
        @keyup.enter="loadList"
      />
      <el-button type="primary" @click="loadList">查询</el-button>
      <el-button @click="handleReset">重置</el-button>
      <div class="spacer" />
      <el-button type="primary" :disabled="isAdmin && !query.supplier_id" @click="openCreate">
        <el-icon><Plus /></el-icon>
        新增联系人
      </el-button>
    </div>

    <el-alert
      v-if="isAdmin && !query.supplier_id"
      type="info"
      :closable="false"
      show-icon
      title="请先选择供应商再新增联系人；不选择时列表展示全部供应商的联系人"
      style="margin-bottom: 12px"
    />
    <el-alert
      v-if="!isAdmin && !supplier"
      type="warning"
      :closable="false"
      show-icon
      title="当前账号尚未关联供应商档案，请联系管理员设置所属供应商"
      style="margin-bottom: 12px"
    />

    <el-table v-loading="loading" :data="list" border stripe>
      <el-table-column v-if="isAdmin" label="供应商" width="180" show-overflow-tooltip>
        <template #default="{ row }">{{ row.supplier?.name || '-' }}</template>
      </el-table-column>
      <el-table-column prop="name" label="联系人" width="140" show-overflow-tooltip />
      <el-table-column prop="phone" label="电话" width="160" show-overflow-tooltip />
      <el-table-column prop="email" label="邮箱" min-width="200" show-overflow-tooltip />
      <el-table-column prop="fax" label="传真" width="150" show-overflow-tooltip />
      <el-table-column label="默认" width="90" align="center">
        <template #default="{ row }">
          <el-tag v-if="Number(row.is_default) === 1" type="success" size="small">默认</el-tag>
          <span v-else class="text-muted">-</span>
        </template>
      </el-table-column>
      <el-table-column prop="update_time" label="更新时间" width="170" align="center" />
      <el-table-column label="操作" width="140" align="center" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
      <template #empty>
        <span class="text-muted">暂无联系人，请点击「新增联系人」添加</span>
      </template>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="editingId ? '编辑联系人' : '新增联系人'" width="520px">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
        <el-form-item v-if="isAdmin" label="供应商" prop="supplier_id">
          <el-select v-model="form.supplier_id" placeholder="请选择供应商" filterable style="width: 100%">
            <el-option v-for="item in suppliers" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="联系人" prop="name">
          <el-input v-model="form.name" maxlength="50" placeholder="请输入联系人姓名" />
        </el-form-item>
        <el-form-item label="电话" prop="phone">
          <el-input v-model="form.phone" maxlength="30" placeholder="请输入联系电话" />
        </el-form-item>
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="form.email" maxlength="100" placeholder="请输入电子邮箱" />
        </el-form-item>
        <el-form-item label="传真" prop="fax">
          <el-input v-model="form.fax" maxlength="30" placeholder="请输入传真" />
        </el-form-item>
        <el-form-item label="默认联系人">
          <el-switch v-model="form.is_default" :active-value="1" :inactive-value="0" />
          <span class="form-tip-inline">方案表单选择供应商时优先带出默认联系人</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import { useUserStore } from '@/store/user';
import { getSuppliers } from '@/api/masterData';
import {
  createSupplierContact,
  deleteSupplierContact,
  getSupplierContacts,
  updateSupplierContact,
} from '@/api/supplierContact';

const userStore = useUserStore();
const isAdmin = computed(() => userStore.isAdmin);

const loading = ref(false);
const saving = ref(false);
const list = ref([]);
const suppliers = ref([]);
const supplier = ref(null);
const editingId = ref(0);
const dialogVisible = ref(false);
const formRef = ref(null);

const query = reactive({
  supplier_id: '',
  keyword: '',
});

const form = reactive({
  supplier_id: '',
  name: '',
  phone: '',
  email: '',
  fax: '',
  is_default: 0,
});

const rules = {
  supplier_id: [{ required: true, message: '请选择供应商', trigger: 'change' }],
  name: [{ required: true, message: '请输入联系人姓名', trigger: 'blur' }],
  email: [{ type: 'email', message: '邮箱格式不正确', trigger: 'blur' }],
};

async function loadSuppliers() {
  if (!isAdmin.value) return;

  const data = await getSuppliers();
  suppliers.value = data || [];
}

async function loadList() {
  loading.value = true;

  try {
    const params = { keyword: query.keyword };

    if (isAdmin.value) {
      if (query.supplier_id) params.supplier_id = query.supplier_id;
    }

    const data = await getSupplierContacts(params);
    list.value = data.list || [];
    supplier.value = data.supplier || null;
  } finally {
    loading.value = false;
  }
}

function handleReset() {
  query.supplier_id = '';
  query.keyword = '';
  loadList();
}

function resetForm() {
  form.supplier_id = query.supplier_id || '';
  form.name = '';
  form.phone = '';
  form.email = '';
  form.fax = '';
  form.is_default = 0;
}

function openCreate() {
  editingId.value = 0;
  resetForm();
  dialogVisible.value = true;
  formRef.value?.clearValidate();
}

function openEdit(row) {
  editingId.value = row.id;
  form.supplier_id = row.supplier_id;
  form.name = row.name || '';
  form.phone = row.phone || '';
  form.email = row.email || '';
  form.fax = row.fax || '';
  form.is_default = Number(row.is_default) === 1 ? 1 : 0;
  dialogVisible.value = true;
  formRef.value?.clearValidate();
}

async function handleSave() {
  try {
    await formRef.value.validate();
  } catch {
    return;
  }

  saving.value = true;

  try {
    const payload = {
      name: form.name,
      phone: form.phone,
      email: form.email,
      fax: form.fax,
      is_default: form.is_default,
    };

    if (isAdmin.value) payload.supplier_id = form.supplier_id;

    if (editingId.value) {
      await updateSupplierContact(editingId.value, payload);
      ElMessage.success('联系人已更新');
    } else {
      await createSupplierContact(payload);
      ElMessage.success('联系人已新增');
    }

    dialogVisible.value = false;
    await loadList();
  } finally {
    saving.value = false;
  }
}

/**
 * 删除联系人
 * @param {object} row
 */
async function handleDelete(row) {
  await ElMessageBox.confirm(
    `确定删除联系人「${row.name}」吗？已保存的历史方案仍保留当时的联系人快照。`,
    '删除联系人',
    { type: 'warning' },
  );
  await deleteSupplierContact(row.id);
  ElMessage.success('联系人已删除');
  await loadList();
}

onMounted(async () => {
  await loadSuppliers();
  await loadList();
});
</script>

<style scoped>
.page-tip {
  margin: -8px 0 16px;
  font-size: 13px;
  color: #909399;
}

.form-tip-inline {
  margin-left: 10px;
  font-size: 12px;
  color: #909399;
}
</style>
