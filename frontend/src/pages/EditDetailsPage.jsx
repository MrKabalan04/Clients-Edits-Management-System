import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'

import EditAttachmentsPanel from '../components/EditAttachmentsPanel.jsx'
import EditFormModal from '../components/EditFormModal.jsx'
import api from '../services/api.js'
import { displayValue, formatDate, formatMoney, labelForValue } from '../utils/clientFormatters.js'
import { editPriorities, editStatuses } from '../utils/editOptions.js'

function EditDetailsPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [edit, setEdit] = useState(null)
  const [projects, setProjects] = useState([])
  const [attachments, setAttachments] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [actionError, setActionError] = useState('')
  const [isEditing, setIsEditing] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)

  async function loadEdit() {
    setIsLoading(true)
    setLoadError('')

    try {
      const [editResponse, attachmentsResponse, projectsResponse] = await Promise.all([
        api.get(`/edits/${id}`),
        api.get(`/edits/${id}/attachments`),
        api.get('/projects'),
      ])
      setEdit(editResponse.data.data)
      setAttachments(attachmentsResponse.data.data)
      setProjects(projectsResponse.data.data)
    } catch (error) {
      setLoadError(error.response?.status === 404
        ? 'This edit could not be found.'
        : 'We couldn’t load this edit. Check your connection and try again.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadEdit()
  }, [id])

  async function deleteEdit() {
    if (!window.confirm(`Delete “${edit.title}”? This action cannot be undone.`)) {
      return
    }

    setIsDeleting(true)
    setActionError('')
    try {
      await api.delete(`/edits/${id}`)
      navigate('/edits', { replace: true })
    } catch (error) {
      setActionError(error.response?.status === 409
        ? 'Delete this edit’s attachments before deleting the edit.'
        : 'We couldn’t delete this edit. Please try again.')
    } finally {
      setIsDeleting(false)
    }
  }

  function updateEdit(updatedEdit) {
    setEdit((current) => ({ ...current, ...updatedEdit }))
    setIsEditing(false)
  }

  if (isLoading) {
    return (
      <section className="client-details-state" role="status">
        <span className="login-spinner" aria-hidden="true" />
        Loading edit…
      </section>
    )
  }

  if (loadError || !edit) {
    return (
      <section className="client-details-state client-details-state-error" role="alert">
        <i className="bi bi-exclamation-circle" aria-hidden="true" />
        <strong>{loadError || 'We couldn’t load this edit.'}</strong>
        <div className="client-detail-state-actions">
          <Link className="client-button client-button-secondary" to="/edits">Back to edits</Link>
          {loadError.includes('connection') && (
            <button className="client-button client-button-primary" onClick={loadEdit} type="button">Try again</button>
          )}
        </div>
      </section>
    )
  }

  const project = edit.project
  const company = project.company
  const client = company.client
  const statusLabel = editStatuses.find((status) => status.value === edit.status)?.label || labelForValue(edit.status)
  const priorityLabel = editPriorities.find((priority) => priority.value === edit.priority)?.label || labelForValue(edit.priority)

  return (
    <section className="client-details-page edit-details-page">
      <Link className="client-back-link" to="/edits">Edits directory</Link>

      <header className="client-profile-header edit-profile-header">
        <div className="client-profile-identity">
          <span className="client-profile-avatar edit-profile-avatar" aria-hidden="true">
            <i className="bi bi-pencil-square" />
          </span>
          <div className="edit-profile-copy">
            <span className="eyebrow"><span /> EDIT PROFILE</span>
            <h1>{edit.title}</h1>
            <div className="edit-profile-badges">
              <span className="edit-attribute">
                <span>Status</span>
                <strong className={`edit-status-badge status-${edit.status}`}>{statusLabel}</strong>
              </span>
              <span className="edit-attribute">
                <span>Priority</span>
                <strong className={`edit-priority-badge priority-${edit.priority}`}>{priorityLabel}</strong>
              </span>
            </div>
            <div className="client-contact-summary edit-related-links">
              <span><i className="bi bi-kanban" aria-hidden="true" /> <Link to={`/projects/${project.id}`}>{project.name}</Link></span>
              <span><i className="bi bi-building" aria-hidden="true" /> <Link to={`/companies/${company.id}`}>{company.name}</Link></span>
              <span><i className="bi bi-person" aria-hidden="true" /> <Link to={`/clients/${client.id}`}>{client.name}</Link></span>
              <span><i className="bi bi-calendar3" aria-hidden="true" /> Created {formatDate(edit.created_at)}</span>
            </div>
          </div>
        </div>
        <div className="client-profile-actions">
          <button className="client-button client-button-secondary" onClick={() => setIsEditing(true)} type="button">
            <i className="bi bi-pencil" aria-hidden="true" />
            Edit details
          </button>
          <button
            className="client-button client-button-danger"
            disabled={isDeleting}
            onClick={deleteEdit}
            type="button"
          >
            <i className="bi bi-trash3" aria-hidden="true" />
            {isDeleting ? 'Deleting…' : 'Delete'}
          </button>
        </div>
      </header>

      {actionError && <p className="client-page-alert" role="alert">{actionError}</p>}

      <section className="client-detail-section edit-information-section">
        <header className="client-section-heading">
          <div>
            <span className="section-kicker">WORK DETAILS</span>
            <h2>Edit information</h2>
          </div>
          <button className="client-text-action" onClick={() => setIsEditing(true)} type="button">Edit information</button>
        </header>
        <dl className="client-information-grid">
          <div className="is-wide"><dt>Description</dt><dd className="edit-description-value">{displayValue(edit.description)}</dd></div>
          <div><dt>Status</dt><dd><strong className={`edit-status-badge status-${edit.status}`}>{statusLabel}</strong></dd></div>
          <div><dt>Priority</dt><dd><strong className={`edit-priority-badge priority-${edit.priority}`}>{priorityLabel}</strong></dd></div>
          <div><dt>Due date</dt><dd>{formatDate(edit.due_date)}</dd></div>
          <div><dt>Price</dt><dd>{formatMoney(edit.price)}</dd></div>
          <div><dt>Payment status</dt><dd><span className={`edit-payment${edit.is_paid ? ' is-paid' : ''}`}>{edit.is_paid ? 'Paid' : 'Unpaid'}</span></dd></div>
          <div><dt>Created</dt><dd>{formatDate(edit.created_at)}</dd></div>
          <div><dt>Last updated</dt><dd>{formatDate(edit.updated_at)}</dd></div>
        </dl>
      </section>

      <EditAttachmentsPanel
        attachments={attachments}
        editId={edit.id}
        onAttachmentsChanged={setAttachments}
      />

      {isEditing && (
        <EditFormModal
          edit={edit}
          onClose={() => setIsEditing(false)}
          onSaved={updateEdit}
          projects={projects}
        />
      )}
    </section>
  )
}

export default EditDetailsPage
