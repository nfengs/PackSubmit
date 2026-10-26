<template>
  <div>
    <div class="welcome-card">
      <div>
        <h2>{{ greeting }}，{{ userStore.displayName }}</h2>
        <p>{{ userStore.roleName }} · {{ userStore.user?.dept || '包装方案协同平台' }}</p>
      </div>
    </div>

    <el-row :gutter="16" class="mt-16">
      <el-col v-for="card in cards" :key="card.key" :xs="12" :sm="12" :md="6">
        <div class="stat-card" @click="goList(card.status)">
          <div class="stat-icon" :style="{ background: card.bg, color: card.color }">
            <el-icon :size="24"><component :is="card.icon" /></el-icon>
          </div>
          <div class="stat-info">
            <span class="stat-value">{{ stats[card.key] || 0 }}</span>
            <span class="stat-label">{{ card.label }}</span>
          </div>
        </div>
      </el-col>
    </el-row>

    <div class="page-card mt-16">
      <div class="card-header">
        <h3>最近方案</h3>
        <el-button link type="primary" @click="router.push('/proposal/list')">查看全部</el-button>
      </div>

      <el-table v-loading="loading" :data="recent" size="small">
        <el-table-column prop="proposal_no" label="方案编号" width="170" />
        <el-table-column prop="part_name" label="零件名称" min-width="180" show-overflow-tooltip />
        <el-table-column prop="part_no" label="零件号" width="150" />
        <el-table-column label="状态" width="110" align="center">
          <template #default="{ row }">
            <StatusTag :status="row.status" />
          </template>
        </el-table-column>
        <el-table-column prop="update_time" label="更新时间" width="170" align="center" />
        <el-table-column label="操作" width="90" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="router.push(`/proposal/detail/${row.id}`)">
              查看
            </el-button>
          </template>
        </el-table-column>
        <template #empty>
          <span class="text-muted">暂无方案数据</span>
        </template>
      </el-table>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import StatusTag from '@/components/StatusTag.vue';
import { getStatistics, getProposalList } from '@/api/proposal';
import { useUserStore } from '@/store/user';
import { STATUS } from '@/utils/constants';

const router = useRouter();
const userStore = useUserStore();

const loading = ref(false);
const stats = ref({ draft: 0, pending: 0, approved: 0, archived: 0 });
const recent = ref([]);

const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour < 6) return '凌晨好';
  if (hour < 12) return '上午好';
  if (hour < 18) return '下午好';
  return '晚上好';
});

/**
 * 统计卡片配置
 */
const cards = computed(() => {
  const prefix = userStore.isSubmitter ? '我的' : '全部';
  return [
    {
      key: 'draft',
      label: `${prefix}草稿`,
      status: STATUS.DRAFT,
      icon: 'EditPen',
      bg: '#f4f4f5',
      color: '#909399',
    },
    {
      key: 'pending',
      label: `${prefix}待评审`,
      status: STATUS.PENDING,
      icon: 'Clock',
      bg: '#fdf6ec',
      color: '#e6a23c',
    },
    {
      key: 'approved',
      label: `${prefix}已通过`,
      status: STATUS.APPROVED,
      icon: 'CircleCheck',
      bg: '#f0f9eb',
      color: '#67c23a',
    },
    {
      key: 'archived',
      label: `${prefix}已归档`,
      status: STATUS.ARCHIVED,
      icon: 'FolderOpened',
      bg: '#ecf5ff',
      color: '#409eff',
    },
  ];
});

/**
 * 跳转到列表并带入状态筛选
 * @param {number} status
 */
function goList(status) {
  router.push({ path: '/proposal/list', query: { status } });
}

async function loadData() {
  loading.value = true;

  try {
    const [statData, listData] = await Promise.all([
      getStatistics(),
      getProposalList({ page: 1, page_size: 5 }),
    ]);

    stats.value = statData;
    recent.value = listData.list || [];
  } finally {
    loading.value = false;
  }
}

onMounted(loadData);
</script>

<style scoped>
.welcome-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 22px 26px;
  border-radius: 10px;
  color: #fff;
  background: linear-gradient(120deg, #2b5876 0%, #4e4376 100%);
}

.welcome-card h2 {
  margin: 0 0 6px;
  font-size: 20px;
}

.welcome-card p {
  margin: 0;
  font-size: 13px;
  opacity: 0.86;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px 20px;
  background: #fff;
  border-radius: 10px;
  cursor: pointer;
  transition: transform 0.15s, box-shadow 0.15s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-info {
  display: flex;
  flex-direction: column;
}

.stat-value {
  font-size: 22px;
  font-weight: 600;
  line-height: 1.2;
}

.stat-label {
  font-size: 13px;
  color: #909399;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.card-header h3 {
  margin: 0;
  font-size: 16px;
}
</style>
