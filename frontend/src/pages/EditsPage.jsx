import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import EditFormModal from '../components/EditFormModal.jsx'
import api from '../services/api.js'
import { formatDate, formatMoney } from '../utils/clientFormatters.js'
import { editPriorities, editStatuses } from '../utils/editOptions.js'

const pageSize = 10
const sortableColumns = [
  { key: 'title', label: 'Edit' },
  { key: 'project.name', label: 'Project' },
  { key: 'project.company.name', label: 'Company' },
  { key: 'project.company.client.name', label: 'Client' },
  { key: 'status', label: 'Status' },
  { key: 'priority', label: 'Priority' },
  { key: 'due_date', label: 'Due date' },
  { key: 'price', label: 'Price', numeric: true },
  { key: 'is_paid', label: 'Payment' },
  { key: 'attachments_count', label: 'Files', numeric: true },
  { key: 'created_at', label: 'Created' },
]

function EditsPage() {
  const navigate = useNavigate()
  const [edits, setEdits] = useState([])
  const [projects, setProjects] = useState([])
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [priorityFilter, setPriorityFilter] = useState('')
  const [paymentFilter, setPaymentFilter] = useState('')
  const [projectFilter, setProjectFilter] = useState('')
  const [companyFilter, setCompanyFilter] = useState('')
  const [clientFilter, setClientFilter] = useState('')
  const [sort, setSort] = useState({ key: 'created_at', direction: 'desc' })
  const [page, setPage] = useState(1)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [actionError, setActionError] = useState('')
  const [editingEdit, setEditingEdit] = useState(null)
  const [isCreating, setIsCreating] = useState(false)
  const [deletingId, setDeletingId] = useState(null)

  async function loadEdits() {
    setIsLoading(true)
    setLoadError('')

    try {
      const [editsResponse, projectsResponse] = await Promise.all([
        api.get('/edits'),
        api.get('/projects'),
      ])
      setEdits(editsResponse.data.data)
      setProjects(projectsResponse.data.data)
    } catch {
      setLoadError('We couldn’t load your edits. Check your connection and try again.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadEdits()
  }, [])

  const companies = useMemo(() => (
    [...new Map(projects
      .filter((project) => project.company)
      .map((project) => [project.company.id, project.company])).values()]
      .sort((left, right) => left.name.localeCompare(right.name))
  ), [projects])

  const clients = useMemo(() => (
    [...new Map(companies
      .filter((company) => company.client)
      .map((company) => [company.client.id, company.client])).values()]
      .sort((left, right) => left.name.localeCompare(right.name))
  ), [companies])

  const filteredEdits = useMemo(() => {
    const term = search.trim().toLowerCase()
    return edits
      .filter((edit) => (
        (!term || [
          edit.title,
          edit.project?.name,
          edit.project?.company?.name,
          edit.project?.company?.client?.name,
        ].some((value) => value?.toLowerCase().includes(term))) &&
        (!statusFilter || edit.status === statusFilter) &&
        (!priorityFilter || edit.priority === priorityFilter) &&
        (paymentFilter === '' || String(Boolean(edit.is_paid)) === paymentFilter) &&
        (!projectFilter || String(edit.project_id) === projectFilter) &&
        (!companyFilter || String(edit.project?.company_id) === companyFilter) &&
        (!clientFilter || String(edit.project?.company?.client_id) === clientFilter)
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
  }, [
    edits,
    search,
    statusFilter,
    priorityFilter,
    paymentFilter,
    projectFilter,
    companyFilter,
    clientFilter,
    sort,
  ])

  const totalPages = Math.max(1, Math.ceil(filteredEdits.length / pageSize))
  const pageEdits = filteredEdits.slice((page - 1) * pageSize, page * pageSize)

  function handleSort(key, numeric = false) {
    setSort((current) => ({
      key,
      numeric,
      direction: current.key === key && current.direction === 'asc' ? 'desc' : 'asc',
    }))
  }

  function saveEdit(savedEdit) {
    if (isCreating) {
      setPage(1)
      setSearch('')
      setStatusFilter('')
      setPriorityFilter('')
      setPaymentFilter('')
      setProjectFilter('')
      setCompanyFilter('')
      setClientFilter('')
      setSort({ key: 'created_at', direction: 'desc' })
      setEdits((current) => [{ ...savedEdit, attachments_count: 0 }, ...current])
    } else {
      setEdits((current) => current.map((edit) => edit.id === savedEdit.id
        ? { ...edit, ...savedEdit }
        : edit))
    }
    setActionError('')
    setIsCreating(false)
    setEditingEdit(null)
  }

  async function deleteEdit(edit) {
    if (!window.confirm(`Delete “${edit.title}”? This action cannot be undone.`)) {
      return
    }

    setDeletingId(edit.id)
    setActionError('')
    try {
      await api.delete(`/edits/${edit.id}`)
      setEdits((current) => current.filter((entry) => entry.id !== edit.id))
      const lastPage = Math.max(1, Math.ceil((filteredEdits.length - 1) / pageSize))
      setPage((currentPage) => Math.min(currentPage, lastPage))
    } catch (error) {
      setActionError(error.response?.status === 409
        ? `Delete attachments from “${edit.title}” before deleting this edit.`
        : 'We couldn’t delete this edit. Please try again.')
    } finally {
      setDeletingId(null)
    }
  }

  function clearFilters() {
    setSearch('')
    setStatusFilter('')
    setPriorityFilter('')
    setPaymentFilter('')
    setProjectFilter('')
    setCompanyFilter('')
    setClientFilter('')
    setPage(1)
  }

  const hasFilters = Boolean(
    search || statusFilter || priorityFilter || paymentFilter ||
    projectFilter || companyFilter || clientFilter,
  )

  return (
    <section className="clients-page">
      <header className="clients-page-header">
        <div>
          <span className="eyebrow"><span /> EDIT WORKSPACE</span>
          <h1>Edits</h1>
          <p>Keep every request, deadline, and payment connected to its project.</p>
        </div>
        <button
          className="client-button client-button-primary add-client-button"
          onClick={() => setIsCreating(true)}
          type="button"
        >
          <i className="bi bi-plus-lg" aria-hidden="true" />
          Add edit
        </button>
      </header>

      {actionError && <p className="client-page-alert" role="alert">{actionError}</p>}

      <section aria-label="Edits directory" className="clients-directory-card">
        <div className="clients-toolbar edits-toolbar">
          <div className="clients-result-count">
            <strong>{filteredEdits.length}</strong>
            <span>{filteredEdits.length === 1 ? 'edit' : 'edits'}</span>
          </div>
          <div className="edits-filters">
            <label className="clients-search">
              <i className="bi bi-search" aria-hidden="true" />
              <span className="visually-hidden">Search edits, projects, companies or clients</span>
              <input
                onChange={(event) => {
                  setSearch(event.target.value)
                  setPage(1)
                }}
                placeholder="Search title, project, company or client"
                type="search"
                value={search}
              />
              {search && (
                <button aria-label="Clear search" onClick={() => setSearch('')} type="button">
                  <i className="bi bi-x" aria-hidden="true" />
                </button>
              )}
            </label>
            <label className="edit-filter-select">
              <span className="visually-hidden">Filter by status</span>
              <select onChange={(event) => { setStatusFilter(event.target.value); setPage(1) }} value={statusFilter}>
                <option value="">All statuses</option>
                {editStatuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
              </select>
            </label>
            <label className="edit-filter-select">
              <span className="visually-hidden">Filter by priority</span>
              <select onChange={(event) => { setPriorityFilter(event.target.value); setPage(1) }} value={priorityFilter}>
                <option value="">All priorities</option>
                {editPriorities.map((priority) => <option key={priority.value} value={priority.value}>{priority.label}</option>)}
              </select>
            </label>
            <label className="edit-filter-select">
              <span className="visually-hidden">Filter by payment status</span>
              <select onChange={(event) => { setPaymentFilter(event.target.value); setPage(1) }} value={paymentFilter}>
                <option value="">All payments</option>
                <option value="true">Paid</option>
                <option value="false">Unpaid</option>
              </select>
            </label>
            <label className="edit-filter-select">
              <span className="visually-hidden">Filter by project</span>
              <select onChange={(event) => { setProjectFilter(event.target.value); setPage(1) }} value={projectFilter}>
                <option value="">All projects</option>
                {projects.map((project) => (
                  <option key={project.id} value={project.id}>{project.name}</option>
                ))}
              </select>
            </label>
            <label className="edit-filter-select">
              <span className="visually-hidden">Filter by company</span>
              <select onChange={(event) => { setCompanyFilter(event.target.value); setPage(1) }} value={companyFilter}>
                <option value="">All companies</option>
                {companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}
              </select>
            </label>
            <label className="edit-filter-select">
              <span className="visually-hidden">Filter by client</span>
              <select onChange={(event) => { setClientFilter(event.target.value); setPage(1) }} value={clientFilter}>
                <option value="">All clients</option>
                {clients.map((client) => <option key={client.id} value={client.id}>{client.name}</option>)}
              </select>
            </label>
            {hasFilters && (
              <button className="edits-clear-filters" onClick={clearFilters} type="button">
                Clear filters
              </button>
            )}
          </div>
        </div>

        {isLoading ? (
          <div className="clients-state" role="status">
            <span className="login-spinner" aria-hidden="true" />
            Loading edits…
          </div>
        ) : loadError ? (
          <div className="clients-state clients-state-error" role="alert">
            <i className="bi bi-exclamation-circle" aria-hidden="true" />
            <p>{loadError}</p>
            <button className="client-button client-button-secondary" onClick={loadEdits} type="button">Try again</button>
          </div>
        ) : filteredEdits.length === 0 ? (
          <div className="clients-state">
            <span className="clients-empty-icon"><i className="bi bi-pencil-square" aria-hidden="true" /></span>
            <strong>{hasFilters ? 'No matching edits' : 'No edits yet'}</strong>
            <p>{hasFilters
              ? 'Try adjusting your search or filters.'
              : 'Add an edit to start tracking work for a project.'}</p>
            {hasFilters ? (
              <button className="client-button client-button-secondary" onClick={clearFilters} type="button">Clear filters</button>
            ) : projects.length > 0 && (
              <button className="client-button client-button-primary" onClick={() => setIsCreating(true)} type="button">
                Add your first edit
              </button>
            )}
          </div>
        ) : (
          <>
            <div className="clients-table-scroll">
              <table className="clients-table edits-table">
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
                  {pageEdits.map((edit) => (
                    <tr
                      key={edit.id}
                      onClick={() => navigate(`/edits/${edit.id}`)}
                      onKeyDown={(event) => {
                        if (event.target === event.currentTarget && (event.key === 'Enter' || event.key === ' ')) {
                          event.preventDefault()
                          navigate(`/edits/${edit.id}`)
                        }
                      }}
                      tabIndex={0}
                    >
                      <td>
                        <span className="edit-directory-title">
                          <strong>{edit.title}</strong>
                        </span>
                      </td>
                      <td>{edit.project?.name || <span className="muted-value">—</span>}</td>
                      <td>{edit.project?.company?.name || <span className="muted-value">—</span>}</td>
                      <td>{edit.project?.company?.client?.name || <span className="muted-value">—</span>}</td>
                      <td><strong className={`edit-status-badge status-${edit.status}`}>{editStatuses.find((status) => status.value === edit.status)?.label || edit.status}</strong></td>
                      <td><strong className={`edit-priority-badge priority-${edit.priority}`}>{editPriorities.find((priority) => priority.value === edit.priority)?.label || edit.priority}</strong></td>
                      <td>{formatDate(edit.due_date)}</td>
                      <td>{formatMoney(edit.price)}</td>
                      <td><span className={`edit-payment${edit.is_paid ? ' is-paid' : ''}`}>{edit.is_paid ? 'Paid' : 'Unpaid'}</span></td>
                      <td><span className="client-count">{edit.attachments_count ?? 0}</span></td>
                      <td>{formatDate(edit.created_at)}</td>
                      <td>
                        <div className="client-row-actions" onClick={(event) => event.stopPropagation()}>
                          <button
                            aria-label={`View ${edit.title}`}
                            className="client-icon-button"
                            onClick={() => navigate(`/edits/${edit.id}`)}
                            title="View edit"
                            type="button"
                          >
                            <i className="bi bi-eye" aria-hidden="true" />
                          </button>
                          <button
                            aria-label={`Edit ${edit.title}`}
                            className="client-icon-button"
                            onClick={() => setEditingEdit(edit)}
                            title="Edit work item"
                            type="button"
                          >
                            <i className="bi bi-pencil" aria-hidden="true" />
                          </button>
                          <button
                            aria-label={`Delete ${edit.title}`}
                            className="client-icon-button is-danger"
                            disabled={deletingId === edit.id}
                            onClick={() => deleteEdit(edit)}
                            title="Delete edit"
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
            {filteredEdits.length > pageSize && (
              <footer className="clients-pagination">
                <span>Showing {(page - 1) * pageSize + 1}–{Math.min(page * pageSize, filteredEdits.length)} of {filteredEdits.length}</span>
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

      {(isCreating || editingEdit) && (
        <EditFormModal
          edit={editingEdit}
          onClose={() => {
            setIsCreating(false)
            setEditingEdit(null)
          }}
          onSaved={saveEdit}
          projects={projects}
        />
      )}
    </section>
  )
}

export default EditsPage
