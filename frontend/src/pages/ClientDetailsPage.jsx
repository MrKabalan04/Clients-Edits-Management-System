import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'

import ClientFormModal from '../components/ClientFormModal.jsx'
import api from '../services/api.js'
import { displayValue, formatDate, labelForValue } from '../utils/clientFormatters.js'

const statisticCards = [
  { key: 'companies', label: 'Companies', icon: 'bi-building' },
  { key: 'projects', label: 'Projects', icon: 'bi-kanban' },
  { key: 'edits', label: 'Total edits', icon: 'bi-pencil-square' },
  { key: 'pending_edits', label: 'Pending', icon: 'bi-hourglass-split', tone: 'orange' },
  { key: 'completed_edits', label: 'Completed', icon: 'bi-check2-circle', tone: 'green' },
]

function ClientDetailsPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [details, setDetails] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [actionError, setActionError] = useState('')
  const [attachmentError, setAttachmentError] = useState('')
  const [isEditing, setIsEditing] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)

  async function loadDetails() {
    setIsLoading(true)
    setLoadError('')

    try {
      const { data } = await api.get(`/clients/${id}/details`)
      setDetails(data.data)
    } catch (error) {
      if (error.response?.status === 404) {
        setLoadError('This client could not be found.')
      } else {
        setLoadError('We couldn’t load this client profile. Check your connection and try again.')
      }
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadDetails()
  }, [id])

  async function deleteClient() {
    if (!window.confirm(`Delete ${details.client.name}? This action cannot be undone.`)) {
      return
    }

    setIsDeleting(true)
    setActionError('')
    try {
      await api.delete(`/clients/${id}`)
      navigate('/clients', { replace: true })
    } catch (error) {
      setActionError(error.response?.status === 409
        ? 'This client still has associated companies and can’t be deleted.'
        : 'We couldn’t delete this client. Please try again.')
    } finally {
      setIsDeleting(false)
    }
  }

  function updateClient(client) {
    setDetails((current) => ({ ...current, client }))
    setIsEditing(false)
  }

  async function openAttachment(attachment) {
    const previewWindow = window.open('', '_blank')
    setAttachmentError('')

    try {
      const { data } = await api.get(`/attachments/${attachment.id}`, {
        responseType: 'blob',
      })
      const objectUrl = URL.createObjectURL(data)
      const canPreview = attachment.mime_type?.startsWith('image/') ||
        attachment.mime_type === 'application/pdf'

      if (canPreview && previewWindow) {
        previewWindow.location.replace(objectUrl)
      } else {
        previewWindow?.close()
        const downloadLink = document.createElement('a')
        downloadLink.href = objectUrl
        downloadLink.download = attachment.file_name
        document.body.append(downloadLink)
        downloadLink.click()
        downloadLink.remove()
      }

      window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
    } catch (error) {
      previewWindow?.close()
      setAttachmentError(error.response?.status === 401
        ? 'Your session expired. Sign in again to view attachments.'
        : 'We couldn’t open this attachment. Please try again.')
    }
  }

  if (isLoading) {
    return (
      <section className="client-details-state" role="status">
        <span className="login-spinner" aria-hidden="true" />
        Loading client profile…
      </section>
    )
  }

  if (loadError || !details) {
    return (
      <section className="client-details-state client-details-state-error" role="alert">
        <i className="bi bi-exclamation-circle" aria-hidden="true" />
        <strong>{loadError || 'We couldn’t load this client profile.'}</strong>
        <div className="client-detail-state-actions">
          <Link className="client-button client-button-secondary" to="/clients">Back to clients</Link>
          {loadError.includes('connection') && (
            <button className="client-button client-button-primary" onClick={loadDetails} type="button">Try again</button>
          )}
        </div>
      </section>
    )
  }

  const { client, statistics, companies, projects, recent_edits: recentEdits } = details

  return (
    <section className="client-details-page">
      <Link className="client-back-link" to="/clients">
        Clients directory
      </Link>

      <header className="client-profile-header">
        <div className="client-profile-identity">
          <span className="client-profile-avatar" aria-hidden="true">
            {client.name.trim().charAt(0).toUpperCase()}
          </span>
          <div>
            <span className="eyebrow"><span /> CLIENT PROFILE</span>
            <h1>{client.name}</h1>
            <div className="client-contact-summary">
              <span><i className="bi bi-telephone" aria-hidden="true" /> {client.phone}</span>
              {client.email && <span><i className="bi bi-envelope" aria-hidden="true" /> {client.email}</span>}
              <span><i className="bi bi-calendar3" aria-hidden="true" /> Client since {formatDate(client.created_at)}</span>
            </div>
          </div>
        </div>
        <div className="client-profile-actions">
          <button className="client-button client-button-secondary" onClick={() => setIsEditing(true)} type="button">
            <i className="bi bi-pencil" aria-hidden="true" />
            Edit client
          </button>
          <button
            className="client-button client-button-danger"
            disabled={isDeleting}
            onClick={deleteClient}
            type="button"
          >
            <i className="bi bi-trash3" aria-hidden="true" />
            {isDeleting ? 'Deleting…' : 'Delete'}
          </button>
        </div>
      </header>

      {actionError && <p className="client-page-alert" role="alert">{actionError}</p>}

      <section aria-label="Client overview statistics" className="client-statistics">
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

      <section className="client-detail-section personal-information">
        <header className="client-section-heading">
          <div>
            <span className="section-kicker">PROFILE</span>
            <h2>Personal information</h2>
          </div>
          <button className="client-text-action" onClick={() => setIsEditing(true)} type="button">
            Edit information
          </button>
        </header>
        <dl className="client-information-grid">
          <div><dt>Full name</dt><dd>{client.name}</dd></div>
          <div><dt>Phone number</dt><dd>{client.phone}</dd></div>
          <div><dt>Email address</dt><dd>{displayValue(client.email)}</dd></div>
          <div><dt>Last follow-up</dt><dd>{formatDate(client.last_follow_up_date)}</dd></div>
          <div className="is-wide"><dt>Address</dt><dd>{displayValue(client.address)}</dd></div>
          <div className="is-wide"><dt>Notes</dt><dd className="client-notes">{displayValue(client.notes)}</dd></div>
        </dl>
      </section>

      <section className="client-detail-section">
        <header className="client-section-heading">
          <div>
            <span className="section-kicker">BUSINESS</span>
            <h2>Companies <span className="section-count">{companies.length}</span></h2>
          </div>
        </header>
        {companies.length ? (
          <div className="client-company-grid">
            {companies.map((company) => (
              <article className="client-company-card" key={company.id}>
                <span className="client-list-icon"><i className="bi bi-building" aria-hidden="true" /></span>
                <div className="client-company-info">
                  <strong>{company.name}</strong>
                  <span>{company.projects_count} {company.projects_count === 1 ? 'project' : 'projects'}</span>
                </div>
                <span className="client-company-edit-count">
                  <strong>{company.edits_count}</strong>
                  <small>{company.edits_count === 1 ? 'edit' : 'edits'}</small>
                </span>
              </article>
            ))}
          </div>
        ) : (
          <div className="client-inline-empty">
            <i className="bi bi-building" aria-hidden="true" />
            <span>No companies are associated with this client yet.</span>
          </div>
        )}
      </section>

      <section className="client-detail-section">
        <header className="client-section-heading">
          <div>
            <span className="section-kicker">WORK</span>
            <h2>Projects <span className="section-count">{projects.length}</span></h2>
          </div>
        </header>
        {projects.length ? (
          <div className="client-project-list">
            {projects.map((project) => (
              <article className="client-project-row" key={project.id}>
                <span className="client-list-icon project-icon"><i className="bi bi-kanban" aria-hidden="true" /></span>
                <div className="client-project-info">
                  <strong>{project.name}</strong>
                  <span><i className="bi bi-building" aria-hidden="true" /> {project.company.name}</span>
                </div>
                <span className="client-project-edit-count">
                  <strong>{project.edits_count}</strong>
                  <small>{project.edits_count === 1 ? 'edit' : 'edits'}</small>
                </span>
              </article>
            ))}
          </div>
        ) : (
          <div className="client-inline-empty">
            <i className="bi bi-kanban" aria-hidden="true" />
            <span>No projects are associated with this client yet.</span>
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
        {attachmentError && <p className="client-page-alert" role="alert">{attachmentError}</p>}
        {recentEdits.length ? (
          <div className="client-edits-list">
            {recentEdits.map((edit) => (
              <article className="client-edit-card" key={edit.id}>
                <header className="client-edit-card-heading">
                  <div className="client-edit-title">
                    <strong>{edit.title}</strong>
                    <span>{edit.project?.name} <i className="bi bi-dot" aria-hidden="true" /> {edit.project?.company?.name}</span>
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
                  <span><i className="bi bi-cash-coin" aria-hidden="true" /> {edit.price === null ? 'No price set' : Number(edit.price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                  <span className={`edit-payment${edit.is_paid ? ' is-paid' : ''}`}>
                    <i className={`bi ${edit.is_paid ? 'bi-check-circle' : 'bi-clock-history'}`} aria-hidden="true" />
                    {edit.is_paid ? 'Paid' : 'Unpaid'}
                  </span>
                </div>
                {edit.attachments?.length > 0 && (
                  <div className="client-edit-attachments" aria-label="Edit attachments">
                    {edit.attachments.map((attachment) => (
                      <button
                        key={attachment.id}
                        onClick={() => openAttachment(attachment)}
                        type="button"
                      >
                        <i className="bi bi-paperclip" aria-hidden="true" />
                        {attachment.file_name}
                      </button>
                    ))}
                  </div>
                )}
              </article>
            ))}
          </div>
        ) : (
          <div className="client-inline-empty">
            <i className="bi bi-pencil-square" aria-hidden="true" />
            <span>No edits have been created for this client’s projects yet.</span>
          </div>
        )}
        {statistics.edits > recentEdits.length && (
          <p className="recent-edits-note">Showing the {recentEdits.length} most recently updated edits.</p>
        )}
      </section>

      {isEditing && (
        <ClientFormModal
          client={client}
          onClose={() => setIsEditing(false)}
          onSaved={updateClient}
        />
      )}
    </section>
  )
}

export default ClientDetailsPage
