import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  { path: '/login', component: () => import('@/views/Login.vue') },
  {
    path: '/',
    component: () => import('@/views/layout/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'dashboard', component: () => import('@/views/Dashboard.vue') },
      { path: 'finance/subjects', component: () => import('@/views/subjects/Index.vue') },
      { path: 'finance/vouchers', component: () => import('@/views/vouchers/Index.vue') },
      { path: 'system/periods', component: () => import('@/views/periods/Index.vue'), meta: { title: '期间管理' } },
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
