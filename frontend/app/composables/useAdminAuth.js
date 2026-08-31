export function useAdminAuth() {
  const token = useState('backoffice-token', () => '')
  const user = useState('backoffice-user', () => null)

  function load() {
    token.value = localStorage.getItem('dwd_backoffice_token') || ''
  }

  function setSession(nextToken, nextUser) {
    token.value = nextToken
    user.value = nextUser
    localStorage.setItem('dwd_backoffice_token', nextToken)
  }

  function clear() {
    token.value = ''
    user.value = null
    localStorage.removeItem('dwd_backoffice_token')
  }

  function headers() {
    return token.value ? { Authorization: `Bearer ${token.value}` } : {}
  }

  return { token, user, load, setSession, clear, headers }
}
