import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import CompanyFormModal from '../components/CompanyFormModal.jsx'
import api from '../services/api.js'
import { formatDate } from '../utils/clientFormatters.js'

const pageSize = 10
const sortableColumns = [
  { key: 'name', label: 'Company' },
  { key: 'client.name', label: 'Client' },
  { key: 'projects_count', label: 'Projects', numeric: true },
  { key: 'edits_count', label: 'Edits', numeric: true },
  { key: 'created_at', label: 'Created' },
]

function CompaniesPage() {
  const navigate = useNavigate()
  const [companies, setCompanies] = useState([])
  const [clients, setClients] = useState([])
  const [search, setSearch] = useState('')
  const [clientFilter, setClientFilter] = useState('')
  const [sort, setSort] = useState({ key: 'name', direction: 'asc' })
  const [page, setPage] = useState(1)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [actionError, setActionError] = useState('')
  const [editingCompany, setEditingCompany] = useState(null)
  const [isCreating, setIsCreating] = useState(false)
  const [deletingId, setDeletingId] = useState(null)

  async function loadCompanies() {
    setIsLoading(true)
    setLoadError('')

    try {
      const [companiesResponse, clientsResponse] = await Promise.all([
        api.get('/companies'),
        api.get('/clients'),
      ])
      setCompanies(companiesResponse.data.data)
      setClients(clientsResponse.data.data)
    } catch {
      setLoadError('We couldn’t load your companies. Check your connection and try again.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadCompanies()
  }, [])

  const filteredCompanies = useMemo(() => {
    const term = search.trim().toLowerCase()
    return companies
      .filter((company) => (
        (!term || [company.name, company.client?.name].some((value) => value?.toLowerCase().includes(term))) &&
        (!clientFilter || String(company.client_id) === clientFilter)
      ))
      .sort((left, right) => {
        const leftValue = sort.key.split('.').reduce((value, part) => value?.[part], left) ?? ''
        const rightValue = sort.key.split('.').reduce((value, part) => value?.[part], right) ?? ''
        const comparison = sort.numeric
          ? Number(leftValue) - Number(rightValue)
          : String(leftValue).localeCompare(String(rightValue), undefined, {
            numeric: true,
            sensitivity: 'base',
          })
        return sort.direction === 'asc' ? comparison : -comparison
      })
  }, [companies, search, clientFilter, sort])

  const totalPages = Math.max(1, Math.ceil(filteredCompanies.length / pageSize))
  const pageCompanies = filteredCompanies.slice((page - 1) * pageSize, page * pageSize)

  function handleSort(key, numeric = false) {
    setSort((current) => ({
      key,
      numeric,
      direction: current.key === key && current.direction === 'asc' ? 'desc' : 'asc',
    }))
  }

  function saveCompany(savedCompany) {
    if (isCreating) {
      setPage(1)
      setSearch('')
      setClientFilter('')
      setSort({ key: 'created_at', direction: 'desc' })
    }

    setCompanies((current) => (
      isCreating
        ? [savedCompany, ...current]
        : current.map((company) => company.id === savedCompany.id
          ? { ...company, ...savedCompany }
          : company)
    ))
    setActionError('')
    setIsCreating(false)
    setEditingCompany(null)
  }

  async function deleteCompany(company) {
    if (!window.confirm(`Delete ${company.name}? This action cannot be undone.`)) {
      return
    }

    setDeletingId(company.id)
    setActionError('')
    try {
      await api.delete(`/companies/${company.id}`)
      setCompanies((current) => current.filter((entry) => entry.id !== company.id))
      const lastPage = Math.max(1, Math.ceil((companies.length - 1) / pageSize))
      setPage((currentPage) => Math.min(currentPage, lastPage))
    } catch (error) {
      setActionError(error.response?.status === 409
        ? `${company.name} still has associated projects and can’t be deleted.`
        : 'We couldn’t delete this company. Please try again.')
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <section className="clients-page">
      <header className="clients-page-header">
        <div>
          <span className="eyebrow"><span /> COMPANY DIRECTORY</span>
          <h1>Companies</h1>
          <p>Organize each client’s businesses and the projects they manage.</p>
        </div>
        <button
          className="client-button client-button-primary add-client-button"
          onClick={() => setIsCreating(true)}
          type="button"
        >
          <i className="bi bi-plus-lg" aria-hidden="true" />
          Add company
        </button>
      </header>

      {actionError && <p className="client-page-alert" role="alert">{actionError}</p>}

      <section aria-label="Companies directory" className="clients-directory-card">
        <div className="clients-toolbar companies-toolbar">
          <div className="clients-result-count">
            <strong>{filteredCompanies.length}</strong>
            <span>{filteredCompanies.length === 1 ? 'company' : 'companies'}</span>
          </div>
          <div className="companies-filters">
            <label className="clients-search">
              <i className="bi bi-search" aria-hidden="true" />
              <span className="visually-hidden">Search companies or clients</span>
              <input
                onChange={(event) => {
                  setSearch(event.target.value)
                  setPage(1)
                }}
                placeholder="Search companies or clients"
                type="search"
                value={search}
              />
              {search && (
                <button aria-label="Clear search" onClick={() => setSearch('')} type="button">
                  <i className="bi bi-x" aria-hidden="true" />
                </button>
              )}
            </label>
            <label className="company-client-filter">
              <span className="visually-hidden">Filter by client</span>
              <i className="bi bi-funnel" aria-hidden="true" />
              <select
                onChange={(event) => {
                  setClientFilter(event.target.value)
                  setPage(1)
                }}
                value={clientFilter}
              >
                <option value="">All clients</option>
                {clients.map((client) => (
                  <option key={client.id} value={client.id}>{client.name}</option>
                ))}
              </select>
            </label>
          </div>
        </div>

        {isLoading ? (
          <div className="clients-state" role="status">
            <span className="login-spinner" aria-hidden="true" />
            Loading companies…
          </div>
        ) : loadError ? (
          <div className="clients-state clients-state-error" role="alert">
            <i className="bi bi-exclamation-circle" aria-hidden="true" />
            <p>{loadError}</p>
            <button className="client-button client-button-secondary" onClick={loadCompanies} type="button">Try again</button>
          </div>
        ) : filteredCompanies.length === 0 ? (
          <div className="clients-state">
            <span className="clients-empty-icon"><i className="bi bi-building" aria-hidden="true" /></span>
            <strong>{search || clientFilter ? 'No matching companies' : 'No companies yet'}</strong>
            <p>{search || clientFilter
              ? 'Try another company or client, or change the client filter.'
              : 'Add a company to organize its projects under a client.'}</p>
            {!search && !clientFilter && (
              <button className="client-button client-button-primary" onClick={() => setIsCreating(true)} type="button">
                Add your first company
              </button>
            )}
          </div>
        ) : (
          <>
            <div className="clients-table-scroll">
              <table className="clients-table companies-table">
                <thead>
                  <tr>
                    {sortableColumns.map(({ key, label, numeric }) => (
                      <th
                        aria-sort={sort.key === key
                          ? sort.direction === 'asc' ? 'ascending' : 'descending'
                          : 'none'}
                        key={key}
                        scope="col"
                      >
                        <button
                          aria-label={`Sort by ${label}`}
                          className="table-sort-button"
                          onClick={() => handleSort(key, numeric)}
                          type="button"
                        >
                          {label}
                        </button>
                      </th>
                    ))}
                    <th scope="col">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {pageCompanies.map((company) => (
                    <tr
                      key={company.id}
                      onClick={() => navigate(`/companies/${company.id}`)}
                      onKeyDown={(event) => {
                        if (event.target === event.currentTarget && (event.key === 'Enter' || event.key === ' ')) {
                          event.preventDefault()
                          navigate(`/companies/${company.id}`)
                        }
                      }}
                      tabIndex={0}
                    >
                      <td>
                        <span className="client-name-cell">
                          <span className="client-row-avatar" aria-hidden="true">
                            {company.name.trim().charAt(0).toUpperCase()}
                          </span>
                          <strong>{company.name}</strong>
                        </span>
                      </td>
                      <td>{company.client?.name || <span className="muted-value">—</span>}</td>
                      <td><span className="client-count">{company.projects_count ?? 0}</span></td>
                      <td><span className="client-count">{company.edits_count ?? 0}</span></td>
                      <td>{formatDate(company.created_at)}</td>
                      <td>
                        <div className="client-row-actions" onClick={(event) => event.stopPropagation()}>
                          <button
                            aria-label={`View ${company.name}`}
                            className="client-icon-button"
                            onClick={() => navigate(`/companies/${company.id}`)}
                            title="View company"
                            type="button"
                          >
                            <i className="bi bi-eye" aria-hidden="true" />
                          </button>
                          <button
                            aria-label={`Edit ${company.name}`}
                            className="client-icon-button"
                            onClick={() => setEditingCompany(company)}
                            title="Edit company"
                            type="button"
                          >
                            <i className="bi bi-pencil" aria-hidden="true" />
                          </button>
                          <button
                            aria-label={`Delete ${company.name}`}
                            className="client-icon-button is-danger"
                            disabled={deletingId === company.id}
                            onClick={() => deleteCompany(company)}
                            title="Delete company"
                            type="button"
                          >
                            <i className="bi bi-trash3" aria-hidden="true" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {filteredCompanies.length > pageSize && (
              <footer className="clients-pagination">
                <span>Showing {(page - 1) * pageSize + 1}–{Math.min(page * pageSize, filteredCompanies.length)} of {filteredCompanies.length}</span>
                <div>
                  <button
                    aria-label="Previous page"
                    disabled={page === 1}
                    onClick={() => setPage((current) => current - 1)}
                    type="button"
                  >
                    Previous
                  </button>
                  <span>Page {page} of {totalPages}</span>
                  <button
                    aria-label="Next page"
                    disabled={page === totalPages}
                    onClick={() => setPage((current) => current + 1)}
                    type="button"
                  >
                    Next
                  </button>
                </div>
              </footer>
            )}
          </>
        )}
      </section>

      {(isCreating || editingCompany) && (
        <CompanyFormModal
          clients={clients}
          company={editingCompany}
          onClose={() => {
            setIsCreating(false)
            setEditingCompany(null)
          }}
          onSaved={saveCompany}
        />
      )}
    </section>
  )
}

export default CompaniesPage
