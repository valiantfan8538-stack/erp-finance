<template>
  <el-container style="height: 100vh;">
    <el-aside width="220px" style="background: #304156; overflow-y: auto;">
      <div class="logo-area">ERP财务系统</div>
      <el-menu :default-active="$route.path" router background-color="#304156" text-color="#bfcbd9" active-text-color="#409EFF">
        <el-menu-item index="/dashboard">
          <span>首页</span>
        </el-menu-item>
        <el-sub-menu index="/finance" v-if="authStore.hasPermission('finance')">
          <template #title>财务管理</template>
          <el-menu-item index="/finance/subjects" v-if="authStore.hasPermission('finance:subject')">会计科目</el-menu-item>
          <el-menu-item index="/finance/vouchers" v-if="authStore.hasPermission('finance:voucher')">记账凭证</el-menu-item>
        </el-sub-menu>
        <el-sub-menu index="/arap" v-if="authStore.hasPermission('arap')">
          <template #title>应收应付</template>
          <el-menu-item index="/arap/receivable">应收账款</el-menu-item>
          <el-menu-item index="/arap/payable">应付账款</el-menu-item>
        </el-sub-menu>
        <el-sub-menu index="/asset" v-if="authStore.hasPermission('asset')">
          <template #title>固定资产</template>
          <el-menu-item index="/asset/cards">资产卡片</el-menu-item>
          <el-menu-item index="/asset/depreciation">折旧计提</el-menu-item>
        </el-sub-menu>
        <el-sub-menu index="/system" v-if="authStore.hasPermission('system')">
          <template #title>系统设置</template>
          <el-menu-item index="/system/account-book">账套管理</el-menu-item>
          <el-menu-item index="/system/users">用户管理</el-menu-item>
          <el-menu-item index="/system/roles">角色管理</el-menu-item>
          <el-menu-item index="/system/periods">期间管理</el-menu-item>
        </el-sub-menu>
      </el-menu>
    </el-aside>
    <el-container>
      <el-header style="background: #fff; border-bottom: 1px solid #e6e6e6; display: flex; align-items: center; justify-content: space-between; padding: 0 20px;">
        <div></div>
        <el-dropdown>
          <span class="user-name">{{ authStore.displayName }}</span>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item @click="handleLogout">退出登录</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </el-header>
      <el-main>
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<script setup>
import { useAuthStore } from '@/store/auth'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

const handleLogout = () => {
  authStore.logout()
  router.push('/login')
}
</script>

<style scoped>
.logo-area {
  padding: 20px; color: #fff; text-align: center;
  border-bottom: 1px solid #404857; font-size: 16px; font-weight: bold;
}
.user-name {
  color: #303133; cursor: pointer; font-size: 14px;
}
</style>
