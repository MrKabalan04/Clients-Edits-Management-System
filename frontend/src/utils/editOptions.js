export const editStatuses = [
  { value: 'pending', label: 'Pending' },
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'done', label: 'Done' },
  { value: 'cancelled', label: 'Cancelled' },
]

export const editPriorities = [
  { value: 'high', label: 'High' },
  { value: 'medium', label: 'Medium' },
  { value: 'low', label: 'Low' },
]

export const attachmentMimesByExtension = {
  jpg: ['image/jpeg'],
  jpeg: ['image/jpeg'],
  png: ['image/png'],
  webp: ['image/webp'],
  doc: ['application/msword', 'application/x-ole-storage'],
  docx: [
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/zip',
  ],
  xls: ['application/vnd.ms-excel', 'application/x-ole-storage'],
  xlsx: [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/zip',
  ],
  pdf: ['application/pdf'],
  txt: ['text/plain'],
}

export const attachmentExtensions = Object.keys(attachmentMimesByExtension)
export const maxAttachmentSize = 15 * 1024 * 1024
export const maxAttachmentBatchSize = 60 * 1024 * 1024
export const maxAttachmentsPerUpload = 20

export function attachmentFileError(file) {
  const extension = file.name.split('.').pop()?.toLowerCase()
  const allowedMimes = attachmentMimesByExtension[extension]

  if (!allowedMimes) {
    return 'unsupported'
  }
  if (file.size > maxAttachmentSize) {
    return 'oversized'
  }
  if (file.type && !allowedMimes.includes(file.type)) {
    return 'mismatch'
  }

  return ''
}

export function attachmentTypeLabel(fileName) {
  const extension = fileName.split('.').pop()?.toUpperCase()
  return extension || 'File'
}

export function formatFileSize(size) {
  if (size < 1024) {
    return `${size} B`
  }
  if (size < 1024 * 1024) {
    return `${(size / 1024).toFixed(1)} KB`
  }

  return `${(size / (1024 * 1024)).toFixed(1)} MB`
}
