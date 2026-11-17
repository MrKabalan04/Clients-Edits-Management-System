import { useEffect, useRef, useState } from 'react'

import api from '../services/api.js'
import { formatDate } from '../utils/clientFormatters.js'
import {
  attachmentExtensions,
  attachmentFileError,
  attachmentTypeLabel,
  formatFileSize,
  maxAttachmentBatchSize,
  maxAttachmentsPerUpload,
} from '../utils/editOptions.js'

function EditAttachmentItem({ attachment, onDeleted }) {
  const [previewUrl, setPreviewUrl] = useState('')
  const [previewError, setPreviewError] = useState('')
  const [actionError, setActionError] = useState('')
  const [isDeleting, setIsDeleting] = useState(false)
  const isImage = ['image/jpeg', 'image/png', 'image/webp'].includes(attachment.mime_type)
  const canPreview = isImage || attachment.mime_type === 'application/pdf'

  useEffect(() => {
    if (!isImage) {
      return undefined
    }

    const controller = new AbortController()
    let objectUrl = ''
    setPreviewUrl('')
    setPreviewError('')

    api.get(`/attachments/${attachment.id}`, {
      responseType: 'blob',
      signal: controller.signal,
    })
      .then(({ data }) => {
        if (!controller.signal.aborted) {
          objectUrl = URL.createObjectURL(data)
          setPreviewUrl(objectUrl)
        }
      })
      .catch(() => {
        if (!controller.signal.aborted) {
          setPreviewError('Preview unavailable')
        }
      })

    return () => {
      controller.abort()
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl)
      }
    }
  }, [attachment.id, isImage])

  async function openAttachment() {
    setActionError('')
    const previewWindow = window.open('', '_blank')
    if (previewWindow) {
      previewWindow.opener = null
    }

    try {
      const { data } = await api.get(`/attachments/${attachment.id}`, {
        responseType: 'blob',
      })
      const objectUrl = URL.createObjectURL(data)

      if (previewWindow) {
        previewWindow.location.replace(objectUrl)
      } else {
        const downloadLink = document.createElement('a')
        downloadLink.href = objectUrl
        downloadLink.target = '_blank'
        downloadLink.rel = 'noopener'
        document.body.append(downloadLink)
        downloadLink.click()
        downloadLink.remove()
      }

      window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
    } catch (error) {
      previewWindow?.close()
      setActionError(error.response?.status === 401
        ? 'Your session expired. Sign in again to access this attachment.'
        : 'We couldn’t open this attachment. Please try again.')
    }
  }

  async function downloadAttachment() {
    setActionError('')

    try {
      const { data } = await api.get(`/attachments/${attachment.id}`, {
        responseType: 'blob',
      })
      const objectUrl = URL.createObjectURL(data)
      const downloadLink = document.createElement('a')
      downloadLink.href = objectUrl
      downloadLink.download = attachment.file_name
      document.body.append(downloadLink)
      downloadLink.click()
      downloadLink.remove()
      window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
    } catch (error) {
      setActionError(error.response?.status === 401
        ? 'Your session expired. Sign in again to access this attachment.'
        : 'We couldn’t download this attachment. Please try again.')
    }
  }

  async function deleteAttachment() {
    if (!window.confirm(`Delete “${attachment.file_name}”? This cannot be undone.`)) {
      return
    }

    setIsDeleting(true)
    setActionError('')
    try {
      await api.delete(`/attachments/${attachment.id}`)
      onDeleted(attachment.id)
    } catch {
      setActionError('We couldn’t delete this attachment. Please try again.')
    } finally {
      setIsDeleting(false)
    }
  }

  return (
    <article className="edit-attachment-card">
      {isImage ? (
        <div className="edit-attachment-preview">
          {previewUrl ? (
            <img alt={`Preview of ${attachment.file_name}`} loading="lazy" src={previewUrl} />
          ) : (
            <span role={previewError ? undefined : 'status'}>
              <i className={`bi ${previewError ? 'bi-image-alt' : 'bi-hourglass-split'}`} aria-hidden="true" />
              {previewError || 'Loading preview'}
            </span>
          )}
        </div>
      ) : (
        <span className="edit-attachment-file-icon" aria-hidden="true">
          <i className={`bi ${attachment.mime_type === 'application/pdf' ? 'bi-file-earmark-pdf' : 'bi-file-earmark-text'}`} />
        </span>
      )}
      <div className="edit-attachment-info">
        <strong title={attachment.file_name}>{attachment.file_name}</strong>
        <span>{attachmentTypeLabel(attachment.file_name)} · {formatFileSize(attachment.file_size)}</span>
        <span>Uploaded {formatDate(attachment.created_at)}</span>
        {attachment.description && <small>{attachment.description}</small>}
      </div>
      <div className="edit-attachment-actions">
        {canPreview && (
          <button
            aria-label={`Open ${attachment.file_name}`}
            className="client-button client-button-secondary"
            onClick={openAttachment}
            type="button"
          >
            <i className="bi bi-box-arrow-up-right" aria-hidden="true" />
            Open
          </button>
        )}
        <button
          aria-label={`Download ${attachment.file_name}`}
          className="client-button client-button-secondary edit-attachment-download"
          onClick={downloadAttachment}
          type="button"
        >
          <i className="bi bi-download" aria-hidden="true" />
          Download
        </button>
        <button
          aria-label={`Delete ${attachment.file_name}`}
          className="client-icon-button is-danger"
          disabled={isDeleting}
          onClick={deleteAttachment}
          title="Delete attachment"
          type="button"
        >
          <i className={`bi ${isDeleting ? 'bi-hourglass-split' : 'bi-trash3'}`} aria-hidden="true" />
        </button>
      </div>
      {actionError && <p className="edit-attachment-error" role="alert">{actionError}</p>}
    </article>
  )
}

