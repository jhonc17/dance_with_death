<script setup>
const { apiBase, session, headers, persist, restore, signOut: endSession } = useVisitorSession()
const { error, fieldErrors, applyErrors, clearFieldError, clearErrors } = useFormErrors()

const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'

const name = ref('')
const email = ref('')
const date = ref('')
const time = ref('')
const slots = ref([])
const loading = ref(false)
const submitting = ref(false)
const code = ref('')
const pending = ref(false)
const loginOpen = ref(false)
const bookOpen = ref(false)
const logoutOpen = ref(false)

const minDate = formatDate(new Date())
const mineTime = computed(() => {
  const appt = session.value?.appointment
  if (!appt?.starts_at || !date.value) return ''
  if (dateInZone(appt.starts_at, timezone) !== date.value) return ''
  return timeInZone(appt.starts_at, timezone)
})
const showSubmit = computed(() => {
  if (!session.value) return false
  if (!session.value.appointment) return true
  return !!time.value && !isMine(time.value)
})

function isMine(slotTime) {
  return mineTime.value === slotTime
}

const bookSummary = computed(() => {
  if (!date.value || !time.value) return ''
  return `${time.value} on ${formatLongDate(date.value)}`
})

async function loadSlots({ keepTime = false } = {}) {
  clearErrors()
  if (!keepTime) time.value = ''
  slots.value = []

  if (!date.value) return

  if (isWeekend(date.value)) {
    error.value = 'Weekdays only (Mon–Fri).'
    return
  }

  loading.value = true
  try {
    const data = await $fetch(`${apiBase}/slots`, {
      query: { date: date.value, timezone },
    })
    slots.value = data.slots
  } catch (e) {
    error.value = e?.data?.message || 'Could not load time slots.'
  } finally {
    loading.value = false
  }
}

function goToNextWeekday() {
  date.value = nextWeekday()
  return loadSlots()
}

function openLogin() {
  clearErrors()
  loginOpen.value = true
}

function clickSlot(s) {
  time.value = s.time
  if (!session.value) {
    openLogin()
    return
  }
  clearFieldError('time')
}

async function closeLogin() {
  const discardEmail = pending.value ? email.value.trim() : ''
  loginOpen.value = false
  pending.value = false
  code.value = ''
  clearErrors()
  email.value = ''
  if (!discardEmail) return
  try {
    await $fetch(`${apiBase}/session/discard`, {
      method: 'POST',
      body: { email: discardEmail },
    })
  } catch {
    // ignore
  }
}

function validateEmail() {
  const next = {}
  if (!email.value.trim()) next.email = 'Email is required.'
  else if (!isValidEmail(email.value)) next.email = 'Enter a valid email.'
  fieldErrors.value = next
  return !Object.keys(next).length
}

async function sendCode() {
  clearErrors()
  if (!validateEmail()) return

  submitting.value = true
  try {
    await $fetch(`${apiBase}/session`, {
      method: 'POST',
      body: { email: email.value.trim() },
    })
    pending.value = true
    code.value = ''
  } catch (e) {
    applyErrors(e, 'Could not send the code.')
  } finally {
    submitting.value = false
  }
}

async function applyAppointment(appt) {
  name.value = appt?.name || ''

  if (!appt?.starts_at) return

  date.value = dateInZone(appt.starts_at, timezone)
  time.value = timeInZone(appt.starts_at, timezone)
  await loadSlots({ keepTime: true })
}

async function confirmCode() {
  clearErrors()
  if (code.value.trim().length !== 6) {
    fieldErrors.value = { code: 'Enter the 6-digit code.' }
    return
  }

  submitting.value = true
  try {
    const data = await $fetch(`${apiBase}/session/confirm`, {
      method: 'POST',
      body: {
        email: email.value.trim(),
        code: code.value.trim(),
      },
    })
    persist({
      token: data.token,
      email: data.email,
      appointment: data.appointment || null,
    })
    pending.value = false
    code.value = ''
    loginOpen.value = false
    await applyAppointment(data.appointment)
  } catch (e) {
    applyErrors(e, 'Could not sign in.')
  } finally {
    submitting.value = false
  }
}

async function signOut() {
  logoutOpen.value = false
  submitting.value = true
  await endSession()
  name.value = ''
  email.value = ''
  await goToNextWeekday()
  submitting.value = false
}

function validateBooking() {
  const next = {}
  if (!name.value.trim()) next.name = 'Name is required.'
  if (!date.value) next.date = 'Date is required.'
  if (!time.value || isMine(time.value)) next.time = 'Select a time slot.'
  fieldErrors.value = next
  return !Object.keys(next).length
}

function closeBook() {
  bookOpen.value = false
  clearErrors()
}

function bookAppointment() {
  if (!session.value) return
  clearErrors()
  if (!validateBooking()) return
  bookOpen.value = true
}

