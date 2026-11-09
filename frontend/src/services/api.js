import axios from 'axios'

const apiBaseUrl = import.meta.env.DEV
  ? '/api'
  : import.meta.env.VITE_API_BASE_URL || '/api'
const apiOrigin = new URL(apiBaseUrl, window.location.origin).origin

const api = axios.create({
  baseURL: apiBaseUrl,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
  },
})

export async function initializeCsrfCookie() {
  await api.get(new URL('/sanctum/csrf-cookie', apiOrigin).toString())
}

export default api
