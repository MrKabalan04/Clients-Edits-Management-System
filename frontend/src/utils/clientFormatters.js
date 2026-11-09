export function formatDate(value) {
  if (!value) {
    return '—'
  }

  const date = new Date(`${value.slice(0, 10)}T00:00:00`)
  return Number.isNaN(date.getTime())
    ? '—'
    : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(date)
}

export function formatMoney(value) {
  if (value === null || value === undefined || value === '') {
    return '—'
  }

  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'USD',
  }).format(Number(value))
}

export function displayValue(value) {
  return value?.trim() || 'Not provided'
}

export function labelForValue(value) {
  return String(value || '').replaceAll('_', ' ')
}
