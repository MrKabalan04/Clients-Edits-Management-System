import { useEffect, useState } from 'react'

import api from '../services/api.js'

function CompanyFormModal({ clients, company, onClose, onSaved }) {
  const isEditing = Boolean(company)
  const [name, setName] = useState(company?.name || '')
  const [clientId, setClientId] = useState(company?.client_id ? String(company.client_id) : '')
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
      nextErrors.name = 'Enter the company name.'
    }
    if (!clientId) {
      nextErrors.client_id = 'Select an associated client.'
    }
    setErrors(nextErrors)
    if (Object.keys(nextErrors).length) {
      return
    }

    setIsSaving(true)
    try {
      const payload = { name: name.trim(), client_id: Number(clientId) }
      const response = isEditing
        ? await api.put(`/companies/${company.id}`, payload)
        : await api.post('/companies', payload)
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
        setFormError('We couldn’t save this company. Please try again.')
      }
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="client-modal-backdrop">
      <section
        aria-labelledby="company-form-title"
        aria-modal="true"
        className="client-modal"
        role="dialog"
      >
        <header className="client-modal-header">
          <div>
            <span className="eyebrow"><span /> COMPANY PROFILE</span>
            <h2 id="company-form-title">{isEditing ? 'Edit company' : 'Add company'}</h2>
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
          {clients.length === 0 && (
            <p className="client-form-alert" role="alert">Add a client before creating a company.</p>
          )}
          <div className="client-form-grid">
            <div className="client-form-field">
              <label htmlFor="company-name">Company name <span aria-hidden="true">*</span></label>
              <input
                aria-describedby={errors.name ? 'company-name-error' : undefined}
                aria-invalid={Boolean(errors.name)}
                autoComplete="organization"
                disabled={isSaving}
                id="company-name"
                name="name"
                onChange={(event) => {
                  setName(event.target.value)
                  setErrors((current) => ({ ...current, name: '' }))
                }}
                required
                value={name}
              />
              {errors.name && <span className="field-error" id="company-name-error">{errors.name}</span>}
            </div>
            <div className="client-form-field">
              <label htmlFor="company-client">Associated client <span aria-hidden="true">*</span></label>
              <select
                aria-describedby={errors.client_id ? 'company-client-error' : undefined}
                aria-invalid={Boolean(errors.client_id)}
                disabled={isSaving || clients.length === 0}
                id="company-client"
                name="client_id"
                onChange={(event) => {
                  setClientId(event.target.value)
                  setErrors((current) => ({ ...current, client_id: '' }))
                }}
                required
                value={clientId}
              >
                <option value="">Select a client</option>
                {clients.map((client) => (
                  <option key={client.id} value={client.id}>{client.name}</option>
                ))}
              </select>
              {errors.client_id && <span className="field-error" id="company-client-error">{errors.client_id}</span>}
            </div>
          </div>
          <footer className="client-modal-actions">
            <button className="client-button client-button-secondary" disabled={isSaving} onClick={onClose} type="button">
              Cancel
            </button>
            <button className="client-button client-button-primary" disabled={isSaving || clients.length === 0} type="submit">
              {isSaving ? 'Saving…' : isEditing ? 'Save changes' : 'Add company'}
            </button>
          </footer>
        </form>
      </section>
    </div>
  )
}

export default CompanyFormModal
