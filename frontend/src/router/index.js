import { createRouter, createWebHistory } from 'vue-router'

const Placeholder = { template: '<div style="padding:40px;text-align:center;color:#999;"><h2>页面开发中</h2><p>该功能将在下一版本上线</p></div>' }

const routes = [
  { path: '/login', component: () => import('@/views/Login.vue') },
  {
    path: '/',
    component: () => import('@/views/layout/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'dashboard', component: () => import('@/views/Dashboard.vue'), meta: { title: '仪表盘' } },
      { path: 'finance/subjects', component: () => import('@/views/subjects/Index.vue'), meta: { title: '会计科目' } },
      { path: 'finance/vouchers', component: () => import('@/views/vouchers/Index.vue'), meta: { title: '记账凭证' } },
      { path: 'arap/receivable', component: Placeholder, meta: { title: '应收账款' } },
      { path: 'arap/payable', component: Placeholder, meta: { title: '应付账款' } },
      { path: 'asset/cards', component: Placeholder, meta: { title: '资产卡片' } },
      { path: 'asset/depreciation', component: Placeholder, meta: { title: '折旧计提' } },
      { path: 'system/periods', component: () => import('@/views/periods/Index.vue'), meta: { title: '期间管理' } },
      { path: 'system/account-book', component: Placeholder, meta: { title: '账套管理' } },
      { path: 'system/users', component: Placeholder, meta: { title: '用户管理' } },
      { path: 'system/roles', component: Placeholder, meta: { title: '角色管理' } },
    ],
  },
]

const router = createRouter({ history: createWebHistory(), routes })

router.beforeEach((to) => {
  if (to.meta.requiresAuth && !localStorage.getItem('token')) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }
})

export default router
