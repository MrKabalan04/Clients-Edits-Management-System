import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import ClientFormModal from '../components/ClientFormModal.jsx'
import api from '../services/api.js'
import { formatDate } from '../utils/clientFormatters.js'

const pageSize = 10
const sortableColumns = [
  { key: 'name', label: 'Client' },
  { key: 'phone', label: 'Phone' },
  { key: 'email', label: 'Email' },
  { key: 'companies_count', label: 'Companies', numeric: true },
  { key: 'projects_count', label: 'Projects', numeric: true },
  { key: 'edits_count', label: 'Edits', numeric: true },
  { key: 'last_follow_up_date', label: 'Last follow-up' },
  { key: 'created_at', label: 'Created' },
]

function ClientsPage() {
  const navigate = useNavigate()
  const [clients, setClients] = useState([])
  const [search, setSearch] = useState('')
  const [sort, setSort] = useState({ key: 'name', direction: 'asc' })
  const [page, setPage] = useState(1)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [actionError, setActionError] = useState('')
  const [editingClient, setEditingClient] = useState(null)
  const [isCreating, setIsCreating] = useState(false)
  const [deletingId, setDeletingId] = useState(null)

  async function loadClients() {
    setIsLoading(true)
    setLoadError('')

    try {
      const { data } = await api.get('/clients')
      setClients(data.data)
    } catch {
      setLoadError('We couldn’t load your clients. Check your connection and try again.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadClients()
  }, [])

  const filteredClients = useMemo(() => {
    const term = search.trim().toLowerCase()
    const filtered = clients.filter((client) => (
      !term ||
      [client.name, client.phone, client.email]
        .some((value) => value?.toLowerCase().includes(term))
    ))

    return filtered.sort((left, right) => {
      const leftValue = left[sort.key] ?? ''
      const rightValue = right[sort.key] ?? ''
      const comparison = sort.numeric
        ? Number(leftValue) - Number(rightValue)
        : String(leftValue).localeCompare(String(rightValue), undefined, {
          numeric: true,
          sensitivity: 'base',
        })

      return sort.direction === 'asc' ? comparison : -comparison
    })
  }, [clients, search, sort])

  const totalPages = Math.max(1, Math.ceil(filteredClients.length / pageSize))
  const pageClients = filteredClients.slice((page - 1) * pageSize, page * pageSize)

  function handleSort(key, numeric = false) {
    setSort((current) => ({
      key,
      numeric,
      direction: current.key === key && current.direction === 'asc' ? 'desc' : 'asc',
    }))
  }

  function saveClient(savedClient) {
    if (isCreating) {
      setPage(1)
      setSearch('')
      setSort({ key: 'created_at', direction: 'desc' })
    }

    setClients((current) => (
      isCreating
        ? [savedClient, ...current]
        : current.map((client) => client.id === savedClient.id
          ? { ...client, ...savedClient }
          : client)
    ))
    setActionError('')
    setIsCreating(false)
    setEditingClient(null)
  }

  async function deleteClient(client) {
    if (!window.confirm(`Delete ${client.name}? This action cannot be undone.`)) {
      return
    }

    setDeletingId(client.id)
    setActionError('')
    try {
      await api.delete(`/clients/${client.id}`)
      setClients((current) => current.filter((entry) => entry.id !== client.id))
      const lastPage = Math.max(1, Math.ceil((clients.length - 1) / pageSize))
      setPage((currentPage) => Math.min(currentPage, lastPage))
    } catch (error) {
      setActionError(error.response?.status === 409
        ? `${client.name} still has associated companies and can’t be deleted.`
        : 'We couldn’t delete this client. Please try again.')
    } finally {
      setDeletingId(null)
    }
  }

  function openClient(client) {
    navigate(`/clients/${client.id}`)
  }

  return (
    <section className="clients-page">
      <header className="clients-page-header">
        <div>
          <span className="eyebrow"><span /> CLIENT RELATIONSHIPS</span>
          <h1>Clients</h1>
          <p>Keep client information and their work together in one place.</p>
        </div>
        <button className="client-button client-button-primary add-client-button" onClick={() => setIsCreating(true)} type="button">
          <i className="bi bi-plus-lg" aria-hidden="true" />
          Add client
        </button>
      </header>

      {actionError && <p className="client-page-alert" role="alert">{actionError}</p>}

      <section className="clients-directory-card" aria-label="Clients directory">
        <div className="clients-toolbar">
          <div className="clients-result-count">
            <strong>{filteredClients.length}</strong>
            <span>{filteredClients.length === 1 ? 'client' : 'clients'}</span>
          </div>
          <label className="clients-search">
            <i className="bi bi-search" aria-hidden="true" />
            <span className="visually-hidden">Search clients</span>
            <input
              onChange={(event) => {
                setSearch(event.target.value)
                setPage(1)
              }}
              placeholder="Search name, phone or email"
              type="search"
              value={search}
            />
            {search && (
              <button aria-label="Clear search" onClick={() => setSearch('')} type="button">
                <i className="bi bi-x" aria-hidden="true" />
              </button>
            )}
          </label>
        </div>

        {isLoading ? (
          <div className="clients-state" role="status">
            <span className="login-spinner" aria-hidden="true" />
            Loading clients…
          </div>
        ) : loadError ? (
          <div className="clients-state clients-state-error" role="alert">
            <i className="bi bi-exclamation-circle" aria-hidden="true" />
            <p>{loadError}</p>
            <button className="client-button client-button-secondary" onClick={loadClients} type="button">Try again</button>
          </div>
        ) : filteredClients.length === 0 ? (
          <div className="clients-state">
            <span className="clients-empty-icon"><i className="bi bi-people" aria-hidden="true" /></span>
            <strong>{search ? 'No matching clients' : 'No clients yet'}</strong>
            <p>{search ? 'Try another name, phone number, or email.' : 'Add a client to start building their profile.'}</p>
            {!search && (
              <button className="client-button client-button-primary" onClick={() => setIsCreating(true)} type="button">
                Add your first client
              </button>
            )}
          </div>
        ) : (
          <>
            <div className="clients-table-scroll">
              <table className="clients-table">
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
                  {pageClients.map((client) => (
                    <tr
                      key={client.id}
                      onClick={() => openClient(client)}
                      onKeyDown={(event) => {
                        if (event.target !== event.currentTarget) {
                          return
                        }

                        if (event.key === 'Enter' || event.key === ' ') {
                          event.preventDefault()
                          openClient(client)
                        }
                      }}
                      tabIndex={0}
                    >
                      <td>
                        <span className="client-name-cell">
                          <span className="client-row-avatar" aria-hidden="true">
                            {client.name.trim().charAt(0).toUpperCase()}
                          </span>
                          <strong>{client.name}</strong>
                        </span>
                      </td>
                      <td>{client.phone}</td>
                      <td>{client.email || <span className="muted-value">—</span>}</td>
                      <td><span className="client-count">{client.companies_count ?? 0}</span></td>
                      <td><span className="client-count">{client.projects_count ?? 0}</span></td>
                      <td><span className="client-count">{client.edits_count ?? 0}</span></td>
                      <td>{formatDate(client.last_follow_up_date)}</td>
                      <td>{formatDate(client.created_at)}</td>
                      <td>
                        <div className="client-row-actions" onClick={(event) => event.stopPropagation()}>
                          <button
                            aria-label={`View ${client.name}`}
                            className="client-icon-button"
                            onClick={() => openClient(client)}
                            title="View client"
                            type="button"
                          >
                            <i className="bi bi-eye" aria-hidden="true" />
                          </button>
                          <button
                            aria-label={`Edit ${client.name}`}
                            className="client-icon-button"
                            onClick={() => setEditingClient(client)}
                            title="Edit client"
                            type="button"
                          >
                            <i className="bi bi-pencil" aria-hidden="true" />
                          </button>
                          <button
                            aria-label={`Delete ${client.name}`}
                            className="client-icon-button is-danger"
                            disabled={deletingId === client.id}
                            onClick={() => deleteClient(client)}
                            title="Delete client"
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
            {filteredClients.length > pageSize && (
              <footer className="clients-pagination">
                <span>Showing {(page - 1) * pageSize + 1}–{Math.min(page * pageSize, filteredClients.length)} of {filteredClients.length}</span>
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

      {(isCreating || editingClient) && (
        <ClientFormModal
          client={editingClient}
          onClose={() => {
            setIsCreating(false)
            setEditingClient(null)
          }}
          onSaved={saveClient}
        />
      )}
    </section>
  )
}

export default ClientsPage
