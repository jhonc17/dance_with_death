<script setup>
const apiBase = useRuntimeConfig().public.apiBase
const { token, user, load, setSession, clear, headers } = useAdminAuth()
const { error, fieldErrors, applyErrors, clearFieldError, clearErrors } = useFormErrors()

const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'

const email = ref('')
const password = ref('')
const date = ref('')
const slots = ref([])
const loading = ref(false)
const submitting = ref(false)
const notice = ref('')
const selected = ref(null)
const confirmCancel = ref(false)
const cancelling = ref(false)

useHead({
  title: 'Backoffice — Dance with Death',
  meta: [{ name: 'robots', content: 'noindex, nofollow' }],
})

function validateLogin() {
  const next = {}
  if (!email.value.trim()) next.email = 'Email is required.'
  else if (!isValidEmail(email.value)) next.email = 'Enter a valid email.'
  if (!password.value) next.password = 'Password is required.'
  fieldErrors.value = next
  return !Object.keys(next).length
}

async function login() {
  clearErrors()

  if (!validateLogin()) return

  submitting.value = true
  try {
    const data = await $fetch(`${apiBase}/admin/login`, {
      method: 'POST',
      body: { email: email.value.trim(), password: password.value },
    })
    setSession(data.token, data.user)
    password.value = ''
    date.value = nextWeekday()
    await loadSlots()
  } catch (e) {
    applyErrors(e, 'Could not sign in.')
  } finally {
    submitting.value = false
  }
}

async function restore() {
  load()
  if (!token.value) return
  try {
    const data = await $fetch(`${apiBase}/admin/me`, { headers: headers() })
    user.value = data.user
    date.value = nextWeekday()
    await loadSlots()
  } catch {
    clear()
  }
}

async function logout() {
  try {
    if (token.value) {
      await $fetch(`${apiBase}/admin/logout`, { method: 'POST', headers: headers() })
    }
  } catch {
    // still drop the local session
  }
  clear()
  slots.value = []
  selected.value = null
  confirmCancel.value = false
  clearErrors()
  notice.value = ''
}

async function loadSlots({ keepNotice = false } = {}) {
  clearErrors()
  if (!keepNotice) notice.value = ''
  selected.value = null
  confirmCancel.value = false
  slots.value = []
  if (!date.value || !token.value) return

  if (isWeekend(date.value)) {
    error.value = 'Weekdays only (Mon–Fri).'
    return
  }

  loading.value = true
  try {
    const data = await $fetch(`${apiBase}/admin/slots`, {
      headers: headers(),
      query: { date: date.value, timezone },
    })
    slots.value = data.slots
  } catch (e) {
    if (e?.status === 401) {
      clear()
      error.value = 'Session expired. Sign in again.'
      return
    }
    error.value = e?.data?.message || 'Could not load time slots.'
  } finally {
    loading.value = false
  }
}

function closeSlot() {
  if (cancelling.value) return
  selected.value = null
  confirmCancel.value = false
}

async function cancelBooking() {
  const booking = selected.value?.booking
  if (!booking?.id || cancelling.value) return

  cancelling.value = true
  error.value = ''
  try {
    const data = await $fetch(`${apiBase}/admin/appointments/${booking.id}`, {
      method: 'DELETE',
      headers: headers(),
    })
    notice.value = data.message || 'Cancelled. The client was notified.'
    await loadSlots({ keepNotice: true })
  } catch (e) {
    if (e?.status === 401) {
      clear()
      selected.value = null
      error.value = 'Session expired. Sign in again.'
      return
    }
    error.value = e?.data?.message || 'Could not cancel the appointment.'
  } finally {
    cancelling.value = false
  }
}

useBodyLock(computed(() => !!selected.value))

onMounted(() => {
  restore()
})
</script>

