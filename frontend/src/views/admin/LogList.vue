<template>
  <div class="page-card">
    <div class="page-toolbar">
      <el-date-picker
        v-model="dateRange"
        type="daterange"
        range-separator="至"
        start-placeholder="开始日期"
        end-placeholder="结束日期"
        value-format="YYYY-MM-DD"
        style="width: 250px"
      />
      <el-input
        v-model="query.operator"
        placeholder="操作人账号/姓名"
        clearable
        style="width: 180px"
        @keyup.enter="handleSearch"
      />
      <el-select v-model="query.action" placeholder="操作类型" clearable style="width: 160px">
        <el-option v-for="item in actionOptions" :key="item.value" :label="item.label" :value="item.value" />
      </el-select>
      <el-input
        v-model="query.keyword"
        placeholder="操作内容关键字"
        clearable
        style="width: 180px"
        @keyup.enter="handleSearch"
      />
      <el-button type="primary" @click="handleSearch">查询</el-button>
      <el-button @click="handleReset">重置</el-button>
    </div>

    <el-table v-loading="loading" :data="list" border stripe>
      <el-table-column prop="create_time" label="操作时间" width="170" align="center" />
      <el-table-column label="操作人" width="150">
        <template #default="{ row }">
          {{ row.operator?.real_name || '-' }}
          <span class="text-muted">（{{ row.operator?.username || '-' }}）</span>
        </template>
      </el-table-column>
      <el-table-column prop="action_name" label="操作类型" width="130" align="center" />
      <el-table-column label="关联方案" min-width="180" show-overflow-tooltip>
        <template #default="{ row }">
          <template v-if="row.proposal">
            {{ row.proposal.proposal_no }} - {{ row.proposal.title }}
          </template>
          <span v-else class="text-muted">-</span>
        </template>
      </el-table-column>
      <el-table-column prop="content" label="操作内容" min-width="220" show-overflow-tooltip />
      <el-table-column prop="ip" label="IP" width="130" align="center" />
      <template #empty>
        <span class="text-muted">暂无日志数据</span>
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
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { getLogList } from '@/api/admin';
import { ACTION_OPTIONS } from '@/utils/constants';

const actionOptions = ACTION_OPTIONS;
const loading = ref(false);
const list = ref([]);
const total = ref(0);
const dateRange = ref([]);

const query = reactive({
  operator: '',
  action: '',
  keyword: '',
  start_date: '',
  end_date: '',
  page: 1,
  page_size: 10,
});

async function loadList() {
  loading.value = true;

  try {
    const data = await getLogList({
      ...query,
      start_date: dateRange.value?.[0] || '',
      end_date: dateRange.value?.[1] || '',
    });
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
  query.operator = '';
  query.action = '';
  query.keyword = '';
  dateRange.value = [];
  query.page = 1;
  loadList();
}

onMounted(loadList);
</script>
