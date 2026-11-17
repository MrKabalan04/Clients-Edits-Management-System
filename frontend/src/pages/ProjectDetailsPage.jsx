import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'

import ProjectFormModal from '../components/ProjectFormModal.jsx'
import api from '../services/api.js'
import { formatDate, formatMoney, labelForValue } from '../utils/clientFormatters.js'

const editStatuses = [
  { value: 'pending', label: 'Pending' },
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'done', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
]

const priorities = [
  { value: 'high', label: 'High' },
  { value: 'medium', label: 'Medium' },
  { value: 'low', label: 'Low' },
]

const workloadCards = [
  { key: 'pending_edits', label: 'Pending', icon: 'bi-hourglass-split', tone: 'orange' },
  { key: 'confirmed_edits', label: 'Confirmed', icon: 'bi-check2', tone: 'blue' },
  { key: 'in_progress_edits', label: 'In progress', icon: 'bi-arrow-repeat', tone: 'blue' },
  { key: 'completed_edits', label: 'Completed', icon: 'bi-check2-circle', tone: 'green' },
  { key: 'cancelled_edits', label: 'Cancelled', icon: 'bi-x-circle', tone: 'muted' },
  { key: 'edits', label: 'Total edits', icon: 'bi-pencil-square' },
]

function ProjectDetailsPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [details, setDetails] = useState(null)
  const [companies, setCompanies] = useState([])
  const [statusFilter, setStatusFilter] = useState('')
  const [priorityFilter, setPriorityFilter] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [actionError, setActionError] = useState('')
  const [isEditing, setIsEditing] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)

  async function loadDetails() {
    setIsLoading(true)
    setLoadError('')

    try {
      const [detailsResponse, companiesResponse] = await Promise.all([
        api.get(`/projects/${id}/details`),
        api.get('/companies'),
      ])
      setDetails(detailsResponse.data.data)
      setCompanies(companiesResponse.data.data)
    } catch (error) {
      setLoadError(error.response?.status === 404
        ? 'This project could not be found.'
        : 'We couldn’t load this project. Check your connection and try again.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadDetails()
  }, [id])

  async function deleteProject() {
    if (!window.confirm(`Delete ${details.project.name}? This action cannot be undone.`)) {
      return
    }

    setIsDeleting(true)
    setActionError('')
    try {
      await api.delete(`/projects/${id}`)
      navigate('/projects', { replace: true })
    } catch (error) {
      setActionError(error.response?.status === 409
        ? 'This project still has associated edits and can’t be deleted.'
        : 'We couldn’t delete this project. Please try again.')
    } finally {
      setIsDeleting(false)
    }
  }

  function updateProject(project) {
    setDetails((current) => ({
      ...current,
      project: { ...current.project, ...project },
      company: project.company,
      client: project.company.client,
    }))
    setIsEditing(false)
  }

  const filteredEdits = useMemo(() => (
    (details?.edits || []).filter((edit) => (
      (!statusFilter || edit.status === statusFilter) &&
      (!priorityFilter || edit.priority === priorityFilter)
    ))
  ), [details, statusFilter, priorityFilter])

  if (isLoading) {
    return (
      <section className="client-details-state" role="status">
        <span className="login-spinner" aria-hidden="true" />
        Loading project…
      </section>
    )
  }

  if (loadError || !details) {
    return (
      <section className="client-details-state client-details-state-error" role="alert">
        <i className="bi bi-exclamation-circle" aria-hidden="true" />
        <strong>{loadError || 'We couldn’t load this project.'}</strong>
        <div className="client-detail-state-actions">
          <Link className="client-button client-button-secondary" to="/projects">Back to projects</Link>
          {loadError.includes('connection') && (
            <button className="client-button client-button-primary" onClick={loadDetails} type="button">Try again</button>
          )}
        </div>
      </section>
    )
  }

  const { project, company, client, statistics, edits } = details

  return (
    <section className="client-details-page project-details-page">
      <Link className="client-back-link" to="/projects">Projects directory</Link>

      <header className="client-profile-header">
        <div className="client-profile-identity">
          <span className="client-profile-avatar" aria-hidden="true">
            <i className="bi bi-kanban" />
          </span>
          <div>
            <span className="eyebrow"><span /> PROJECT PROFILE</span>
            <h1>{project.name}</h1>
            <div className="client-contact-summary">
              <span><i className="bi bi-building" aria-hidden="true" /> <Link to={`/companies/${company.id}`}>{company.name}</Link></span>
              <span><i className="bi bi-person" aria-hidden="true" /> <Link to={`/clients/${client.id}`}>{client.name}</Link></span>
              <span><i className="bi bi-calendar3" aria-hidden="true" /> Created {formatDate(project.created_at)}</span>
            </div>
          </div>
        </div>
        <div className="client-profile-actions">
          <button className="client-button client-button-secondary" onClick={() => setIsEditing(true)} type="button">
            <i className="bi bi-pencil" aria-hidden="true" />
            Edit project
          </button>
          <button
            className="client-button client-button-danger"
            disabled={isDeleting}
            onClick={deleteProject}
            type="button"
          >
            <i className="bi bi-trash3" aria-hidden="true" />
            {isDeleting ? 'Deleting…' : 'Delete'}
          </button>
        </div>
      </header>

      {actionError && <p className="client-page-alert" role="alert">{actionError}</p>}

      <section aria-label="Project financial overview" className="project-financial-cards">
        <article className="project-financial-card">
          <span className="project-financial-icon"><i className="bi bi-cash-stack" aria-hidden="true" /></span>
          <div><span>Total project value</span><strong>{formatMoney(statistics.total_value)}</strong></div>
        </article>
        <article className="project-financial-card is-paid">
          <span className="project-financial-icon"><i className="bi bi-check-circle" aria-hidden="true" /></span>
          <div><span>Paid amount</span><strong>{formatMoney(statistics.paid_amount)}</strong></div>
        </article>
        <article className="project-financial-card is-outstanding">
          <span className="project-financial-icon"><i className="bi bi-clock-history" aria-hidden="true" /></span>
          <div><span>Outstanding amount</span><strong>{formatMoney(statistics.outstanding_amount)}</strong></div>
        </article>
      </section>
      <p className="project-financial-note">Totals include edits with a recorded price; unpaid edits contribute to the outstanding amount.</p>

      <section aria-label="Project edit statistics" className="client-statistics project-workload-statistics">
        {workloadCards.map(({ key, label, icon, tone }) => (
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
            <span className="section-kicker">RELATIONSHIPS</span>
            <h2>Company &amp; client</h2>
          </div>
        </header>
        <div className="project-related-grid">
          <article className="project-related-card">
            <span className="client-list-icon"><i className="bi bi-building" aria-hidden="true" /></span>
            <div>
              <span>Company</span>
              <Link to={`/companies/${company.id}`}>{company.name}</Link>
            </div>
          </article>
          <article className="project-related-card">
            <span className="client-list-icon project-icon"><i className="bi bi-person" aria-hidden="true" /></span>
            <div>
              <span>Client</span>
              <Link to={`/clients/${client.id}`}>{client.name}</Link>
              <small>
                {client.phone || 'Phone not provided'}
                {client.email ? ` · ${client.email}` : ' · Email not provided'}
              </small>
            </div>
          </article>
        </div>
      </section>

      <section className="client-detail-section project-edits-section">
        <header className="client-section-heading project-edits-heading">
          <div>
            <span className="section-kicker">WORKLOAD</span>
            <h2>Project edits <span className="section-count">{edits.length}</span></h2>
          </div>
          <div className="project-edit-filters">
            <label>
              <span className="visually-hidden">Filter edits by status</span>
              <select onChange={(event) => setStatusFilter(event.target.value)} value={statusFilter}>
                <option value="">All statuses</option>
                {editStatuses.map((status) => (
                  <option key={status.value} value={status.value}>{status.label}</option>
                ))}
              </select>
            </label>
            <label>
              <span className="visually-hidden">Filter edits by priority</span>
              <select onChange={(event) => setPriorityFilter(event.target.value)} value={priorityFilter}>
                <option value="">All priorities</option>
                {priorities.map((priority) => (
                  <option key={priority.value} value={priority.value}>{priority.label}</option>
                ))}
              </select>
            </label>
          </div>
        </header>
        {filteredEdits.length ? (
          <div className="client-edits-list">
            {filteredEdits.map((edit) => (
              <article className="client-edit-card project-edit-card" key={edit.id}>
                <header className="client-edit-card-heading">
                  <div className="client-edit-title">
                    <strong>{edit.title}</strong>
                    {edit.description && <p className="project-edit-description">{edit.description}</p>}
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
                  <span><i className="bi bi-paperclip" aria-hidden="true" /> {edit.attachments_count} {edit.attachments_count === 1 ? 'attachment' : 'attachments'}</span>
                </div>
              </article>
            ))}
          </div>
        ) : (
          <div className="client-inline-empty">
            <i className="bi bi-pencil-square" aria-hidden="true" />
            <span>{edits.length
              ? 'No edits match the selected status and priority.'
              : 'No edits have been added to this project yet.'}</span>
          </div>
        )}
      </section>

      {isEditing && (
        <ProjectFormModal
          companies={companies}
          onClose={() => setIsEditing(false)}
          onSaved={updateProject}
          project={project}
        />
      )}
    </section>
  )
}

export default ProjectDetailsPage