function EditAttachmentsPanel({ attachments, editId, onAttachmentsChanged }) {
  const fileInput = useRef(null)
  const [selectedFiles, setSelectedFiles] = useState([])
  const [validationError, setValidationError] = useState('')
  const [uploadError, setUploadError] = useState('')
  const [isUploading, setIsUploading] = useState(false)

  function chooseFiles(event) {
    const files = Array.from(event.target.files || [])
    const invalidFiles = files
      .map((file) => ({ file, error: attachmentFileError(file) }))
      .filter(({ error }) => error)

    setSelectedFiles(files)
    setUploadError('')
    const fileErrors = invalidFiles.map(({ file, error }) => (
      error === 'oversized'
        ? `${file.name} exceeds the 15 MB per-file limit.`
        : error === 'mismatch'
          ? `${file.name} has a file type that does not match its extension.`
          : `${file.name} is not a supported file type.`
    ))
    if (files.length > maxAttachmentsPerUpload) {
      fileErrors.unshift(`Upload no more than ${maxAttachmentsPerUpload} files at a time.`)
    }
    if (files.reduce((total, file) => total + file.size, 0) > maxAttachmentBatchSize) {
      fileErrors.unshift('The combined upload size exceeds 60 MB. Split the files into smaller batches.')
    }
    setValidationError(fileErrors.join(' '))
  }

  async function uploadAttachments(event) {
    event.preventDefault()
    setUploadError('')

    if (!selectedFiles.length) {
      setValidationError('Choose at least one file to upload.')
      return
    }
    if (selectedFiles.length > maxAttachmentsPerUpload) {
      setValidationError(`Upload no more than ${maxAttachmentsPerUpload} files at a time.`)
      return
    }
    if (selectedFiles.reduce((total, file) => total + file.size, 0) > maxAttachmentBatchSize) {
      setValidationError('The combined upload size exceeds 60 MB. Split the files into smaller batches.')
      return
    }

    const invalidFiles = selectedFiles.filter((file) => attachmentFileError(file))
    if (invalidFiles.length) {
      setValidationError('Remove unsupported files or files larger than 15 MB before uploading.')
      return
    }

    setIsUploading(true)
    const formData = new FormData()
    selectedFiles.forEach((file) => formData.append('files[]', file))

    try {
      const { data } = await api.post(`/edits/${editId}/attachments`, formData)
      onAttachmentsChanged((current) => [...current, ...data.data])
      setSelectedFiles([])
      setValidationError('')
      if (fileInput.current) {
        fileInput.current.value = ''
      }
    } catch (error) {
      if (error.response?.status === 422) {
        const errors = error.response.data?.errors || {}
        const filesRequired = errors.files?.some((message) => message.toLowerCase().includes('required'))
        if (filesRequired && selectedFiles.length) {
          setUploadError('The server did not receive these files. The batch may exceed the server request limit; try a smaller batch.')
          return
        }
        const firstError = Object.values(errors).flat()[0]
        setUploadError(typeof firstError === 'string'
          ? firstError
          : 'One or more files failed validation. Check their type and size.')
      } else if (error.response?.status === 413) {
        setUploadError('This upload exceeds the server request limit. Try uploading fewer files at a time.')
      } else {
        setUploadError('We couldn’t upload these files. Check your connection and try again.')
      }
    } finally {
      setIsUploading(false)
    }
  }

  function removeAttachment(attachmentId) {
    onAttachmentsChanged((current) => current.filter((attachment) => attachment.id !== attachmentId))
  }

  function clearSelection() {
    setSelectedFiles([])
    setValidationError('')
    if (fileInput.current) {
      fileInput.current.value = ''
    }
  }

  return (
    <section aria-label="Edit attachments" className="client-detail-section edit-attachments-section">
      <header className="client-section-heading">
        <div>
          <span className="section-kicker">FILES</span>
          <h2>Attachments <span className="section-count">{attachments.length}</span></h2>
        </div>
      </header>

      <form className="edit-attachment-upload" onSubmit={uploadAttachments}>
        <label className="edit-file-picker">
          <span><i className="bi bi-paperclip" aria-hidden="true" /> Choose files</span>
          <input
            accept={attachmentExtensions.map((extension) => `.${extension}`).join(',')}
            disabled={isUploading}
            multiple
            onChange={chooseFiles}
            ref={fileInput}
            type="file"
          />
        </label>
        <div className="edit-upload-selection">
          <span>
            {selectedFiles.length
              ? `${selectedFiles.length} ${selectedFiles.length === 1 ? 'file' : 'files'} selected`
              : 'JPG, PNG, WEBP, DOC, DOCX, XLS, XLSX, PDF or TXT · 15 MB each · 60 MB per batch · up to 20 files.'}
          </span>
          {selectedFiles.length > 0 && (
            <>
              <ul>
                {selectedFiles.map((file, index) => (
                  <li key={`${file.name}-${file.lastModified}-${index}`}>
                    <span>{file.name} · {formatFileSize(file.size)}</span>
                  </li>
                ))}
              </ul>
              <button className="edit-clear-selection" disabled={isUploading} onClick={clearSelection} type="button">
                Clear selection
              </button>
            </>
          )}
        </div>
        <button className="client-button client-button-primary" disabled={isUploading || !selectedFiles.length || Boolean(validationError)} type="submit">
          {isUploading ? (
            <><span className="login-spinner" aria-hidden="true" /> Uploading…</>
          ) : (
            <><i className="bi bi-cloud-arrow-up" aria-hidden="true" /> Upload attachments</>
          )}
        </button>
      </form>

      {validationError && <p className="client-form-alert" role="alert">{validationError}</p>}
      {uploadError && <p className="client-form-alert" role="alert">{uploadError}</p>}

      {attachments.length ? (
        <div className="edit-attachments-list">
          {attachments.map((attachment) => (
            <EditAttachmentItem
              attachment={attachment}
              key={attachment.id}
              onDeleted={removeAttachment}
            />
          ))}
        </div>
      ) : (
        <div className="client-inline-empty">
          <i className="bi bi-paperclip" aria-hidden="true" />
          <span>No attachments have been uploaded for this edit yet.</span>
        </div>
      )}
    </section>
  )
}

export default EditAttachmentsPanel
