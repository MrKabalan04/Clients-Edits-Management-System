import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'

import CompanyFormModal from '../components/CompanyFormModal.jsx'
import api from '../services/api.js'
import { formatDate, formatMoney, labelForValue } from '../utils/clientFormatters.js'

const statisticCards = [
  { key: 'projects', label: 'Projects', icon: 'bi-kanban' },
  { key: 'edits', label: 'Total edits', icon: 'bi-pencil-square' },
  { key: 'pending_edits', label: 'Pending', icon: 'bi-hourglass-split', tone: 'orange' },
  { key: 'completed_edits', label: 'Completed', icon: 'bi-check2-circle', tone: 'green' },
]

function CompanyDetailsPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [details, setDetails] = useState(null)
  const [clients, setClients] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [actionError, setActionError] = useState('')
  const [isEditing, setIsEditing] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)

  async function loadDetails() {
    setIsLoading(true)
    setLoadError('')

    try {
      const [detailsResponse, clientsResponse] = await Promise.all([
        api.get(`/companies/${id}/details`),
        api.get('/clients'),
      ])
      setDetails(detailsResponse.data.data)
      setClients(clientsResponse.data.data)
    } catch (error) {
      setLoadError(error.response?.status === 404
        ? 'This company could not be found.'
        : 'We couldn’t load this company profile. Check your connection and try again.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadDetails()
  }, [id])

  async function deleteCompany() {
    if (!window.confirm(`Delete ${details.company.name}? This action cannot be undone.`)) {
      return
    }

    setIsDeleting(true)
    setActionError('')
    try {
      await api.delete(`/companies/${id}`)
      navigate('/companies', { replace: true })
    } catch (error) {
      setActionError(error.response?.status === 409
        ? 'This company still has associated projects and can’t be deleted.'
        : 'We couldn’t delete this company. Please try again.')
    } finally {
      setIsDeleting(false)
    }
  }

  function updateCompany(company) {
    setDetails((current) => ({
      ...current,
      company: { ...current.company, ...company },
      client: company.client,
    }))
    setIsEditing(false)
  }

  if (isLoading) {
    return (
      <section className="client-details-state" role="status">
        <span className="login-spinner" aria-hidden="true" />
        Loading company profile…
      </section>
    )
  }

  if (loadError || !details) {
    return (
      <section className="client-details-state client-details-state-error" role="alert">
        <i className="bi bi-exclamation-circle" aria-hidden="true" />
        <strong>{loadError || 'We couldn’t load this company profile.'}</strong>
        <div className="client-detail-state-actions">
          <Link className="client-button client-button-secondary" to="/companies">Back to companies</Link>
          {loadError.includes('connection') && (
            <button className="client-button client-button-primary" onClick={loadDetails} type="button">Try again</button>
          )}
        </div>
      </section>
    )
  }

  const { company, client, statistics, projects, recent_edits: recentEdits } = details

  return (
    <section className="client-details-page">
      <Link className="client-back-link" to="/companies">Companies directory</Link>

      <header className="client-profile-header">
        <div className="client-profile-identity">
          <span className="client-profile-avatar" aria-hidden="true">
            {company.name.trim().charAt(0).toUpperCase()}
          </span>
          <div>
            <span className="eyebrow"><span /> COMPANY PROFILE</span>
            <h1>{company.name}</h1>
            <div className="client-contact-summary">
              <span><i className="bi bi-person" aria-hidden="true" /> Client <Link to={`/clients/${client.id}`}>{client.name}</Link></span>
              <span><i className="bi bi-calendar3" aria-hidden="true" /> Company since {formatDate(company.created_at)}</span>
            </div>
          </div>
        </div>
        <div className="client-profile-actions">
          <button className="client-button client-button-secondary" onClick={() => setIsEditing(true)} type="button">
            <i className="bi bi-pencil" aria-hidden="true" />
            Edit company
          </button>
          <button
            className="client-button client-button-danger"
            disabled={isDeleting}
            onClick={deleteCompany}
            type="button"
          >
            <i className="bi bi-trash3" aria-hidden="true" />
            {isDeleting ? 'Deleting…' : 'Delete'}
          </button>
        </div>
      </header>

      {actionError && <p className="client-page-alert" role="alert">{actionError}</p>}

      <section aria-label="Company overview statistics" className="client-statistics company-statistics">
        {statisticCards.map(({ key, label, icon, tone }) => (
          <article className={`client-stat-card${tone ? ` tone-${tone}` : ''}`} key={key}>
            <span className="client-stat-icon"><i className={`bi ${icon}`} aria-hidden="true" /></span>
            <div>
              <strong>{statistics[key]}</strong>
              <span>{label}</span>
            </div>
          </article>
        ))}
      </section>

      <section className="client-detail-section">
        <header className="client-section-heading">
          <div>
            <span className="section-kicker">PROFILE</span>
            <h2>Company information</h2>
          </div>
          <button className="client-text-action" onClick={() => setIsEditing(true)} type="button">
            Edit information
          </button>
        </header>
        <dl className="client-information-grid">
          <div><dt>Company name</dt><dd>{company.name}</dd></div>
          <div><dt>Associated client</dt><dd><Link to={`/clients/${client.id}`}>{client.name}</Link></dd></div>
          <div><dt>Created</dt><dd>{formatDate(company.created_at)}</dd></div>
          <div><dt>Last updated</dt><dd>{formatDate(company.updated_at)}</dd></div>
        </dl>
      </section>

      <section className="client-detail-section">
        <header className="client-section-heading">
          <div>
            <span className="section-kicker">WORKSPACE</span>
            <h2>Projects <span className="section-count">{projects.length}</span></h2>
          </div>
        </header>
        {projects.length ? (
          <div className="client-project-list">
            {projects.map((project) => (
              <article className="client-project-row company-project-row" key={project.id}>
                <span className="client-list-icon project-icon"><i className="bi bi-kanban" aria-hidden="true" /></span>
                <div className="client-project-info">
                  <strong>{project.name}</strong>
                  <span>Created {formatDate(project.created_at)}</span>
                </div>
                <div className="company-project-metrics">
                  <span><strong>{project.edits_count}</strong> edits</span>
                  <span><strong>{project.pending_edits_count}</strong> pending</span>
                  <span><strong>{project.completed_edits_count}</strong> completed</span>
                </div>
              </article>
            ))}
          </div>
        ) : (
          <div className="client-inline-empty">
            <i className="bi bi-kanban" aria-hidden="true" />
            <span>No projects are associated with this company yet.</span>
          </div>
        )}
      </section>

      <section className="client-detail-section recent-edits-section">
        <header className="client-section-heading">
          <div>
            <span className="section-kicker">LATEST ACTIVITY</span>
            <h2>Recent edits <span className="section-count">{recentEdits.length}</span></h2>
          </div>
        </header>
        {recentEdits.length ? (
          <div className="client-edits-list">
            {recentEdits.map((edit) => (
              <article className="client-edit-card" key={edit.id}>
                <header className="client-edit-card-heading">
                  <div className="client-edit-title">
                    <strong>{edit.title}</strong>
                    <span>{edit.project?.name}</span>
                  </div>
                  <div className="client-edit-badges">
                    <span className="edit-attribute">
                      <span>Status</span>
                      <strong className={`edit-status-badge status-${edit.status}`}>
                        {labelForValue(edit.status)}
                      </strong>
                    </span>
                    <span className="edit-attribute">
                      <span>Priority</span>
                      <strong className={`edit-priority-badge priority-${edit.priority}`}>
                        {labelForValue(edit.priority)}
                      </strong>
                    </span>
                  </div>
                </header>
                <div className="client-edit-meta">
                  <span><i className="bi bi-calendar-event" aria-hidden="true" /> Due {formatDate(edit.due_date)}</span>
                  <span><i className="bi bi-cash-coin" aria-hidden="true" /> {formatMoney(edit.price)}</span>
                  <span className={`edit-payment${edit.is_paid ? ' is-paid' : ''}`}>
                    <i className={`bi ${edit.is_paid ? 'bi-check-circle' : 'bi-clock-history'}`} aria-hidden="true" />
                    {edit.is_paid ? 'Paid' : 'Unpaid'}
                  </span>
                </div>
              </article>
            ))}
          </div>
        ) : (
          <div className="client-inline-empty">
            <i className="bi bi-pencil-square" aria-hidden="true" />
            <span>No edits have been created for this company’s projects yet.</span>
          </div>
        )}
        {statistics.edits > recentEdits.length && (
          <p className="recent-edits-note">Showing the {recentEdits.length} most recently updated edits.</p>
        )}
      </section>

      {isEditing && (
        <CompanyFormModal
          clients={clients}
          company={company}
          onClose={() => setIsEditing(false)}
          onSaved={updateCompany}
        />
      )}
    </section>
  )
}

export default CompanyDetailsPage
