import { useEffect } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'

import { useAuth } from '../context/AuthContext.jsx'
import DashboardPage from '../pages/DashboardPage.jsx'
import LoginPage from '../pages/LoginPage.jsx'

function ProtectedDashboard() {
  const { isAuthenticated, isLoading, isSessionUnknown, restoreSession } = useAuth()

  useEffect(() => {
    restoreSession()
  }, [restoreSession])

  if (isLoading || isSessionUnknown) {
    return <main className="auth-loading" role="status">Checking your session…</main>
  }

  return isAuthenticated ? <DashboardPage /> : <Navigate to="/login" replace />
}

function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/dashboard" element={<ProtectedDashboard />} />
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  )
}

export default AppRoutes
