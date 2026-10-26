<template>
  <div class="page-card">
    <div class="page-toolbar">
      <el-select v-model="query.dict_type" placeholder="字典类型" clearable style="width: 160px">
        <el-option v-for="type in dictTypes" :key="type" :label="type" :value="type" />
      </el-select>
      <el-input
        v-model="query.keyword"
        placeholder="名称 / 字典值"
        clearable
        style="width: 180px"
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
        新增字典
      </el-button>
    </div>

    <el-table v-loading="loading" :data="list" border stripe>
      <el-table-column prop="dict_type" label="字典类型" width="150" />
      <el-table-column prop="dict_label" label="显示名称" min-width="150" />
      <el-table-column prop="dict_value" label="字典值" min-width="150" />
      <el-table-column prop="sort" label="排序" width="80" align="center" />
      <el-table-column label="状态" width="100" align="center">
        <template #default="{ row }">
          <el-tag size="small" :type="Number(row.status) === 1 ? 'success' : 'info'">
            {{ Number(row.status) === 1 ? '启用' : '禁用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="remark" label="备注" min-width="150" show-overflow-tooltip />
      <el-table-column prop="update_time" label="更新时间" width="170" align="center" />
      <el-table-column label="操作" width="150" fixed="right" align="center">
        <template #default="{ row }">
          <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
      <template #empty>
        <span class="text-muted">暂无字典数据</span>
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
      :title="form.id ? '编辑字典' : '新增字典'"
      width="520px"
      destroy-on-close
    >
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="字典类型" prop="dict_type">
          <el-select
            v-model="form.dict_type"
            filterable
            allow-create
            default-first-option
            placeholder="选择或输入类型"
            style="width: 100%"
          >
            <el-option v-for="type in dictTypes" :key="type" :label="type" :value="type" />
          </el-select>
        </el-form-item>
        <el-form-item label="显示名称" prop="dict_label">
          <el-input v-model="form.dict_label" maxlength="100" />
        </el-form-item>
        <el-form-item label="字典值" prop="dict_value">
          <el-input v-model="form.dict_value" maxlength="100" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" :max="9999" :controls="false" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" type="textarea" :rows="2" maxlength="255" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import { getDictList, createDict, updateDict, deleteDict } from '@/api/admin';
import { getDictTypes } from '@/api/dict';

const loading = ref(false);
const saving = ref(false);
const list = ref([]);
const total = ref(0);
const dictTypes = ref([]);

const query = reactive({
  dict_type: '',
  keyword: '',
  status: '',
  page: 1,
  page_size: 10,
});

const formVisible = ref(false);
const formRef = ref(null);
const form = reactive({
  id: 0,
  dict_type: '',
  dict_label: '',
  dict_value: '',
  sort: 0,
  status: 1,
  remark: '',
});

const rules = {
  dict_type: [{ required: true, message: '请选择或输入字典类型', trigger: 'change' }],
  dict_label: [{ required: true, message: '请输入显示名称', trigger: 'blur' }],
  dict_value: [{ required: true, message: '请输入字典值', trigger: 'blur' }],
};

async function loadTypes() {
  dictTypes.value = (await getDictTypes()) || [];
}

async function loadList() {
  loading.value = true;

  try {
    const data = await getDictList({ ...query });
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
  query.dict_type = '';
  query.keyword = '';
  query.status = '';
  query.page = 1;
  loadList();
}

function openCreate() {
  Object.assign(form, {
    id: 0,
    dict_type: query.dict_type || '',
    dict_label: '',
    dict_value: '',
    sort: 0,
    status: 1,
    remark: '',
  });
  formVisible.value = true;
}

function openEdit(row) {
  Object.assign(form, {
    id: row.id,
    dict_type: row.dict_type,
    dict_label: row.dict_label,
    dict_value: row.dict_value,
    sort: Number(row.sort || 0),
    status: Number(row.status),
    remark: row.remark || '',
  });
  formVisible.value = true;
}

async function handleSave() {
  const valid = await formRef.value.validate().catch(() => false);
  if (!valid) return;

  saving.value = true;

  try {
    const payload = {
      dict_type: form.dict_type,
      dict_label: form.dict_label,
      dict_value: form.dict_value,
      sort: Number(form.sort || 0),
      status: Number(form.status),
      remark: form.remark,
    };

    if (form.id) {
      await updateDict(form.id, payload);
      ElMessage.success('字典更新成功');
    } else {
      await createDict(payload);
      ElMessage.success('字典新增成功');
    }

    formVisible.value = false;
    await loadTypes();
    loadList();
  } finally {
    saving.value = false;
  }
}

async function handleDelete(row) {
  await ElMessageBox.confirm(
    `确定删除字典「${row.dict_label}」吗？已被方案使用的字典不允许删除。`,
    '删除字典',
    { type: 'warning' },
  );
  await deleteDict(row.id);
  ElMessage.success('字典已删除');
  await loadTypes();
  loadList();
}

onMounted(async () => {
  await loadTypes();
  await loadList();
});
</script>