<template>
  <main class="page">
    <header class="hero">
      <p class="brand">Dance with Death</p>
      <h1>Backoffice</h1>
      <p v-if="!user" class="lead">
        Sign in to see bookings.
      </p>
    </header>

    <form v-if="!user" class="form" novalidate @submit.prevent="login">
      <div class="fields">
        <div class="field-col">
          <label class="field">
            <span>Email</span>
            <input
              v-model="email"
              type="email"
              placeholder="you@example.com"
              autocomplete="username"
              :aria-invalid="!!fieldErrors.email"
              aria-describedby="email-error"
              @input="clearFieldError('email')"
            />
          </label>
          <p id="email-error" class="field-error" role="alert">{{ fieldErrors.email || '' }}</p>
        </div>

        <div class="field-col">
          <label class="field">
            <span>Password</span>
            <input
              v-model="password"
              type="password"
              placeholder="••••••••"
              autocomplete="current-password"
              :aria-invalid="!!fieldErrors.password"
              aria-describedby="password-error"
              @input="clearFieldError('password')"
            />
          </label>
          <p id="password-error" class="field-error" role="alert">{{ fieldErrors.password || '' }}</p>
        </div>
      </div>
      <div class="footer-actions">
        <button class="btn" type="submit" :disabled="submitting">
          {{ submitting ? 'Signing in…' : 'Sign in' }}
        </button>
        <p v-if="error" class="msg err">{{ error }}</p>
      </div>
    </form>

    <div v-else class="form">
      <div class="fields backoffice-fields">
        <label class="field">
          <span>Date</span>
          <input
            v-model="date"
            type="date"
            @change="loadSlots()"
          />
        </label>
        <p class="who">{{ user.name }} · {{ user.email }}</p>
      </div>

      <section aria-label="Time slots">
        <div class="section-head">
          <h2>Select a slot</h2>
          <p>{{ timezone }}</p>
        </div>

        <p v-if="loading" class="msg">Loading slots…</p>
        <p v-else-if="!slots.length && error" class="msg err">{{ error }}</p>

        <div v-else class="slots" role="listbox">
          <button
            v-for="s in slots"
            :key="s.time"
            type="button"
            class="slot"
            role="option"
            :aria-selected="selected?.time === s.time"
            :class="{
              available: !s.booking,
              taken: !!s.booking,
              selected: selected?.time === s.time,
            }"
            @click="confirmCancel = false; selected = s"
          >
            {{ s.time }}
          </button>
        </div>
      </section>

      <div class="footer-actions">
        <button class="btn ghost" type="button" @click="logout">Sign out</button>
        <p v-if="error && slots.length" class="msg err">{{ error }}</p>
        <p v-if="notice" class="msg ok">{{ notice }}</p>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="selected" class="modal" role="dialog" aria-modal="true" aria-labelledby="slot-title">
        <button class="backdrop" type="button" aria-label="Close" @click="closeSlot" />
        <div class="sheet sheet-appointment">
          <button class="sheet-close" type="button" aria-label="Close" :disabled="cancelling" @click="closeSlot">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M6 6l12 12M18 6L6 18" />
            </svg>
          </button>
          <p class="sheet-kicker">{{ formatLongDate(date) }}</p>
          <h2 id="slot-title">{{ selected.time }}</h2>
          <p class="sheet-copy">{{ timezone }}</p>
          <article v-if="selected.booking" class="ticket">
            <p class="status booked">Booked</p>
            <p class="ticket-name">{{ selected.booking.name }}</p>
            <a class="ticket-email" :href="`mailto:${selected.booking.email}`">
              {{ selected.booking.email }}
            </a>
          </article>
          <article v-else class="ticket ticket-empty">
            <p class="status">Free</p>
            <p class="ticket-empty-copy">No booking for this slot.</p>
          </article>
          <p v-if="confirmCancel" class="cancel-copy">
            We'll email <strong>{{ selected.booking.email }}</strong> to let them know.
          </p>
          <p v-if="error" class="msg err">{{ error }}</p>
          <div class="sheet-actions">
            <template v-if="selected.booking && confirmCancel">
              <button class="btn danger" type="button" :disabled="cancelling" @click="cancelBooking">
                {{ cancelling ? 'Cancelling…' : 'Yes, cancel' }}
              </button>
              <button class="btn ghost" type="button" :disabled="cancelling" @click="confirmCancel = false">
                Back
              </button>
            </template>
            <template v-else>
              <button
                v-if="selected.booking"
                class="btn danger"
                type="button"
                @click="confirmCancel = true"
              >
                Cancel appointment
              </button>
              <button class="btn ghost close-action" type="button" :disabled="cancelling" @click="closeSlot">
                Close
              </button>
            </template>
          </div>
        </div>
      </div>
    </Teleport>
  </main>
</template>

<style scoped>
h1 {
  margin: 0 0 0.65rem;
}

.lead {
  margin: 0;
  max-width: 28rem;
  color: var(--muted);
  font-size: 0.95rem;
  font-weight: 300;
  line-height: 1.55;
  animation: fade 0.9s ease 0.24s both;
}

.backoffice-fields {
  align-items: start;
}

.who {
  margin: 0;
  color: var(--muted);
  font-size: 0.9rem;
  padding-top: 1.7rem;
}

.slot.taken {
  border-color: var(--line-strong);
}

.slot.available {
  opacity: 0.45;
}

.slot.selected {
  opacity: 1;
}

.btn.danger {
  background: transparent;
  color: var(--danger);
  border: 1px solid rgba(255, 107, 107, 0.45);
}

.btn.danger:hover:not(:disabled) {
  border-color: var(--danger);
}

.cancel-copy {
  margin: 0.15rem 0 0;
  color: var(--muted);
  font-size: 0.92rem;
  line-height: 1.5;
}

.cancel-copy strong {
  color: var(--text);
  font-weight: 500;
}

.msg.ok {
  color: var(--ok);
}

.sheet-appointment {
  gap: 0.85rem;
}

.sheet-appointment h2 {
  font-size: 2.15rem;
  letter-spacing: -0.04em;
  line-height: 0.95;
}

.ticket {
  display: grid;
  gap: 0.35rem;
  margin: 0.25rem 0 0.35rem;
  padding: 1.15rem 1.15rem 1.1rem;
  border: 1px solid var(--line-strong);
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.012));
}

.ticket-empty {
  gap: 0.55rem;
}

.ticket-empty-copy {
  margin: 0;
  color: var(--muted);
  font-size: 0.92rem;
}

.status {
  margin: 0 0 0.25rem;
  width: fit-content;
  font-size: 0.68rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--muted);
  border: 1px solid var(--line-strong);
  padding: 0.28rem 0.5rem;
}

.status.booked {
  color: var(--ok);
  border-color: rgba(110, 231, 183, 0.38);
}

.ticket-name {
  margin: 0.15rem 0 0;
  font-size: 1.35rem;
  font-weight: 600;
  letter-spacing: -0.03em;
  line-height: 1.2;
}

.ticket-email {
  color: var(--muted);
  text-decoration: none;
  font-size: 0.92rem;
  line-height: 1.4;
  text-underline-offset: 0.2em;
}

.ticket-email:hover {
  color: var(--text);
  text-decoration: underline;
}

@media (min-width: 720px) {
  .fields {
    grid-template-columns: 1.2fr 1.4fr;
    gap: 1.5rem;
  }

  .backoffice-fields {
    grid-template-columns: 0.9fr 1fr;
  }
}
</style>
