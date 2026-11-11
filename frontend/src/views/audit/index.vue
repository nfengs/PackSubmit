<script setup>
import { onMounted, reactive, ref, watch } from 'vue';

import { getAuditLogs } from '@/api/proposal';
import { ACTION_OPTIONS } from '@/utils/constants';
import { formatDateTime } from '@/utils/format';

const loading = ref(false);
const rows = ref([]);
const total = ref(0);
const dateRange = ref([]);

const query = reactive({
  page: 1,
  page_size: 20,
  keyword: '',
  action: '',
  start_date: '',
  end_date: '',
});

async function load() {
  loading.value = true;

  try {
    const params = { ...query };
    if (!params.keyword) delete params.keyword;
    if (!params.action) delete params.action;

    const data = await getAuditLogs(params);
    rows.value = data.list || [];
    total.value = data.total || 0;
  } finally {
    loading.value = false;
  }
}

watch(dateRange, (value) => {
  query.start_date = value?.[0] || '';
  query.end_date = value?.[1] || '';
});

function search() {
  query.page = 1;
  load();
}

function reset() {
  Object.assign(query, { page: 1, keyword: '', action: '', start_date: '', end_date: '' });
  dateRange.value = [];
  load();
}

onMounted(load);
</script>

<template>
  <div class="page-card">
    <div class="page-toolbar">
      <el-input v-model="query.keyword" placeholder="动作名称 / 内容关键字" clearable style="width: 240px"
        @keyup.enter="search" />
      <el-select v-model="query.action" placeholder="动作" clearable filterable style="width: 160px">
        <el-option v-for="item in ACTION_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
      </el-select>
      <el-date-picker v-model="dateRange" type="daterange" value-format="YYYY-MM-DD" start-placeholder="开始日期"
        end-placeholder="结束日期" style="width: 240px" />
      <el-button type="primary" @click="search">查询</el-button>
      <el-button @click="reset">重置</el-button>
    </div>

    <el-table :data="rows" v-loading="loading" size="small">
      <el-table-column label="时间" width="170">
        <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
      </el-table-column>
      <el-table-column prop="user_name" label="操作人" width="120" />
      <el-table-column prop="action_name" label="动作" width="140" />
      <el-table-column label="版本" width="72">
        <template #default="{ row }">V{{ row.version }}</template>
      </el-table-column>
      <el-table-column prop="content" label="内容" min-width="260" show-overflow-tooltip />
      <el-table-column prop="ip" label="IP" width="140" />
      <template #empty>
        <div class="empty">暂无日志</div>
      </template>
    </el-table>

    <div style="display: flex; justify-content: flex-end; margin-top: 14px">
      <el-pagination v-model:current-page="query.page" v-model:page-size="query.page_size" :total="total"
        :page-sizes="[20, 50, 100]" layout="total, sizes, prev, pager, next" @current-change="load"
        @size-change="search" />
    </div>
  </div>
</template>
