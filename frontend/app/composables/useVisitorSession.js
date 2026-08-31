const TOKEN_KEY = 'dwd_visitor_token'

export function useVisitorSession() {
  const apiBase = useRuntimeConfig().public.apiBase
  const session = ref(null)

  function headers() {
    return session.value?.token ? { Authorization: `Bearer ${session.value.token}` } : {}
  }

  function persist(next) {
    session.value = next
    if (next?.token) localStorage.setItem(TOKEN_KEY, next.token)
    else localStorage.removeItem(TOKEN_KEY)
  }

  async function restore() {
    const token = localStorage.getItem(TOKEN_KEY)
    if (!token) return null

    try {
      const data = await $fetch(`${apiBase}/session/me`, {
        headers: { Authorization: `Bearer ${token}` },
      })
      persist({
        token,
        email: data.email,
        appointment: data.appointment || null,
      })
      return session.value
    } catch {
      persist(null)
      return null
    }
  }

  async function signOut() {
    try {
      if (session.value?.token) {
        await $fetch(`${apiBase}/session/logout`, {
          method: 'POST',
          headers: headers(),
        })
      }
    } catch {
      // still drop the page session
    }
    persist(null)
  }

  return { apiBase, session, headers, persist, restore, signOut }
}
