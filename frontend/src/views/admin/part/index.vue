<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';

import {
  deletePart,
  downloadPartTemplate,
  getPartLogs,
  getPartPage,
  importParts,
  previewPartBatch,
  updatePart,
  updatePartBatch,
} from '@/api/admin';
import { getSuppliers } from '@/api/masterdata';
import ImportDialog from '@/components/ImportDialog.vue';
import LogDrawer from '@/components/LogDrawer.vue';
import { formatDateTime } from '@/utils/format';

const loading = ref(false);
const rows = ref([]);
const total = ref(0);
const suppliers = ref([]);
const query = reactive({ page: 1, page_size: 20, supplier_id: '', keyword: '' });

const dialogVisible = ref(false);
const submitting = ref(false);
const editingRow = ref(null);
const form = reactive({ project_name: '', part_name: '' });

const importVisible = ref(false);

const batchVisible = ref(false);
const batchLoading = ref(false);
const batchRows = ref([]);
const batchFileName = ref('');

const logsVisible = ref(false);
const logsLoading = ref(false);
const logs = ref([]);
const logsTitle = ref('');

async function load() {
  loading.value = true;

  try {
    const data = await getPartPage({
      page: query.page,
      page_size: query.page_size,
      supplier_id: query.supplier_id || undefined,
      keyword: query.keyword || undefined,
    });
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

function openEdit(row) {
  editingRow.value = row;
  Object.assign(form, { project_name: row.project_name, part_name: row.part_name });
  dialogVisible.value = true;
}

async function submit() {
  if (!form.project_name.trim() && !form.part_name.trim()) {
    ElMessage.warning('项目名称与零件名称不能都为空');
    return;
  }

  submitting.value = true;

  try {
    await updatePart(editingRow.value.id, {
      project_name: form.project_name.trim(),
      part_name: form.part_name.trim(),
    });
    ElMessage.success('修改成功，已同步该零件下所有方案');
    dialogVisible.value = false;
    load();
  } finally {
    submitting.value = false;
  }
}

async function remove(row) {
  try {
    await ElMessageBox.confirm(
      `确认删除零件号「${row.part_no}」？删除只移除清单行，已生成的方案不会级联删除。`,
      '删除确认',
      { type: 'warning' },
    );
  } catch (e) {
    return;
  }

  await deletePart(row.id);
  ElMessage.success('删除成功');
  load();
}

function openBatch() {
  batchVisible.value = true;
  batchRows.value = [];
  batchFileName.value = '';
}

async function onBatchFile(uploadFile) {
  const file = uploadFile.raw;
  if (!file) return;

  batchLoading.value = true;
  batchFileName.value = file.name;

  try {
    batchRows.value = (await previewPartBatch(file)) || [];
  } finally {
    batchLoading.value = false;
  }
}

async function confirmBatch() {
  const payload = batchRows.value
    .filter((item) => item.status === 'update')
    .map((item) => ({
      supplier_name: item.supplier_name,
      part_no: item.part_no,
      project_name: item.new_project_name,
      part_name: item.new_part_name,
    }));

  if (!payload.length) {
    ElMessage.warning('没有需要修改的内容');
    return;
  }

  try {
    await ElMessageBox.confirm(
      `将修改 ${payload.length} 条零件号信息，并同步到该零件下所有状态的方案，确认提交？`,
      '二次确认',
      { type: 'warning' },
    );
  } catch (e) {
    return;
  }

  const data = await updatePartBatch(payload);
  ElMessage.success(`已修改 ${data.count} 条`);
  batchVisible.value = false;
  load();
}

async function openLogs(row) {
  logsTitle.value = `零件号维护日志 · ${row.part_no}`;
  logsVisible.value = true;
  logsLoading.value = true;

  try {
    logs.value = (await getPartLogs(row.id)) || [];
  } finally {
    logsLoading.value = false;
  }
}

function batchStatusText(status) {
  return { update: '可修改', same: '无变化', missing: '零件号不存在', error: '异常' }[status] || status;
}

function batchStatusType(status) {
  return { update: 'warning', same: 'info', missing: 'danger', error: 'danger' }[status] || 'info';
}
</script>

<template>
  <div class="page-card">
    <div class="page-toolbar">
      <el-select v-model="query.supplier_id" placeholder="供应商" clearable filterable style="width: 200px">
        <el-option v-for="item in suppliers" :key="item.id" :label="item.name" :value="item.id" />
      </el-select>
      <el-input v-model="query.keyword" placeholder="零件号 / 零件名称 / 项目" clearable style="width: 220px"
        @keyup.enter="search" />
      <el-button type="primary" @click="search">查询</el-button>
      <div class="spacer"></div>
      <el-button @click="openBatch">批量修改</el-button>
      <el-button type="primary" @click="importVisible = true">导入零件号</el-button>
    </div>

    <el-alert type="info" :closable="false" show-icon style="margin-bottom: 12px"
      title="导入零件号后，若该「供应商 + 零件号」没有任何有效方案，系统会自动生成方案草稿（受控建单）。" />

    <el-table :data="rows" v-loading="loading" size="small">
      <el-table-column prop="supplier_name" label="供应商" width="150" show-overflow-tooltip />
      <el-table-column prop="part_no" label="零件号" width="160" />
      <el-table-column prop="part_name" label="零件名称" min-width="170" show-overflow-tooltip />
      <el-table-column prop="project_name" label="项目名称" min-width="150" show-overflow-tooltip />
      <el-table-column label="更新时间" width="170">
        <template #default="{ row }">{{ formatDateTime(row.updated_at) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="190" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button link type="primary" @click="openLogs(row)">日志</el-button>
          <el-button link type="danger" @click="remove(row)">删除</el-button>
        </template>
      </el-table-column>
      <template #empty>
        <div class="empty">暂无零件号，请先导入</div>
      </template>
    </el-table>

    <div style="display: flex; justify-content: flex-end; margin-top: 14px">
      <el-pagination v-model:current-page="query.page" v-model:page-size="query.page_size" :total="total"
        :page-sizes="[20, 50, 100]" layout="total, sizes, prev, pager, next" @current-change="load"
        @size-change="search" />
    </div>

    <el-dialog v-model="dialogVisible" title="编辑零件号" width="500px">
      <el-form label-width="90px">
        <el-form-item label="供应商">
          <el-input :model-value="editingRow?.supplier_name" disabled />
        </el-form-item>
        <el-form-item label="零件号">
          <el-input :model-value="editingRow?.part_no" disabled />
        </el-form-item>
        <el-form-item label="项目名称">
          <el-input v-model="form.project_name" maxlength="150" />
        </el-form-item>
        <el-form-item label="零件名称">
          <el-input v-model="form.part_name" maxlength="200" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submit">保存</el-button>
      </template>
    </el-dialog>

    <ImportDialog v-model="importVisible" title="导入零件号"
      tips="列顺序：供应商名称、项目名称、零件号、零件名称。按「供应商 + 零件号」新增或更新，并在无有效方案时自动生成草稿。"
      template-name="指定零件号导入模板.xlsx" :template-loader="downloadPartTemplate" :uploader="importParts"
      @success="load" />

    <el-dialog v-model="batchVisible" title="批量修改零件号" width="900px">
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px">
        <el-upload :auto-upload="false" :show-file-list="false" accept=".xlsx,.xls" :on-change="onBatchFile">
          <el-button :loading="batchLoading">选择 Excel 并预览</el-button>
        </el-upload>
        <span class="muted" style="font-size: 13px">{{ batchFileName || '未选择文件' }}</span>
        <div class="spacer"></div>
        <span class="muted" style="font-size: 12px">预览阶段不写库</span>
      </div>

      <el-table :data="batchRows" v-loading="batchLoading" size="small" max-height="420">
        <el-table-column prop="supplier_name" label="供应商" width="130" show-overflow-tooltip />
        <el-table-column prop="part_no" label="零件号" width="140" />
        <el-table-column label="项目名称（旧 → 新）" min-width="180">
          <template #default="{ row }">
            <span class="muted">{{ row.project_name || '-' }}</span>
            <span v-if="row.project_name !== row.new_project_name"> → {{ row.new_project_name }}</span>
          </template>
        </el-table-column>
        <el-table-column label="零件名称（旧 → 新）" min-width="180">
          <template #default="{ row }">
            <span class="muted">{{ row.part_name || '-' }}</span>
            <span v-if="row.part_name !== row.new_part_name"> → {{ row.new_part_name }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="130">
          <template #default="{ row }">
            <el-tag size="small" :type="batchStatusType(row.status)" effect="plain">
              {{ batchStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
      </el-table>

      <template #footer>
        <el-button @click="batchVisible = false">取消</el-button>
        <el-button type="primary" :disabled="!batchRows.some((item) => item.status === 'update')"
          @click="confirmBatch">
          确认修改
        </el-button>
      </template>
    </el-dialog>

    <LogDrawer v-model="logsVisible" :title="logsTitle" :logs="logs" :loading="logsLoading" />
  </div>
</template>
