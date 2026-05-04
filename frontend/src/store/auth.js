import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import request from '@/api/request'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('token') || '')
  const user = ref(null)

  const isLoggedIn = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.is_admin === 1)
  const permissions = computed(() => user.value?.permissions || [])
  const displayName = computed(() => user.value?.real_name || user.value?.username || '')

  function hasPermission(code) {
    if (isAdmin.value) return true
    return permissions.value.includes(code)
  }

  async function login(username, password) {
    const res = await request.post('/auth/login', { username, password })
    token.value = res.data.token
    user.value = res.data.user
    localStorage.setItem('token', res.data.token)
    await fetchUser()
  }

  async function fetchUser() {
    try {
      const res = await request.get('/auth/me')
      user.value = res.data
    } catch {
      logout()
    }
  }

  function logout() {
    token.value = ''
    user.value = null
    localStorage.removeItem('token')
  }

  return { token, user, isLoggedIn, isAdmin, permissions, displayName, hasPermission, login, fetchUser, logout }
})
