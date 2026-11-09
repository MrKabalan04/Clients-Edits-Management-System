import { useEffect } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'

import { useAuth } from '../context/AuthContext.jsx'
import AuthenticatedLayout from '../layouts/AuthenticatedLayout.jsx'
import AccountPage from '../pages/AccountPage.jsx'
import ClientDetailsPage from '../pages/ClientDetailsPage.jsx'
import ClientsPage from '../pages/ClientsPage.jsx'
import DashboardPage from '../pages/DashboardPage.jsx'
import LoginPage from '../pages/LoginPage.jsx'
import SectionPlaceholderPage from '../pages/SectionPlaceholderPage.jsx'

function ProtectedAdminArea() {
  const { isAuthenticated, isLoading, isSessionUnknown, restoreSession } = useAuth()

  useEffect(() => {
    restoreSession()
  }, [restoreSession])

  if (isLoading || isSessionUnknown) {
    return <main className="auth-loading" role="status">Checking your session…</main>
  }

  return isAuthenticated ? <AuthenticatedLayout /> : <Navigate to="/login" replace />
}

function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="/login" element={<LoginPage />} />
      <Route element={<ProtectedAdminArea />}>
        <Route path="/dashboard" element={<DashboardPage />} />
        <Route path="/account" element={<AccountPage />} />
        <Route path="/clients" element={<ClientsPage />} />
        <Route path="/clients/:id" element={<ClientDetailsPage />} />
        <Route path="/companies" element={<SectionPlaceholderPage title="Companies" />} />
        <Route path="/projects" element={<SectionPlaceholderPage title="Projects" />} />
        <Route path="/edits" element={<SectionPlaceholderPage title="Edits" />} />
      </Route>
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  )
}

export default AppRoutes
