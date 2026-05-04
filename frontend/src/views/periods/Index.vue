<template>
  <div style="padding: 20px;">
    <h2 style="margin-bottom: 20px;">期间管理</h2>

    <el-table
      :data="periods"
      v-loading="loading"
      border
      stripe
      style="width: 100%"
    >
      <el-table-column prop="year" label="年度" width="120" />
      <el-table-column prop="period" label="期间" width="120" />
      <el-table-column prop="start_date" label="开始日期" width="200" />
      <el-table-column prop="end_date" label="结束日期" width="200" />
      <el-table-column label="状态" width="150">
        <template #default="{ row }">
          <el-tag :type="statusType(row.status)">
            {{ statusLabel(row.status) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" min-width="200" fixed="right">
        <template #default="{ row }">
          <el-button
            v-if="row.status === 'open'"
            type="primary"
            size="small"
            @click="handleClose(row)"
          >
            结账
          </el-button>
          <el-button
            v-if="row.status === 'closed'"
            type="warning"
            size="small"
            @click="handleOpen(row)"
          >
            反结账
          </el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getPeriods, closePeriod, openPeriod } from '@/api/finance'

const route = useRoute()
const loading = ref(false)
const periods = ref([])

const bookId = Number(route.query.book_id) || 1

function statusType(status) {
  const map = { open: 'success', closed: 'danger', year_closed: 'info' }
  return map[status] || 'info'
}

function statusLabel(status) {
  const map = { open: '未结账', closed: '已结账', year_closed: '已年结' }
  return map[status] || status
}

async function fetchPeriods() {
  loading.value = true
  try {
    const res = await getPeriods(bookId)
    periods.value = res.data || []
  } catch {
    // Error handled by request interceptor
  } finally {
    loading.value = false
  }
}

async function handleClose(row) {
  try {
    await ElMessageBox.confirm(
      `确认结账 ${row.year} 年 ${row.period} 期？`,
      '确认',
      { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
    )
    await closePeriod(row.id, { book_id: bookId })
    ElMessage.success('结账成功')
    await fetchPeriods()
  } catch {
    // Cancelled by user or error handled by interceptor
  }
}

async function handleOpen(row) {
  try {
    await ElMessageBox.confirm(
      `确认反结账 ${row.year} 年 ${row.period} 期？`,
      '确认',
      { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
    )
    await openPeriod(row.id, { book_id: bookId })
    ElMessage.success('反结账成功')
    await fetchPeriods()
  } catch {
    // Cancelled by user or error handled by interceptor
  }
}

onMounted(fetchPeriods)
</script>
