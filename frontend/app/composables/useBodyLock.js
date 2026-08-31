export function useBodyLock(locked) {
  watch(locked, (open) => {
    document.body.style.overflow = open ? 'hidden' : ''
  })

  onBeforeUnmount(() => {
    document.body.style.overflow = ''
  })
}
