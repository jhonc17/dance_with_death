export function useFormErrors() {
  const error = ref('')
  const fieldErrors = ref({})

  function applyErrors(err, fallback) {
    fieldErrors.value = {}
    if (err?.data?.errors) {
      for (const k in err.data.errors) {
        const v = err.data.errors[k]
        fieldErrors.value[k] = Array.isArray(v) ? v[0] : String(v)
      }
    }
    error.value = Object.keys(fieldErrors.value).length
      ? ''
      : (err?.data?.message || fallback)
  }

  function clearFieldError(key) {
    if (!fieldErrors.value[key]) return
    const next = { ...fieldErrors.value }
    delete next[key]
    fieldErrors.value = next
  }

  function clearErrors() {
    error.value = ''
    fieldErrors.value = {}
  }

  return { error, fieldErrors, applyErrors, clearFieldError, clearErrors }
}
