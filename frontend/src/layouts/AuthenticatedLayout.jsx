import { useState } from 'react'
import { Outlet } from 'react-router-dom'

import { useAuth } from '../context/AuthContext.jsx'
import SidebarNavigation from '../components/SidebarNavigation.jsx'

function AuthenticatedLayout() {
  const { logout, user } = useAuth()
  const [isSidebarOpen, setIsSidebarOpen] = useState(false)
  const [isSigningOut, setIsSigningOut] = useState(false)
  const [logoutError, setLogoutError] = useState('')

  async function handleLogout() {
    setIsSigningOut(true)
    setLogoutError('')

    try {
      await logout()
    } catch {
      setLogoutError('We couldn’t sign you out. Please check your connection and try again.')
    } finally {
      setIsSigningOut(false)
    }
  }

  return (
    <div className="app-shell">
      <SidebarNavigation
        isOpen={isSidebarOpen}
        isSigningOut={isSigningOut}
        onClose={() => setIsSidebarOpen(false)}
        onLogout={handleLogout}
        user={user}
      />
      <div className="app-main">
        <header className="app-mobile-header">
          <button
            aria-controls="app-navigation"
            aria-expanded={isSidebarOpen}
            aria-label={isSidebarOpen ? 'Close navigation menu' : 'Open navigation menu'}
            className="mobile-menu-button"
            onClick={() => setIsSidebarOpen((open) => !open)}
            type="button"
          >
            <i className={`bi ${isSidebarOpen ? 'bi-x-lg' : 'bi-list'}`} aria-hidden="true" />
          </button>
          <span className="mobile-header-title">Client Edits</span>
          <span className="mobile-header-avatar" aria-hidden="true">
            {user?.name?.trim()?.[0]?.toUpperCase() || 'A'}
          </span>
        </header>
        <main className="app-content">
          {logoutError && <p className="layout-alert" role="alert">{logoutError}</p>}
          <Outlet />
        </main>
      </div>
    </div>
  )
}

export default AuthenticatedLayout
