<template>
  <div class="page-card">
    <div class="page-toolbar">
      <el-input
        v-model="query.keyword"
        placeholder="账号 / 姓名 / 部门"
        clearable
        style="width: 200px"
        @keyup.enter="handleSearch"
      />
      <el-select v-model="query.role" placeholder="角色" clearable style="width: 130px">
        <el-option v-for="item in roleOptions" :key="item.value" :label="item.label" :value="item.value" />
      </el-select>
      <el-select v-model="query.status" placeholder="状态" clearable style="width: 120px">
        <el-option label="启用" :value="1" />
        <el-option label="禁用" :value="0" />
      </el-select>
      <el-button type="primary" @click="handleSearch">查询</el-button>
      <el-button @click="handleReset">重置</el-button>
      <div class="spacer" />
      <el-button type="primary" @click="openCreate">
        <el-icon><Plus /></el-icon>
        新增用户
      </el-button>
    </div>

    <el-table v-loading="loading" :data="list" border stripe>
      <el-table-column prop="username" label="登录账号" width="140" />
      <el-table-column prop="real_name" label="姓名" width="120" />
      <el-table-column label="角色" width="110" align="center">
        <template #default="{ row }">
          <el-tag size="small" :type="row.role === 3 ? 'danger' : row.role === 2 ? 'warning' : 'info'">
            {{ roleText(row.role) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="dept" label="部门/供应商" min-width="150" show-overflow-tooltip />
      <el-table-column prop="phone" label="联系电话" width="130" />
      <el-table-column label="状态" width="100" align="center">
        <template #default="{ row }">
          <el-switch
            :model-value="row.status === 1"
            :disabled="row.id === userStore.user?.id"
            @change="(value) => handleStatusChange(row, value)"
          />
        </template>
      </el-table-column>
      <el-table-column prop="create_time" label="创建时间" width="170" align="center" />
      <el-table-column label="更新时间" width="230" align="center">
        <template #default="{ row }">
          <span>{{ row.update_time || '-' }}</span>
          <el-button link type="primary" class="row-log-btn" @click="openLogs(row)">日志</el-button>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="190" fixed="right" align="center">
        <template #default="{ row }">
          <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button link type="warning" @click="openReset(row)">重置密码</el-button>
        </template>
      </el-table-column>
      <template #empty>
        <span class="text-muted">暂无用户</span>
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

    <!-- 新增/编辑 -->
    <el-dialog
      v-model="formVisible"
      :title="form.id ? '编辑用户' : '新增用户'"
      width="520px"
      destroy-on-close
    >
      <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px">
        <el-form-item label="登录账号" prop="username">
          <el-input v-model="form.username" :disabled="!!form.id" maxlength="50" />
        </el-form-item>
        <el-form-item v-if="!form.id" label="初始密码" prop="password">
          <el-input v-model="form.password" type="password" show-password maxlength="32" />
        </el-form-item>
        <el-form-item label="姓名" prop="real_name">
          <el-input v-model="form.real_name" maxlength="50" />
        </el-form-item>
        <el-form-item label="角色" prop="role">
          <el-select v-model="form.role" style="width: 100%" @change="handleRoleChange">
            <el-option
              v-for="item in roleOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="部门/供应商" prop="dept">
          <el-select
            v-if="Number(form.role) === 1"
            v-model="form.dept"
            placeholder="请选择供应商"
            filterable
            style="width: 100%"
          >
            <el-option
              v-for="item in suppliers"
              :key="item.id"
              :label="item.code ? `${item.name}（${item.code}）` : item.name"
              :value="item.name"
            />
          </el-select>
          <el-input v-else v-model="form.dept" maxlength="100" placeholder="请输入部门" />
        </el-form-item>
        <el-form-item label="联系电话">
          <el-input v-model="form.phone" maxlength="30" />
        </el-form-item>
        <el-form-item v-if="form.id" label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>

    <!-- 重置密码 -->
    <el-dialog v-model="resetVisible" title="重置密码" width="420px" destroy-on-close>
      <el-form ref="resetFormRef" :model="resetForm" :rules="resetRules" label-width="90px">
        <el-form-item label="登录账号">
          <el-input :model-value="resetForm.username" disabled />
        </el-form-item>
        <el-form-item label="新密码" prop="password">
          <el-input v-model="resetForm.password" type="password" show-password maxlength="32" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="resetVisible = false">取消</el-button>
        <el-button type="primary" :loading="resetting" @click="handleResetPassword">确定</el-button>
      </template>
    </el-dialog>

    <!-- 维护日志 -->
    <el-dialog v-model="logVisible" :title="logTitle" width="680px">
      <el-table v-loading="logLoading" :data="logs" border size="small" max-height="420">
        <el-table-column prop="create_time" label="操作时间" width="170" align="center" />
        <el-table-column label="操作人" width="110" align="center">
          <template #default="{ row }">{{ row.operator?.real_name || '-' }}</template>
        </el-table-column>
        <el-table-column prop="action_name" label="操作" width="130" align="center" />
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
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import {
  getUserList,
  createUser,
  updateUser,
  updateUserStatus,
  resetUserPassword,
  getUserLogs,
} from '@/api/admin';
import { getSuppliers } from '@/api/masterData';
import { useUserStore } from '@/store/user';
import { ROLE_OPTIONS, ROLE_TEXT } from '@/utils/constants';

const userStore = useUserStore();
const roleOptions = ROLE_OPTIONS;

const suppliers = ref([]);
const loading = ref(false);
const saving = ref(false);
const resetting = ref(false);
const list = ref([]);
const total = ref(0);

const query = reactive({
  keyword: '',
  role: '',
  status: '',
  page: 1,
  page_size: 10,
});

const formVisible = ref(false);
const formRef = ref(null);
const form = reactive({
  id: 0,
  username: '',
  password: '',
  real_name: '',
  role: 1,
  dept: '',
  phone: '',
  status: 1,
});

const formRules = computed(() => ({
  username: [{ required: !form.id, message: '请输入登录账号', trigger: 'blur' }],
  password: [
    {
      required: !form.id,
      message: '请输入初始密码',
      trigger: 'blur',
    },
    { min: 6, message: '密码长度不能少于 6 位', trigger: 'blur' },
  ],
  real_name: [{ required: true, message: '请输入姓名', trigger: 'blur' }],
  role: [{ required: true, message: '请选择角色', trigger: 'change' }],
  dept: [
    {
      validator: (rule, value, callback) => {
        if (Number(form.role) === 1 && !value) {
          callback(new Error('请选择供应商'));
          return;
        }
        callback();
      },
      trigger: 'change',
    },
  ],
}));

const resetVisible = ref(false);
const resetFormRef = ref(null);
const resetForm = reactive({ id: 0, username: '', password: '' });
const resetRules = {
  password: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 6, message: '密码长度不能少于 6 位', trigger: 'blur' },
  ],
};

