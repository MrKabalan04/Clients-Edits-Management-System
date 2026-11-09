import { useEffect, useState } from 'react'

import api from '../services/api.js'

const emptyClient = {
  name: '',
  phone: '',
  email: '',
  address: '',
  notes: '',
  last_follow_up_date: '',
}

const fields = [
  { name: 'name', label: 'Client name', required: true },
  { name: 'phone', label: 'Phone number', required: true },
  { name: 'email', label: 'Email address', type: 'email' },
  { name: 'address', label: 'Address', multiline: true },
  { name: 'last_follow_up_date', label: 'Last follow-up date', type: 'date' },
  { name: 'notes', label: 'Notes', multiline: true },
]

function ClientFormModal({ client, onClose, onSaved }) {
  const isEditing = Boolean(client)
  const [values, setValues] = useState({
    ...emptyClient,
    ...client,
    email: client?.email || '',
    address: client?.address || '',
    notes: client?.notes || '',
    last_follow_up_date: client?.last_follow_up_date?.slice(0, 10) || '',
  })
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

  function validate() {
    const nextErrors = {}
    if (!values.name.trim()) {
      nextErrors.name = 'Enter the client name.'
    }
    if (!values.phone.trim()) {
      nextErrors.phone = 'Enter a phone number.'
    }
    if (values.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email)) {
      nextErrors.email = 'Enter a valid email address.'
    }
    setErrors(nextErrors)
    return Object.keys(nextErrors).length === 0
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setFormError('')

    if (!validate()) {
      return
    }

    setIsSaving(true)
    const payload = {
      ...values,
      name: values.name.trim(),
      phone: values.phone.trim(),
      email: values.email.trim() || null,
      address: values.address.trim() || null,
      notes: values.notes.trim() || null,
      last_follow_up_date: values.last_follow_up_date || null,
    }

    try {
      const response = isEditing
        ? await api.put(`/clients/${client.id}`, payload)
        : await api.post('/clients', payload)
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
        setFormError('We couldn’t save this client. Please try again.')
      }
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="client-modal-backdrop">
      <section
        aria-labelledby="client-form-title"
        aria-modal="true"
        className="client-modal"
        role="dialog"
      >
        <header className="client-modal-header">
          <div>
            <span className="eyebrow"><span /> CLIENT PROFILE</span>
            <h2 id="client-form-title">{isEditing ? 'Edit client' : 'Add client'}</h2>
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
          <div className="client-form-grid">
            {fields.map(({ name, label, type, required, multiline }) => (
              <div className={`client-form-field${multiline ? ' is-wide' : ''}`} key={name}>
                <label htmlFor={`client-${name}`}>
                  {label}{required && <span aria-hidden="true"> *</span>}
                </label>
                {multiline ? (
                  <textarea
                    aria-describedby={errors[name] ? `client-${name}-error` : undefined}
                    aria-invalid={Boolean(errors[name])}
                    disabled={isSaving}
                    id={`client-${name}`}
                    name={name}
                    onChange={(event) => {
                      setValues((current) => ({ ...current, [name]: event.target.value }))
                      setErrors((current) => ({ ...current, [name]: '' }))
                    }}
                    rows={name === 'notes' ? 3 : 2}
                    value={values[name] || ''}
                  />
                ) : (
                  <input
                    aria-describedby={errors[name] ? `client-${name}-error` : undefined}
                    aria-invalid={Boolean(errors[name])}
                    autoComplete={name === 'name'
                      ? 'name'
                      : name === 'email'
                        ? 'email'
                        : name === 'phone'
                          ? 'tel'
                          : undefined}
                    disabled={isSaving}
                    id={`client-${name}`}
                    name={name}
                    onChange={(event) => {
                      setValues((current) => ({ ...current, [name]: event.target.value }))
                      setErrors((current) => ({ ...current, [name]: '' }))
                    }}
                    required={required}
                    type={type || 'text'}
                    value={values[name] || ''}
                  />
                )}
                {errors[name] && (
                  <span className="field-error" id={`client-${name}-error`}>{errors[name]}</span>
                )}
              </div>
            ))}
          </div>
          <footer className="client-modal-actions">
            <button className="client-button client-button-secondary" disabled={isSaving} onClick={onClose} type="button">
              Cancel
            </button>
            <button className="client-button client-button-primary" disabled={isSaving} type="submit">
              {isSaving ? 'Saving…' : isEditing ? 'Save changes' : 'Add client'}
            </button>
          </footer>
        </form>
      </section>
    </div>
  )
}

export default ClientFormModal