async function confirmBook() {
  clearErrors()

  submitting.value = true
  const changing = !!session.value.appointment
  try {
    const data = await $fetch(`${apiBase}/appointments`, {
      method: 'POST',
      headers: headers(),
      body: {
        name: name.value.trim(),
        date: date.value,
        time: time.value,
        timezone,
      },
    })
    persist({
      ...session.value,
      appointment: data.appointment,
    })
    bookOpen.value = false
    await applyAppointment(data.appointment)
  } catch (e) {
    if (e?.status === 401) {
      bookOpen.value = false
      persist(null)
      name.value = ''
      email.value = ''
      await goToNextWeekday()
      openLogin()
      return
    }
    applyErrors(e, changing ? 'Could not change the appointment.' : 'Could not book the appointment.')
  } finally {
    submitting.value = false
  }
}

useBodyLock(computed(() => loginOpen.value || bookOpen.value || logoutOpen.value))

onMounted(async () => {
  const restored = await restore()
  if (restored) {
    await applyAppointment(restored.appointment)
    if (restored.appointment?.starts_at) return
  }
  await goToNextWeekday()
})
</script>

<template>
  <main class="page">
    <header class="hero">
      <p class="brand">Dance with Death</p>
      <div class="hero-row">
        <h1>Book a time</h1>
        <button
          class="session-btn"
          type="button"
          :aria-label="session ? `Sign out ${session.email}` : 'Sign in'"
          :title="session ? `Sign out ${session.email}` : 'Sign in'"
          :class="{ on: !!session }"
          @click="session ? (logoutOpen = true) : openLogin()"
        >
          <svg v-if="!session" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="8" r="3.2" />
            <path d="M5.4 19.2c.8-3.2 3.4-5.2 6.6-5.2s5.8 2 6.6 5.2" />
          </svg>
          <svg v-else viewBox="0 0 24 24" aria-hidden="true">
            <path d="M10 6H6.5A1.5 1.5 0 0 0 5 7.5v9A1.5 1.5 0 0 0 6.5 18H10" />
            <path d="M10 12h9" />
            <path d="M16 8.5 19.5 12 16 15.5" />
          </svg>
        </button>
      </div>
    </header>

    <form class="form" novalidate @submit.prevent="bookAppointment">
      <div class="fields" :class="{ 'fields-book': !!session }">
        <div v-if="session" class="field-col">
          <label class="field">
            <span>Name</span>
            <input
              v-model="name"
              type="text"
              placeholder="Your name"
              autocomplete="name"
              :aria-invalid="!!fieldErrors.name"
              aria-describedby="name-error"
              @input="clearFieldError('name')"
            />
          </label>
          <p id="name-error" class="field-error" role="alert">{{ fieldErrors.name || '' }}</p>
        </div>

        <div class="field-col date-field">
          <label class="field">
            <span>Date</span>
            <input
              v-model="date"
              type="date"
              :min="minDate"
              :aria-invalid="!!fieldErrors.date"
              aria-describedby="date-error"
              @change="loadSlots()"
            />
          </label>
          <p id="date-error" class="field-error" role="alert">{{ fieldErrors.date || '' }}</p>
        </div>
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
            :aria-selected="time === s.time || isMine(s.time)"
            :class="{
              taken: !s.available && !isMine(s.time),
              selected: time === s.time && !isMine(s.time),
              mine: isMine(s.time),
            }"
            :disabled="!isMine(s.time) && !s.available"
            @click="clickSlot(s)"
          >
            {{ s.time }}
          </button>
        </div>
        <small v-if="fieldErrors.time">{{ fieldErrors.time }}</small>
      </section>

      <div v-if="showSubmit" class="footer-actions">
        <button class="btn" type="submit" :disabled="submitting">
          {{ session?.appointment ? 'Change' : 'Book' }}
        </button>
      </div>
    </form>

    <Teleport to="body">
      <div
        v-if="loginOpen"
        class="modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="login-title"
      >
        <button class="backdrop" type="button" aria-label="Close" @click="closeLogin" />
        <div class="sheet sheet-appointment">
          <button class="sheet-close" type="button" aria-label="Close" @click="closeLogin">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M6 6l12 12M18 6L6 18" />
            </svg>
          </button>
          <p class="sheet-kicker">Sign in</p>
          <h2 id="login-title">{{ pending ? 'Enter your code' : 'Your email' }}</h2>
          <p class="sheet-copy">
            {{ pending
              ? `We emailed a 6-digit code to ${email.trim()}. It expires in 15 minutes.`
              : 'We’ll send a 6-digit code to this address.' }}
          </p>
          <div class="field-col">
            <label class="field">
              <span>Email</span>
              <input
                v-model="email"
                type="email"
                placeholder="you@example.com"
                autocomplete="email"
                :disabled="!!pending || submitting"
                :aria-invalid="!!fieldErrors.email"
                aria-describedby="email-error"
                @input="clearFieldError('email')"
              />
            </label>
            <p id="email-error" class="field-error" role="alert">{{ fieldErrors.email || '' }}</p>
          </div>
          <div v-if="pending" class="field-col">
            <label class="field">
              <span>Code</span>
              <input
                v-model="code"
                class="code-input"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                placeholder="000000"
                :aria-invalid="!!fieldErrors.code"
                aria-describedby="code-error"
                @input="clearFieldError('code')"
                @keydown.enter.prevent="confirmCode"
              />
            </label>
            <p id="code-error" class="field-error" role="alert">{{ fieldErrors.code || '' }}</p>
          </div>
          <p v-if="error" class="msg err">{{ error }}</p>
          <div class="sheet-actions">
            <button class="btn ghost" type="button" :disabled="submitting" @click="closeLogin">
              Cancel
            </button>
            <button
              v-if="!pending"
              class="btn close-action"
              type="button"
              :disabled="submitting"
              @click="sendCode"
            >
              {{ submitting ? 'Sending…' : 'Send code' }}
            </button>
            <button
              v-else
              class="btn close-action"
              type="button"
              :disabled="submitting || code.trim().length !== 6"
              @click="confirmCode"
            >
              {{ submitting ? 'Confirming…' : 'Confirm' }}
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="bookOpen"
        class="modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="book-title"
      >
        <button class="backdrop" type="button" aria-label="Close" @click="closeBook" />
        <div class="sheet sheet-appointment">
          <button class="sheet-close" type="button" aria-label="Close" @click="closeBook">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M6 6l12 12M18 6L6 18" />
            </svg>
          </button>
          <p class="sheet-kicker">{{ session?.appointment ? 'Change' : 'Book' }}</p>
          <h2 id="book-title">
            {{ session?.appointment ? 'Confirm this change?' : 'Confirm this appointment?' }}
          </h2>
          <p class="sheet-copy">
            <template v-if="session?.appointment">
              You're moving your appointment to {{ bookSummary }} ({{ timezone }}).
              The previous hour will be freed.
            </template>
            <template v-else>
              You're booking {{ bookSummary }} ({{ timezone }}) as {{ name.trim() }}.
            </template>
          </p>
          <p v-if="error" class="msg err">{{ error }}</p>
          <div class="sheet-actions">
            <button class="btn ghost" type="button" :disabled="submitting" @click="closeBook">
              Cancel
            </button>
            <button
              class="btn close-action"
              type="button"
              :disabled="submitting"
              @click="confirmBook"
            >
              {{ submitting ? 'Confirming…' : 'Confirm' }}
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="logoutOpen"
        class="modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="logout-title"
      >
        <button class="backdrop" type="button" aria-label="Close" @click="logoutOpen = false" />
        <div class="sheet sheet-appointment">
          <button class="sheet-close" type="button" aria-label="Close" @click="logoutOpen = false">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M6 6l12 12M18 6L6 18" />
            </svg>
          </button>
          <p class="sheet-kicker">Sign out</p>
          <h2 id="logout-title">Leave this session?</h2>
          <p class="sheet-copy">
            You’ll go back to the calendar. Sign in again if you want to see your appointment.
          </p>
          <div class="sheet-actions">
            <button class="btn ghost" type="button" :disabled="submitting" @click="logoutOpen = false">
              Cancel
            </button>
            <button class="btn close-action" type="button" :disabled="submitting" @click="signOut">
              {{ submitting ? 'Signing out…' : 'Sign out' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </main>
</template>

<style scoped>
.hero-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.session-btn {
  appearance: none;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2.4rem;
  height: 2.4rem;
  border: 1px solid var(--line-strong);
  background: var(--bg);
  color: var(--muted);
  cursor: pointer;
  transition: color 0.15s ease, border-color 0.15s ease, background 0.15s ease;
}

.session-btn:hover {
  color: var(--text);
  border-color: var(--text);
}

.session-btn.on {
  background: var(--text);
  color: var(--accent-ink);
  border-color: var(--text);
}

.session-btn svg {
  width: 1.2rem;
  height: 1.2rem;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.slot.taken {
  opacity: 0.28;
  text-decoration: line-through;
}

.slot.mine {
  border-color: var(--ok);
  background: rgba(110, 231, 183, 0.12);
  color: var(--ok);
  box-shadow: inset 0 0 0 1px rgba(110, 231, 183, 0.35);
}

.fields {
  display: flex;
  justify-content: flex-end;
  align-items: start;
  gap: 1.5rem;
}

.fields-book > .field-col:not(.date-field) {
  flex: 1;
  min-width: 0;
}

.date-field {
  width: max-content;
  flex-shrink: 0;
}

.date-field input[type='date'] {
  width: 12.25rem;
}

.code-input {
  letter-spacing: 0.28em;
  font-variant-numeric: tabular-nums;
}
</style>
