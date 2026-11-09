import { useRef, useState } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'

import { useAuth } from '../context/AuthContext.jsx'

function LoginPage() {
  const { authError, isAuthenticated, isLoading, login } = useAuth()
  const navigate = useNavigate()
  const submissionInProgress = useRef(false)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordVisible, setPasswordVisible] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})

  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />
  }

  if (isLoading) {
    return <main className="auth-loading" role="status">Checking your session…</main>
  }

  async function handleSubmit(event) {
    event.preventDefault()

    if (submissionInProgress.current) {
      return
    }

    const errors = {}
    if (!email.trim()) {
      errors.email = 'Enter your email address.'
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
      errors.email = 'Enter a valid email address.'
    }
    if (!password) {
      errors.password = 'Enter your password.'
    }

    setFieldErrors(errors)
    setFormError('')

    if (Object.keys(errors).length > 0) {
      return
    }

    submissionInProgress.current = true
    setIsSubmitting(true)

    try {
      await login(email.trim(), password)
      navigate('/dashboard', { replace: true })
    } catch (error) {
      const status = error.response?.status
      if (status === 401) {
        setFormError('That email and password combination does not match our records.')
      } else if (status === 403) {
        setFormError('This account does not have administrator access.')
      } else if (status === 422) {
        setFormError('Check your email and password, then try again.')
      } else if (error.request && !error.response) {
        setFormError('We couldn’t reach the server. Check your connection and try again.')
      } else {
        setFormError('We couldn’t sign you in just now. Please try again.')
      }
    } finally {
      submissionInProgress.current = false
      setIsSubmitting(false)
    }
  }

  return (
    <main className="login-page">
      <div className="login-shell">
        <header className="brand-lockup" aria-label="Client Edits Management System">
          <span className="brand-mark" aria-hidden="true">
            <i className="bi bi-layers" />
            <span />
          </span>
          <span className="brand-name">
            <strong>Client Edits</strong>
            <small>MANAGEMENT SYSTEM</small>
          </span>
        </header>

        <section className="login-card" aria-labelledby="login-title">
          <div className="login-card-accent" aria-hidden="true" />
          <div className="login-heading">
            <span className="eyebrow"><span /> ADMIN WORKSPACE</span>
            <h1 id="login-title">Welcome back.</h1>
            <p>Sign in to pick up where your next great edit begins.</p>
          </div>

          <form onSubmit={handleSubmit} noValidate>
            {(formError || authError) && (
              <div className="form-alert" role="alert">
                <i className="bi bi-exclamation-circle" aria-hidden="true" />
                <span>{formError || authError}</span>
              </div>
            )}

            <div className="form-field">
              <label htmlFor="email">Email address</label>
              <div className={`input-wrap ${fieldErrors.email ? 'has-error' : ''}`}>
                <i className="bi bi-envelope" aria-hidden="true" />
                <input
                  autoComplete="username"
                  aria-describedby={fieldErrors.email ? 'email-error' : undefined}
                  aria-invalid={Boolean(fieldErrors.email)}
                  disabled={isSubmitting}
                  id="email"
                  name="email"
                  onChange={(event) => {
                    setEmail(event.target.value)
                    setFieldErrors((current) => ({ ...current, email: '' }))
                    setFormError('')
                  }}
                  placeholder="you@company.com"
                  type="email"
                  value={email}
                />
              </div>
              {fieldErrors.email && <span className="field-error" id="email-error">{fieldErrors.email}</span>}
            </div>

            <div className="form-field">
              <label htmlFor="password">Password</label>
              <div className={`input-wrap ${fieldErrors.password ? 'has-error' : ''}`}>
                <i className="bi bi-lock" aria-hidden="true" />
                <input
                  autoComplete="current-password"
                  aria-describedby={fieldErrors.password ? 'password-error' : undefined}
                  aria-invalid={Boolean(fieldErrors.password)}
                  disabled={isSubmitting}
                  id="password"
                  name="password"
                  onChange={(event) => {
                    setPassword(event.target.value)
                    setFieldErrors((current) => ({ ...current, password: '' }))
                    setFormError('')
                  }}
                  placeholder="Enter your password"
                  type={passwordVisible ? 'text' : 'password'}
                  value={password}
                />
                <button
                  aria-label={passwordVisible ? 'Hide password' : 'Show password'}
                  className="password-toggle"
                  disabled={isSubmitting}
                  onClick={() => setPasswordVisible((visible) => !visible)}
                  type="button"
                >
                  <i className={`bi ${passwordVisible ? 'bi-eye-slash' : 'bi-eye'}`} aria-hidden="true" />
                </button>
              </div>
              {fieldErrors.password && <span className="field-error" id="password-error">{fieldErrors.password}</span>}
            </div>

            <button className="login-submit" disabled={isSubmitting} type="submit">
              {isSubmitting ? (
                <>
                  <span className="login-spinner" aria-hidden="true" />
                  Signing in…
                </>
              ) : (
                <>
                  Sign in
                  <i className="bi bi-arrow-up-right" aria-hidden="true" />
                </>
              )}
            </button>
          </form>

          <div className="login-card-footer">
            <span><i className="bi bi-shield-check" aria-hidden="true" /> Secure administrator access</span>
            <span>Need help? Contact your system administrator</span>
          </div>
        </section>

        <footer className="page-footer">
          <span>Thoughtful work, beautifully managed.</span>
          <span>© {new Date().getFullYear()} Client Edits</span>
        </footer>
      </div>

      <div className="page-decoration" aria-hidden="true">
        <div className="decoration-paper decoration-paper-back" />
        <div className="decoration-paper decoration-paper-front">
          <span />
          <span />
          <span />
          <i className="bi bi-pencil" />
        </div>
        <span className="decoration-dot" />
      </div>
    </main>
  )
}

export default LoginPage
