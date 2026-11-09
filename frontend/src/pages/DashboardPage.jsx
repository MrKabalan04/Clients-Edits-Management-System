import { useState } from 'react'

import { useAuth } from '../context/AuthContext.jsx'

function DashboardPage() {
  const { user, logout } = useAuth()
  const [logoutError, setLogoutError] = useState('')
  const [isSigningOut, setIsSigningOut] = useState(false)

  async function handleSignOut() {
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
    <main className="dashboard-placeholder">
      <header className="dashboard-topbar">
        <span className="brand-mark" aria-hidden="true">
          <i className="bi bi-layers" />
          <span />
        </span>
        <span className="brand-name">
          <strong>Client Edits</strong>
          <small>MANAGEMENT SYSTEM</small>
        </span>
        <button className="sign-out-button" disabled={isSigningOut} onClick={handleSignOut} type="button">
          {isSigningOut ? 'Signing out…' : 'Sign out'}
        </button>
      </header>
      <section>
        <span className="eyebrow"><span /> ADMIN WORKSPACE</span>
        <h1>You’re signed in, {user.name.split(' ')[0]}.</h1>
        <p>Your workspace is ready. Dashboard features will be available here soon.</p>
        {logoutError && <p className="form-alert" role="alert">{logoutError}</p>}
      </section>
    </main>
  )
}

export default DashboardPage
