<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { getProposals, getStatistics } from '@/api/proposal';
import { useUserStore } from '@/store/user';
import { formatDateTime } from '@/utils/format';
import { STATUS_TAG, STATUS_TEXT } from '@/utils/constants';

const router = useRouter();
const userStore = useUserStore();

const stats = ref({ draft: 0, pending: 0, approved: 0, archived: 0, obsolete: 0, total: 0 });
const recent = ref([]);
const loading = ref(false);

const cards = [
  { key: 'draft', label: '草稿', status: 0, color: '#8a93a6' },
  { key: 'pending', label: '待评审', status: 1, color: '#d99414' },
  { key: 'approved', label: '评审通过', status: 2, color: '#2a9d62' },
  { key: 'archived', label: '已归档', status: 4, color: '#1f5fbf' },
  { key: 'obsolete', label: '已作废', status: 5, color: '#98a2b3' },
];

const roleHint = computed(() => {
  const role = userStore.user?.role;
  if (role === 1) return '当前为供应商账号：可编辑/提交/撤回本供应商方案，已归档方案可发起变更。';
  if (role === 2) return '当前为评审员账号：可查看所有已提交方案并进行评审。';
  if (role === 4) return '当前为查看者账号：可查看全部方案与导出，不能修改任何信息。';
  return '当前为管理员账号：可维护主数据、全量查看、评审与批量归档。';
});

async function load() {
  loading.value = true;

  try {
    const [statistics, list] = await Promise.all([
      getStatistics(),
      getProposals({ page: 1, page_size: 8 }),
    ]);
    stats.value = statistics;
    recent.value = list.list || [];
  } finally {
    loading.value = false;
  }
}

function goList(status) {
  router.push({ path: '/proposals', query: { status: String(status) } });
}

onMounted(load);
</script>

<template>
  <div>
    <div class="page-card" style="margin-bottom: 14px">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 20px">
        <div>
          <h2 style="margin: 0 0 8px; font-size: 20px">
            你好，{{ userStore.displayName }}
          </h2>
          <p class="muted" style="margin: 0">{{ roleHint }}</p>
        </div>
        <el-button type="primary" @click="router.push('/proposals')">进入方案列表</el-button>
      </div>
    </div>

    <div class="stat-grid" v-loading="loading">
      <div v-for="card in cards" :key="card.key" class="stat-card" @click="goList(card.status)">
        <div class="label">{{ card.label }}</div>
        <div class="value" :style="{ color: card.color }">{{ stats[card.key] || 0 }}</div>
      </div>
      <div class="stat-card" @click="router.push('/proposals')">
        <div class="label">方案总数</div>
        <div class="value">{{ stats.total || 0 }}</div>
      </div>
    </div>

    <div class="page-card" style="margin-top: 14px">
      <div class="page-toolbar">
        <h3 class="section-title" style="margin: 0">最近方案</h3>
        <div class="spacer"></div>
        <el-button link type="primary" @click="router.push('/proposals')">查看全部</el-button>
      </div>

      <el-table :data="recent" v-loading="loading" size="small">
        <el-table-column prop="proposal_no" label="方案编号" width="160" />
        <el-table-column prop="part_no" label="零件号" width="150" />
        <el-table-column prop="part_name" label="零件名称" min-width="160" show-overflow-tooltip />
        <el-table-column prop="supplier_name" label="供应商" width="130" show-overflow-tooltip />
        <el-table-column label="状态" width="110">
          <template #default="{ row }">
            <el-tag size="small" :type="STATUS_TAG[row.status]" effect="light">
              {{ row.status_label || STATUS_TEXT[row.status] }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="更新时间" width="170">
          <template #default="{ row }">{{ formatDateTime(row.updated_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="90">
          <template #default="{ row }">
            <el-button link type="primary" @click="router.push(`/proposals/${row.id}`)">查看</el-button>
          </template>
        </el-table-column>
        <template #empty>
          <div class="empty">暂无方案数据</div>
        </template>
      </el-table>
    </div>
  </div>
</template>
