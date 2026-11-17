import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import ProjectFormModal from '../components/ProjectFormModal.jsx'
import api from '../services/api.js'
import { formatDate } from '../utils/clientFormatters.js'

const pageSize = 10
const sortableColumns = [
  { key: 'name', label: 'Project' },
  { key: 'company.name', label: 'Company' },
  { key: 'company.client.name', label: 'Client' },
  { key: 'edits_count', label: 'Edits', numeric: true },
  { key: 'pending_edits_count', label: 'Pending', numeric: true },
  { key: 'completed_edits_count', label: 'Completed', numeric: true },
  { key: 'created_at', label: 'Created' },
]

function ProjectsPage() {
  const navigate = useNavigate()
  const [projects, setProjects] = useState([])
  const [companies, setCompanies] = useState([])
  const [search, setSearch] = useState('')
  const [companyFilter, setCompanyFilter] = useState('')
  const [clientFilter, setClientFilter] = useState('')
  const [sort, setSort] = useState({ key: 'name', direction: 'asc' })
  const [page, setPage] = useState(1)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [actionError, setActionError] = useState('')
  const [editingProject, setEditingProject] = useState(null)
  const [isCreating, setIsCreating] = useState(false)
  const [deletingId, setDeletingId] = useState(null)

  async function loadProjects() {
    setIsLoading(true)
    setLoadError('')

    try {
      const [projectsResponse, companiesResponse] = await Promise.all([
        api.get('/projects'),
        api.get('/companies'),
      ])
      setProjects(projectsResponse.data.data)
      setCompanies(companiesResponse.data.data)
    } catch {
      setLoadError('We couldn’t load your projects. Check your connection and try again.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadProjects()
  }, [])

  const clients = useMemo(() => (
    [...new Map(companies
      .filter((company) => company.client)
      .map((company) => [company.client.id, company.client])).values()]
      .sort((left, right) => left.name.localeCompare(right.name))
  ), [companies])

  const filteredProjects = useMemo(() => {
    const term = search.trim().toLowerCase()
    return projects
      .filter((project) => (
        (!term || [
          project.name,
          project.company?.name,
          project.company?.client?.name,
        ].some((value) => value?.toLowerCase().includes(term))) &&
        (!companyFilter || String(project.company_id) === companyFilter) &&
        (!clientFilter || String(project.company?.client_id) === clientFilter)
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
  }, [projects, search, companyFilter, clientFilter, sort])

  const totalPages = Math.max(1, Math.ceil(filteredProjects.length / pageSize))
  const pageProjects = filteredProjects.slice((page - 1) * pageSize, page * pageSize)

  function handleSort(key, numeric = false) {
    setSort((current) => ({
      key,
      numeric,
      direction: current.key === key && current.direction === 'asc' ? 'desc' : 'asc',
    }))
  }

  function saveProject(savedProject) {
    if (isCreating) {
      setPage(1)
      setSearch('')
      setCompanyFilter('')
      setClientFilter('')
      setSort({ key: 'created_at', direction: 'desc' })
      setProjects((current) => [{
        ...savedProject,
        edits_count: 0,
        pending_edits_count: 0,
        completed_edits_count: 0,
      }, ...current])
    } else {
      setProjects((current) => current.map((project) => project.id === savedProject.id
        ? { ...project, ...savedProject }
        : project))
    }
    setActionError('')
    setIsCreating(false)
    setEditingProject(null)
  }

  async function deleteProject(project) {
    if (!window.confirm(`Delete ${project.name}? This action cannot be undone.`)) {
      return
    }

    setDeletingId(project.id)
    setActionError('')
    try {
      await api.delete(`/projects/${project.id}`)
      setProjects((current) => current.filter((entry) => entry.id !== project.id))
      const lastPage = Math.max(1, Math.ceil((projects.length - 1) / pageSize))
      setPage((currentPage) => Math.min(currentPage, lastPage))
    } catch (error) {
      setActionError(error.response?.status === 409
        ? `${project.name} still has associated edits and can’t be deleted.`
        : 'We couldn’t delete this project. Please try again.')
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <section className="clients-page">
      <header className="clients-page-header">
        <div>
          <span className="eyebrow"><span /> PROJECT DIRECTORY</span>
          <h1>Projects</h1>
          <p>Track the work and edits connected to each company and client.</p>
        </div>
        <button
          className="client-button client-button-primary add-client-button"
          onClick={() => setIsCreating(true)}
          type="button"
        >
          <i className="bi bi-plus-lg" aria-hidden="true" />
          Add project
        </button>
      </header>

      {actionError && <p className="client-page-alert" role="alert">{actionError}</p>}

      <section aria-label="Projects directory" className="clients-directory-card">
        <div className="clients-toolbar projects-toolbar">
          <div className="clients-result-count">
            <strong>{filteredProjects.length}</strong>
            <span>{filteredProjects.length === 1 ? 'project' : 'projects'}</span>
          </div>
          <div className="projects-filters">
            <label className="clients-search">
              <i className="bi bi-search" aria-hidden="true" />
              <span className="visually-hidden">Search projects, companies or clients</span>
              <input
                onChange={(event) => {
                  setSearch(event.target.value)
                  setPage(1)
                }}
                placeholder="Search projects, companies or clients"
                type="search"
                value={search}
              />
              {search && (
                <button aria-label="Clear search" onClick={() => setSearch('')} type="button">
                  <i className="bi bi-x" aria-hidden="true" />
                </button>
              )}
            </label>
            <label className="project-filter-select">
              <span className="visually-hidden">Filter by company</span>
              <i className="bi bi-building" aria-hidden="true" />
              <select
                onChange={(event) => {
                  setCompanyFilter(event.target.value)
                  setPage(1)
                }}
                value={companyFilter}
              >
                <option value="">All companies</option>
                {companies.map((company) => (
                  <option key={company.id} value={company.id}>{company.name}</option>
                ))}
              </select>
            </label>
            <label className="project-filter-select">
              <span className="visually-hidden">Filter by client</span>
              <i className="bi bi-person" aria-hidden="true" />
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
            Loading projects…
          </div>
        ) : loadError ? (
          <div className="clients-state clients-state-error" role="alert">
            <i className="bi bi-exclamation-circle" aria-hidden="true" />
            <p>{loadError}</p>
            <button className="client-button client-button-secondary" onClick={loadProjects} type="button">Try again</button>
          </div>
        ) : filteredProjects.length === 0 ? (
          <div className="clients-state">
            <span className="clients-empty-icon"><i className="bi bi-kanban" aria-hidden="true" /></span>
            <strong>{search || companyFilter || clientFilter ? 'No matching projects' : 'No projects yet'}</strong>
            <p>{search || companyFilter || clientFilter
              ? 'Try another search or adjust the company and client filters.'
              : 'Add a project to start organizing work for a company.'}</p>
            {!search && !companyFilter && !clientFilter && companies.length > 0 && (
              <button className="client-button client-button-primary" onClick={() => setIsCreating(true)} type="button">
                Add your first project
              </button>
            )}
          </div>
        ) : (
          <>
            <div className="clients-table-scroll">
              <table className="clients-table projects-table">
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
                  {pageProjects.map((project) => (
                    <tr
                      key={project.id}
                      onClick={() => navigate(`/projects/${project.id}`)}
                      onKeyDown={(event) => {
                        if (event.target === event.currentTarget && (event.key === 'Enter' || event.key === ' ')) {
                          event.preventDefault()
                          navigate(`/projects/${project.id}`)
                        }
                      }}
                      tabIndex={0}
                    >
                      <td>
                        <span className="client-name-cell">
                          <span className="client-row-avatar project-row-icon" aria-hidden="true">
                            <i className="bi bi-kanban" />
                          </span>
                          <strong>{project.name}</strong>
                        </span>
                      </td>
                      <td>{project.company?.name || <span className="muted-value">—</span>}</td>
                      <td>{project.company?.client?.name || <span className="muted-value">—</span>}</td>
                      <td><span className="client-count">{project.edits_count ?? 0}</span></td>
                      <td><span className="project-count is-pending">{project.pending_edits_count ?? 0}</span></td>
                      <td><span className="project-count is-completed">{project.completed_edits_count ?? 0}</span></td>
                      <td>{formatDate(project.created_at)}</td>
                      <td>
                        <div className="client-row-actions" onClick={(event) => event.stopPropagation()}>
                          <button
                            aria-label={`View ${project.name}`}
                            className="client-icon-button"
                            onClick={() => navigate(`/projects/${project.id}`)}
                            title="View project"
                            type="button"
                          >
                            <i className="bi bi-eye" aria-hidden="true" />
                          </button>
                          <button
                            aria-label={`Edit ${project.name}`}
                            className="client-icon-button"
                            onClick={() => setEditingProject(project)}
                            title="Edit project"
                            type="button"
                          >
                            <i className="bi bi-pencil" aria-hidden="true" />
                          </button>
                          <button
                            aria-label={`Delete ${project.name}`}
                            className="client-icon-button is-danger"
                            disabled={deletingId === project.id}
                            onClick={() => deleteProject(project)}
                            title="Delete project"
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
            {filteredProjects.length > pageSize && (
              <footer className="clients-pagination">
                <span>Showing {(page - 1) * pageSize + 1}–{Math.min(page * pageSize, filteredProjects.length)} of {filteredProjects.length}</span>
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

      {(isCreating || editingProject) && (
        <ProjectFormModal
          companies={companies}
          onClose={() => {
            setIsCreating(false)
            setEditingProject(null)
          }}
          onSaved={saveProject}
          project={editingProject}
        />
      )}
    </section>
  )
}

export default ProjectsPage
