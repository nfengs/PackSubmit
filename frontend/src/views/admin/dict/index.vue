<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';

import { createDict, deleteDict, getDictPage, updateDict } from '@/api/admin';
import { formatDateTime } from '@/utils/format';

const loading = ref(false);
const rows = ref([]);
const total = ref(0);
const query = reactive({ page: 1, page_size: 20, dict_type: '', keyword: '' });

const dialogVisible = ref(false);
const submitting = ref(false);
const editingId = ref('');
const form = reactive({ dict_type: 'customer', dict_label: '', dict_value: '', sort: 0, remark: '', enabled: true });

async function load() {
  loading.value = true;

  try {
    const data = await getDictPage({
      page: query.page,
      page_size: query.page_size,
      dict_type: query.dict_type || undefined,
      keyword: query.keyword || undefined,
    });
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
  Object.assign(form, { dict_type: 'customer', dict_label: '', dict_value: '', sort: 0, remark: '', enabled: true });
  dialogVisible.value = true;
}

function openEdit(row) {
  editingId.value = row.id;
  Object.assign(form, {
    dict_type: row.dict_type,
    dict_label: row.dict_label,
    dict_value: row.dict_value,
    sort: row.sort,
    remark: row.remark,
    enabled: row.status === 1,
  });
  dialogVisible.value = true;
}

async function submit() {
  if (!form.dict_type.trim() || !form.dict_label.trim() || !form.dict_value.trim()) {
    ElMessage.warning('字典类型、显示名称、存储值均不能为空');
    return;
  }

  submitting.value = true;

  try {
    const payload = {
      dict_type: form.dict_type.trim(),
      dict_label: form.dict_label.trim(),
      dict_value: form.dict_value.trim(),
      sort: Number(form.sort) || 0,
      remark: form.remark,
      status: form.enabled ? 1 : 0,
    };

    if (editingId.value) {
      await updateDict(editingId.value, payload);
    } else {
      await createDict(payload);
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
    await ElMessageBox.confirm(`确认删除字典「${row.dict_label}」？`, '删除确认', { type: 'warning' });
  } catch (e) {
    return;
  }

  await deleteDict(row.id);
  ElMessage.success('删除成功');
  load();
}
</script>

<template>
  <div class="page-card">
    <div class="page-toolbar">
      <el-input v-model="query.dict_type" placeholder="字典类型（如 customer）" clearable style="width: 200px"
        @keyup.enter="search" />
      <el-input v-model="query.keyword" placeholder="显示名称 / 存储值" clearable style="width: 200px"
        @keyup.enter="search" />
      <el-button type="primary" @click="search">查询</el-button>
      <div class="spacer"></div>
      <el-button type="primary" @click="openCreate">新增字典</el-button>
    </div>

    <el-table :data="rows" v-loading="loading" size="small">
      <el-table-column prop="dict_type" label="字典类型" width="140" />
      <el-table-column prop="dict_label" label="显示名称" min-width="140" />
      <el-table-column prop="dict_value" label="存储值" min-width="140" />
      <el-table-column prop="sort" label="排序" width="80" />
      <el-table-column prop="remark" label="备注" min-width="160" show-overflow-tooltip />
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
      <el-table-column label="操作" width="130" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button link type="danger" @click="remove(row)">删除</el-button>
        </template>
      </el-table-column>
      <template #empty>
        <div class="empty">暂无字典数据</div>
      </template>
    </el-table>

    <div style="display: flex; justify-content: flex-end; margin-top: 14px">
      <el-pagination v-model:current-page="query.page" v-model:page-size="query.page_size" :total="total"
        :page-sizes="[20, 50, 100]" layout="total, sizes, prev, pager, next" @current-change="load"
        @size-change="search" />
    </div>

    <el-dialog v-model="dialogVisible" :title="editingId ? '编辑字典' : '新增字典'" width="500px">
      <el-form label-width="90px">
        <el-form-item label="字典类型" required>
          <el-input v-model="form.dict_type" maxlength="50" />
        </el-form-item>
        <el-form-item label="显示名称" required>
          <el-input v-model="form.dict_label" maxlength="100" />
        </el-form-item>
        <el-form-item label="存储值" required>
          <el-input v-model="form.dict_value" maxlength="100" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :precision="0" style="width: 100%" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" maxlength="255" />
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
  </div>
</template>
