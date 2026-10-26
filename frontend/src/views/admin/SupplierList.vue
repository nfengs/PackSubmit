<template>
  <div class="page-card">
    <div class="page-toolbar">
      <el-input
        v-model="query.keyword"
        placeholder="供应商名称 / 代码 / 联系人"
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
      <el-button type="primary" @click="openCreate">
        <el-icon><Plus /></el-icon>
        新增供应商
      </el-button>
    </div>

    <el-table v-loading="loading" :data="list" border stripe>
      <el-table-column prop="name" label="供应商名称" min-width="140" show-overflow-tooltip />
      <el-table-column prop="code" label="供应商代码" width="130" />
      <el-table-column prop="address" label="供应商地址" min-width="220" show-overflow-tooltip />
      <el-table-column prop="contact" label="联系人" width="110" />
      <el-table-column prop="phone" label="联系电话" width="140" />
      <el-table-column prop="email" label="电子邮箱" min-width="170" show-overflow-tooltip />
      <el-table-column prop="fax" label="传真" width="130" />
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
        title="供应商名称需与提交人账号的“部门/供应商”一致，提交人才能使用该供应商提交方案"
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
        <el-form-item label="联系人" prop="contact">
          <el-input v-model="form.contact" maxlength="50" placeholder="请输入联系人" />
        </el-form-item>
        <el-form-item label="联系电话" prop="phone">
          <el-input v-model="form.phone" maxlength="30" placeholder="请输入联系电话" />
        </el-form-item>
        <el-form-item label="电子邮箱" prop="email">
          <el-input v-model="form.email" maxlength="100" placeholder="请输入电子邮箱" />
        </el-form-item>
        <el-form-item label="传真" prop="fax">
          <el-input v-model="form.fax" maxlength="30" placeholder="请输入传真" />
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
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import {
  getSupplierList,
  createSupplier,
  updateSupplier,
  deleteSupplier,
  checkSupplierName,
  getSupplierLogs,
} from '@/api/admin';

const loading = ref(false);
const saving = ref(false);
const list = ref([]);
const total = ref(0);

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
  contact: '',
  phone: '',
  email: '',
  fax: '',
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
  email: [{ type: 'email', message: '邮箱格式不正确', trigger: 'blur' }],
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
    contact: '',
    phone: '',
    email: '',
    fax: '',
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
    contact: row.contact || '',
    phone: row.phone || '',
    email: row.email || '',
    fax: row.fax || '',
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
      contact: form.contact,
      phone: form.phone,
      email: form.email,
      fax: form.fax,
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
    `确定删除供应商「${row.name}」吗？已被方案使用或仍有提交人账号在用的供应商不允许删除。`,
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

onMounted(loadList);
</script>

<style scoped>
.row-log-btn {
  margin-left: 8px;
}
</style>
