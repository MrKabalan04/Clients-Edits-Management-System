import { useEffect, useMemo, useState } from 'react'

import api from '../services/api.js'
import { editPriorities, editStatuses } from '../utils/editOptions.js'

function EditFormModal({ edit, projects, onClose, onSaved }) {
  const isEditing = Boolean(edit)
  const [values, setValues] = useState({
    title: edit?.title || '',
    description: edit?.description || '',
    project_id: edit?.project_id ? String(edit.project_id) : '',
    priority: edit?.priority || 'medium',
    status: edit?.status || 'pending',
    price: edit?.price ?? '',
    is_paid: edit?.is_paid ? 'true' : 'false',
    due_date: edit?.due_date?.slice(0, 10) || '',
  })
  const [projectSearch, setProjectSearch] = useState('')
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  useEffect(() => {
    function handleKeyDown(event) {
      if (event.key === 'Escape' && !isSaving) {
        onClose()
      }
    }

    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [isSaving, onClose])

  const filteredProjects = useMemo(() => {
    const term = projectSearch.trim().toLowerCase()
    const matches = projects.filter((project) => (
      !term || [
        project.name,
        project.company?.name,
        project.company?.client?.name,
      ].some((value) => value?.toLowerCase().includes(term))
    ))
    const selectedProject = projects.find((project) => String(project.id) === values.project_id)

    return selectedProject && !matches.some((project) => project.id === selectedProject.id)
      ? [selectedProject, ...matches]
      : matches
  }, [projects, projectSearch, values.project_id])

  function updateField(name, value) {
    setValues((current) => ({ ...current, [name]: value }))
    setErrors((current) => ({ ...current, [name]: '' }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setFormError('')

    const nextErrors = {}
    if (!values.title.trim()) {
      nextErrors.title = 'Enter the edit title.'
    }
    if (!values.project_id) {
      nextErrors.project_id = 'Select a project.'
    }
    if (values.price !== '') {
      const price = Number(values.price)
      if (
        !/^(?:\d+\.?\d{0,2}|\.\d{1,2})$/.test(values.price) ||
        !Number.isFinite(price) ||
        price < 0 ||
        price > 99999999.99
      ) {
        nextErrors.price = 'Enter a non-negative amount with up to 2 decimal places.'
      }
    }
    setErrors(nextErrors)
    if (Object.keys(nextErrors).length) {
      return
    }

    setIsSaving(true)
    const payload = {
      title: values.title.trim(),
      description: values.description.trim() || null,
      project_id: Number(values.project_id),
      priority: values.priority,
      status: values.status,
      price: values.price === '' ? null : Number(values.price),
      is_paid: values.is_paid === 'true',
      due_date: values.due_date || null,
    }

    try {
      const response = isEditing
        ? await api.put(`/edits/${edit.id}`, payload)
        : await api.post('/edits', payload)
      onSaved(response.data.data)
    } catch (error) {
      if (error.response?.status === 422) {
        const serverErrors = error.response.data?.errors || {}
        setErrors(Object.fromEntries(
          Object.entries(serverErrors).map(([field, messages]) => [
            field,
            Array.isArray(messages) ? messages[0] : 'Please check this field.',
          ]),
        ))
        setFormError('Please review the highlighted information.')
      } else {
        setFormError('We couldn’t save this edit. Please try again.')
      }
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="client-modal-backdrop">
      <section
        aria-labelledby="edit-form-title"
        aria-modal="true"
        className="client-modal edit-form-modal"
        role="dialog"
      >
        <header className="client-modal-header">
          <div>
            <span className="eyebrow"><span /> EDIT WORK</span>
            <h2 id="edit-form-title">{isEditing ? 'Edit work item' : 'Add edit'}</h2>
          </div>
          <button
            aria-label="Close dialog"
            className="client-modal-close"
            disabled={isSaving}
            onClick={onClose}
            type="button"
          >
            <i className="bi bi-x-lg" aria-hidden="true" />
          </button>
        </header>

        <form className="client-form" noValidate onSubmit={handleSubmit}>
          {formError && <p className="client-form-alert" role="alert">{formError}</p>}
          {projects.length === 0 && (
            <p className="client-form-alert" role="alert">Add a project before creating an edit.</p>
          )}
          <div className="client-form-grid">
            <div className="client-form-field is-wide">
              <label htmlFor="edit-title">Edit title <span aria-hidden="true">*</span></label>
              <input
                aria-describedby={errors.title ? 'edit-title-error' : undefined}
                aria-invalid={Boolean(errors.title)}
                disabled={isSaving}
                id="edit-title"
                maxLength={255}
                name="title"
                onChange={(event) => updateField('title', event.target.value)}
                required
                value={values.title}
              />
              {errors.title && <span className="field-error" id="edit-title-error">{errors.title}</span>}
            </div>
            <div className="client-form-field is-wide">
              <label htmlFor="edit-description">Description</label>
              <textarea
                disabled={isSaving}
                id="edit-description"
                name="description"
                onChange={(event) => updateField('description', event.target.value)}
                rows={3}
                value={values.description}
              />
            </div>
            <div className="client-form-field is-wide">
              <label htmlFor="edit-project-search">Search projects</label>
              <input
                autoComplete="off"
                disabled={isSaving || projects.length === 0}
                id="edit-project-search"
                onChange={(event) => setProjectSearch(event.target.value)}
                placeholder="Search project, company or client"
                type="search"
                value={projectSearch}
              />
              <label className="edit-project-select-label" htmlFor="edit-project">
                Project <span aria-hidden="true">*</span>
              </label>
              <select
                aria-describedby={errors.project_id ? 'edit-project-error' : undefined}
                aria-invalid={Boolean(errors.project_id)}
                disabled={isSaving || projects.length === 0}
                id="edit-project"
                name="project_id"
                onChange={(event) => updateField('project_id', event.target.value)}
                required
                value={values.project_id}
              >
                <option value="">Select a project</option>
                {filteredProjects.map((project) => (
                  <option key={project.id} value={project.id}>
                    {project.name} — {project.company?.name || 'Company unavailable'} · {project.company?.client?.name || 'Client unavailable'}
                  </option>
                ))}
              </select>
              {errors.project_id && <span className="field-error" id="edit-project-error">{errors.project_id}</span>}
            </div>
            <div className="client-form-field">
              <label htmlFor="edit-status">Status <span aria-hidden="true">*</span></label>
              <select
                disabled={isSaving}
                id="edit-status"
                onChange={(event) => updateField('status', event.target.value)}
                required
                value={values.status}
              >
                {editStatuses.map((status) => (
                  <option key={status.value} value={status.value}>{status.label}</option>
                ))}
              </select>
            </div>
            <div className="client-form-field">
              <label htmlFor="edit-priority">Priority <span aria-hidden="true">*</span></label>
              <select
                disabled={isSaving}
                id="edit-priority"
                onChange={(event) => updateField('priority', event.target.value)}
                required
                value={values.priority}
              >
                {editPriorities.map((priority) => (
                  <option key={priority.value} value={priority.value}>{priority.label}</option>
                ))}
              </select>
            </div>
            <div className="client-form-field">
              <label htmlFor="edit-price">Price</label>
              <input
                aria-describedby={errors.price ? 'edit-price-error' : undefined}
                aria-invalid={Boolean(errors.price)}
                disabled={isSaving}
                id="edit-price"
                min="0"
                name="price"
                onChange={(event) => updateField('price', event.target.value)}
                placeholder="0.00"
                step="0.01"
                type="number"
                value={values.price}
              />
              {errors.price && <span className="field-error" id="edit-price-error">{errors.price}</span>}
            </div>
            <div className="client-form-field">
              <label htmlFor="edit-due-date">Due date</label>
              <input
                disabled={isSaving}
                id="edit-due-date"
                name="due_date"
                onChange={(event) => updateField('due_date', event.target.value)}
                type="date"
                value={values.due_date}
              />
            </div>
            <div className="client-form-field">
              <label htmlFor="edit-payment">Payment status</label>
              <select
                disabled={isSaving}
                id="edit-payment"
                onChange={(event) => updateField('is_paid', event.target.value)}
                value={values.is_paid}
              >
                <option value="false">Unpaid</option>
                <option value="true">Paid</option>
              </select>
            </div>
          </div>
          <footer className="client-modal-actions">
            <button className="client-button client-button-secondary" disabled={isSaving} onClick={onClose} type="button">
              Cancel
            </button>
            <button className="client-button client-button-primary" disabled={isSaving || projects.length === 0} type="submit">
              {isSaving ? 'Saving…' : isEditing ? 'Save changes' : 'Add edit'}
            </button>
          </footer>
        </form>
      </section>
    </div>
  )
}

export default EditFormModal