const roleText = (role) => ROLE_TEXT[Number(role)] || '-';

/** 维护日志 */
const logVisible = ref(false);
const logLoading = ref(false);
const logs = ref([]);
const logTarget = ref({});
const logTitle = computed(() => `用户日志 · ${logTarget.value.username || ''}`);

async function loadList() {
  loading.value = true;

  try {
    const data = await getUserList({ ...query });
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
  query.role = '';
  query.status = '';
  query.page = 1;
  loadList();
}

/**
 * 切换角色：清空与新角色不匹配的部门/供应商
 * @param {number} role
 */
function handleRoleChange(role) {
  const isSubmitter = Number(role) === 1;
  const isKnownSupplier = suppliers.value.some((item) => item.name === form.dept);

  if ((isSubmitter && !isKnownSupplier) || (!isSubmitter && isKnownSupplier)) {
    form.dept = '';
  }

  formRef.value?.clearValidate('dept');
}

function openCreate() {
  Object.assign(form, {
    id: 0,
    username: '',
    password: '',
    real_name: '',
    role: 1,
    dept: '',
    phone: '',
    status: 1,
  });
  formVisible.value = true;
}

function openEdit(row) {
  Object.assign(form, {
    id: row.id,
    username: row.username,
    password: '',
    real_name: row.real_name,
    role: Number(row.role),
    dept: row.dept,
    phone: row.phone,
    status: Number(row.status),
  });
  formVisible.value = true;
}

async function handleSave() {
  const valid = await formRef.value.validate().catch(() => false);
  if (!valid) return;

  saving.value = true;

  try {
    if (form.id) {
      await updateUser(form.id, {
        real_name: form.real_name,
        role: Number(form.role),
        dept: form.dept,
        phone: form.phone,
        status: Number(form.status),
      });
      ElMessage.success('用户更新成功');
    } else {
      await createUser({
        username: form.username,
        password: form.password,
        real_name: form.real_name,
        role: Number(form.role),
        dept: form.dept,
        phone: form.phone,
      });
      ElMessage.success('用户创建成功');
    }

    formVisible.value = false;
    loadList();
  } finally {
    saving.value = false;
  }
}

async function handleStatusChange(row, value) {
  const status = value ? 1 : 0;

  try {
    await updateUserStatus(row.id, status);
    row.status = status;
    ElMessage.success(status === 1 ? '已启用' : '已禁用');
  } catch (e) {
    // 失败时开关保持原状态
  }
}

function openReset(row) {
  Object.assign(resetForm, { id: row.id, username: row.username, password: '' });
  resetVisible.value = true;
}

async function handleResetPassword() {
  const valid = await resetFormRef.value.validate().catch(() => false);
  if (!valid) return;

  resetting.value = true;

  try {
    await resetUserPassword(resetForm.id, resetForm.password);
    ElMessage.success('密码重置成功');
    resetVisible.value = false;
  } finally {
    resetting.value = false;
  }
}

async function loadSuppliers() {
  const data = await getSuppliers();
  suppliers.value = data || [];
}

/**
 * 查询用户维护日志
 * @param {object} row
 */
async function openLogs(row) {
  logTarget.value = row;
  logs.value = [];
  logVisible.value = true;
  logLoading.value = true;

  try {
    const data = await getUserLogs(row.id);
    logs.value = data || [];
  } finally {
    logLoading.value = false;
  }
}

onMounted(() => {
  loadList();
  loadSuppliers();
});
</script>

<style scoped>
.row-log-btn {
  margin-left: 8px;
}
</style>
