import { useEffect, useState } from 'react'

import api from '../services/api.js'

function ProjectFormModal({ companies, project, onClose, onSaved }) {
  const isEditing = Boolean(project)
  const [name, setName] = useState(project?.name || '')
  const [companyId, setCompanyId] = useState(project?.company_id ? String(project.company_id) : '')
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

  async function handleSubmit(event) {
    event.preventDefault()
    setFormError('')

    const nextErrors = {}
    if (!name.trim()) {
      nextErrors.name = 'Enter the project name.'
    }
    if (!companyId) {
      nextErrors.company_id = 'Select an associated company.'
    }
    setErrors(nextErrors)
    if (Object.keys(nextErrors).length) {
      return
    }

    setIsSaving(true)
    try {
      const payload = { name: name.trim(), company_id: Number(companyId) }
      const response = isEditing
        ? await api.put(`/projects/${project.id}`, payload)
        : await api.post('/projects', payload)
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
        setFormError('We couldn’t save this project. Please try again.')
      }
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="client-modal-backdrop">
      <section
        aria-labelledby="project-form-title"
        aria-modal="true"
        className="client-modal"
        role="dialog"
      >
        <header className="client-modal-header">
          <div>
            <span className="eyebrow"><span /> PROJECT PROFILE</span>
            <h2 id="project-form-title">{isEditing ? 'Edit project' : 'Add project'}</h2>
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
          {companies.length === 0 && (
            <p className="client-form-alert" role="alert">Add a company before creating a project.</p>
          )}
          <div className="client-form-grid">
            <div className="client-form-field">
              <label htmlFor="project-name">Project name <span aria-hidden="true">*</span></label>
              <input
                aria-describedby={errors.name ? 'project-name-error' : undefined}
                aria-invalid={Boolean(errors.name)}
                disabled={isSaving}
                id="project-name"
                name="name"
                onChange={(event) => {
                  setName(event.target.value)
                  setErrors((current) => ({ ...current, name: '' }))
                }}
                required
                value={name}
              />
              {errors.name && <span className="field-error" id="project-name-error">{errors.name}</span>}
            </div>
            <div className="client-form-field">
              <label htmlFor="project-company">Company <span aria-hidden="true">*</span></label>
              <select
                aria-describedby={errors.company_id ? 'project-company-error' : undefined}
                aria-invalid={Boolean(errors.company_id)}
                disabled={isSaving || companies.length === 0}
                id="project-company"
                name="company_id"
                onChange={(event) => {
                  setCompanyId(event.target.value)
                  setErrors((current) => ({ ...current, company_id: '' }))
                }}
                required
                value={companyId}
              >
                <option value="">Select a company</option>
                {companies.map((company) => (
                  <option key={company.id} value={company.id}>
                    {company.name} — {company.client?.name || 'Client unavailable'}
                  </option>
                ))}
              </select>
              {errors.company_id && <span className="field-error" id="project-company-error">{errors.company_id}</span>}
              <span className="project-form-hint">The client that owns each company is shown in the list.</span>
            </div>
          </div>
          <footer className="client-modal-actions">
            <button className="client-button client-button-secondary" disabled={isSaving} onClick={onClose} type="button">
              Cancel
            </button>
            <button className="client-button client-button-primary" disabled={isSaving || companies.length === 0} type="submit">
              {isSaving ? 'Saving…' : isEditing ? 'Save changes' : 'Add project'}
            </button>
          </footer>
        </form>
      </section>
    </div>
  )
}

export default ProjectFormModal
