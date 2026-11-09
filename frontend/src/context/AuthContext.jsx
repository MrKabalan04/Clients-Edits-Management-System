import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'

import api, { initializeCsrfCookie } from '../services/api.js'

const AuthContext = createContext(null)
let currentUserRequest = null

function getCurrentUser() {
  if (!currentUserRequest) {
    currentUserRequest = api.get('/admin/me').finally(() => {
      currentUserRequest = null
    })
  }

  return currentUserRequest
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [authStatus, setAuthStatus] = useState('unknown')
  const [authError, setAuthError] = useState('')

  useEffect(() => {
    window.localStorage.removeItem('client-edits-auth')
  }, [])

  const restoreSession = useCallback(async () => {
    if (authStatus !== 'unknown') {
      return
    }

    setAuthStatus('loading')

    try {
      const { data } = await getCurrentUser()
      if (!data.success || data.user?.role !== 'admin') {
        throw new Error('The server returned an invalid administrator session.')
      }

      setUser(data.user)
      setAuthStatus('authenticated')
    } catch (error) {
      setUser(null)
      setAuthStatus('unauthenticated')

      if (error.response?.status !== 401) {
        setAuthError('We couldn’t verify your session. Check your connection and try again.')
      }
    }
  }, [authStatus])

  async function login(email, password) {
    setAuthError('')
    await initializeCsrfCookie()

    const response = await api.post('/login', { email, password })

    if (!response.data.success) {
      throw new Error('The server could not authenticate this account.')
    }

    const currentUser = await api.get('/admin/me')

    if (!currentUser.data.success || currentUser.data.user?.role !== 'admin') {
      throw new Error('The server did not establish an administrator session.')
    }

    setUser(currentUser.data.user)
    setAuthStatus('authenticated')
  }

  async function logout() {
    await initializeCsrfCookie()
    await api.post('/logout')
    setUser(null)
    setAuthError('')
    setAuthStatus('unauthenticated')
  }

  const value = useMemo(() => ({
    authError,
    isAuthenticated: authStatus === 'authenticated',
    isLoading: authStatus === 'loading',
    isSessionUnknown: authStatus === 'unknown',
    login,
    logout,
    restoreSession,
    user,
  }), [authError, authStatus, restoreSession, user])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth must be used inside an AuthProvider.')
  }

  return context
}
