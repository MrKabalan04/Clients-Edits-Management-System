import { useState } from 'react'

import { useAuth } from '../context/AuthContext.jsx'
import api from '../services/api.js'

const passwordFields = [
  { name: 'current_password', label: 'Current password', autocomplete: 'current-password' },
  { name: 'new_password', label: 'New password', autocomplete: 'new-password' },
  { name: 'new_password_confirmation', label: 'Confirm new password', autocomplete: 'new-password' },
]

function AccountPage() {
  const { user } = useAuth()
  const [passwords, setPasswords] = useState({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
  })
  const [visibleFields, setVisibleFields] = useState({})
  const [fieldErrors, setFieldErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [successMessage, setSuccessMessage] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setFormError('')
    setSuccessMessage('')

    const errors = {}
    passwordFields.forEach(({ name }) => {
      if (!passwords[name]) {
        errors[name] = 'This field is required.'
      }
    })

    if (passwords.new_password && passwords.new_password.length < 8) {
      errors.new_password = 'Your new password must be at least 8 characters.'
    }

    if (
      passwords.new_password &&
      passwords.new_password_confirmation &&
      passwords.new_password !== passwords.new_password_confirmation
    ) {
      errors.new_password_confirmation = 'The password confirmation does not match.'
    }

    setFieldErrors(errors)
    if (Object.keys(errors).length > 0) {
      return
    }

    setIsSubmitting(true)

    try {
      const { data } = await api.put('/admin/password', passwords)
      setPasswords({
        current_password: '',
        new_password: '',
        new_password_confirmation: '',
      })
      setVisibleFields({})
      setFieldErrors({})
      setSuccessMessage(data.message || 'Your password has been updated.')
    } catch (error) {
      if (error.response?.status === 422) {
        const errorsFromServer = error.response.data?.errors || {}
        const messages = Object.fromEntries(
          Object.entries(errorsFromServer).map(([field, messagesForField]) => [
            field,
            Array.isArray(messagesForField) ? messagesForField[0] : 'Please check this field.',
          ]),
        )
        setFieldErrors(messages)
        setFormError('Please review the highlighted password fields.')
      } else {
        setFormError('We couldn’t update your password. Please try again.')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <section className="account-page" aria-labelledby="account-title">
      <header className="account-page-heading">
        <span className="eyebrow"><span /> ADMIN WORKSPACE</span>
        <h1 id="account-title">Admin Account</h1>
        <p>Manage your account details and sign-in password.</p>
      </header>

      <section className="account-card account-details-card" aria-labelledby="account-details-title">
        <div className="account-card-heading">
          <span className="account-card-icon"><i className="bi bi-person-badge" aria-hidden="true" /></span>
          <div>
            <h2 id="account-details-title">Account details</h2>
            <p>Your administrator profile information.</p>
          </div>
        </div>
        <dl className="account-details-list">
          <div>
            <dt>Name</dt>
            <dd>{user?.name}</dd>
          </div>
          <div>
            <dt>Email address</dt>
            <dd>{user?.email}</dd>
          </div>
        </dl>
      </section>

      <section className="account-card password-card" aria-labelledby="change-password-title">
        <div className="account-card-heading">
          <span className="account-card-icon"><i className="bi bi-shield-lock" aria-hidden="true" /></span>
          <div>
            <h2 id="change-password-title">Change password</h2>
            <p>Choose a password with at least 8 characters.</p>
          </div>
        </div>

        <form className="password-form" noValidate onSubmit={handleSubmit}>
          {formError && <p className="account-form-alert" role="alert">{formError}</p>}
          {successMessage && <p className="account-form-success" role="status">{successMessage}</p>}

          {passwordFields.map(({ name, label, autocomplete }) => (
            <div className="account-form-field" key={name}>
              <label htmlFor={name}>{label}</label>
              <div className={`account-input-wrap${fieldErrors[name] ? ' has-error' : ''}`}>
                <input
                  autoComplete={autocomplete}
                  aria-describedby={fieldErrors[name] ? `${name}-error` : undefined}
                  aria-invalid={Boolean(fieldErrors[name])}
                  disabled={isSubmitting}
                  id={name}
                  name={name}
                  onChange={(event) => {
                    setPasswords((current) => ({ ...current, [name]: event.target.value }))
                    setFieldErrors((current) => ({ ...current, [name]: '' }))
                    setFormError('')
                    setSuccessMessage('')
                  }}
                  type={visibleFields[name] ? 'text' : 'password'}
                  value={passwords[name]}
                />
                <button
                  aria-label={`${visibleFields[name] ? 'Hide' : 'Show'} ${label.toLowerCase()}`}
                  className="account-password-toggle"
                  disabled={isSubmitting}
                  onClick={() => setVisibleFields((current) => ({
                    ...current,
                    [name]: !current[name],
                  }))}
                  type="button"
                >
                  <i className={`bi ${visibleFields[name] ? 'bi-eye-slash' : 'bi-eye'}`} aria-hidden="true" />
                </button>
              </div>
              {fieldErrors[name] && (
                <span className="field-error" id={`${name}-error`}>{fieldErrors[name]}</span>
              )}
            </div>
          ))}

          <button className="account-submit" disabled={isSubmitting} type="submit">
            {isSubmitting ? (
              <>
                <span className="login-spinner" aria-hidden="true" />
                Updating password…
              </>
            ) : 'Update password'}
          </button>
        </form>
      </section>
    </section>
  )
}

export default AccountPage
