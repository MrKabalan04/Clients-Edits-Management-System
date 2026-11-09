import { NavLink } from 'react-router-dom'

const navigationItems = [
  { label: 'Dashboard', path: '/dashboard', icon: 'bi-grid-1x2' },
  { label: 'Clients', path: '/clients', icon: 'bi-people' },
  { label: 'Companies', path: '/companies', icon: 'bi-building' },
  { label: 'Projects', path: '/projects', icon: 'bi-kanban' },
  { label: 'Edits', path: '/edits', icon: 'bi-pencil-square' },
]

function SidebarNavigation({ isOpen, isSigningOut, onClose, onLogout, user }) {
  const initials = user?.name
    ?.split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase() || 'AD'

  return (
    <>
      {isOpen && (
        <button
          aria-label="Close navigation menu"
          className="sidebar-backdrop"
          onClick={onClose}
          type="button"
        />
      )}
      <aside className={`app-sidebar ${isOpen ? 'is-open' : ''}`} id="app-navigation">
        <div className="sidebar-brand">
          <span className="brand-mark" aria-hidden="true">
            <i className="bi bi-layers" />
            <span />
          </span>
          <span className="brand-name">
            <strong>Client Edits</strong>
            <small>MANAGEMENT SYSTEM</small>
          </span>
          <button
            aria-label="Close navigation menu"
            className="sidebar-close"
            onClick={onClose}
            type="button"
          >
            <i className="bi bi-x-lg" aria-hidden="true" />
          </button>
        </div>

        <span className="sidebar-section-label">WORKSPACE</span>
        <nav aria-label="Main navigation" className="sidebar-links">
          {navigationItems.map(({ label, path, icon }) => (
            <NavLink
              className={({ isActive }) => `sidebar-link${isActive ? ' is-active' : ''}`}
              end={path !== '/clients'}
              key={path}
              onClick={onClose}
              to={path}
            >
              <i className={`bi ${icon}`} aria-hidden="true" />
              <span>{label}</span>
            </NavLink>
          ))}
        </nav>

        <div className="sidebar-profile">
          <NavLink
            className={({ isActive }) => `sidebar-account-link${isActive ? ' is-active' : ''}`}
            onClick={onClose}
            to="/account"
          >
            <div className="admin-avatar" aria-hidden="true">{initials}</div>
            <div className="admin-details">
              <strong>{user?.name || 'Administrator'}</strong>
              <span>Administrator</span>
            </div>
          </NavLink>
          <button
            aria-label="Sign out"
            className="sidebar-logout"
            disabled={isSigningOut}
            onClick={onLogout}
            title="Sign out"
            type="button"
          >
            <i className={`bi ${isSigningOut ? 'bi-hourglass-split' : 'bi-door-open'}`} aria-hidden="true" />
          </button>
        </div>
      </aside>
    </>
  )
}

export default SidebarNavigation
