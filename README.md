# Dance with Death

Public calendar to book a one-hour appointment. Visitors sign in with an email code; staff manage bookings in a small backoffice.

[English](README.md) · [Español](README.es.md)

You do **not** need PHP, Node, or Postgres installed on your machine. Docker builds and runs everything.

---

## What you get

| Piece | Role |
| --- | --- |
| **Frontend** | Nuxt static site. `/` is the public calendar. `/backoffice` is staff-only. |
| **API** | Laravel JSON API. Sessions, slots, bookings, mail. |
| **Postgres** | Appointments and tokens. Data stays on the host under `./data/postgres`. |

Hours are **09:00–18:00 on the visitor’s clock**, **Monday–Friday**. The database stores `starts_at` in UTC. One upcoming appointment per email.

---

## Requirements

- [Docker](https://docs.docker.com/get-docker/) with Compose (`docker compose version` should work)
- Git
- `openssl` (to generate `APP_KEY`; already on most Linux/macOS setups)

---

## Run from scratch

### 1. Clone

```bash
git clone <this-repo-url> dance_with_death
cd dance_with_death
```

### 2. Environment file

```bash
cp .env.local.example .env.local
```

Secrets live only in `.env.local`. It is gitignored. Do not commit it.

### 3. App key

Laravel needs `APP_KEY` before the API will start. Generate one and paste it into `.env.local`:

```bash
echo "APP_KEY=base64:$(openssl rand -base64 32)"
```

The line should look like `APP_KEY=base64:……=` with no quotes and no spaces around `=`.

Leave mail as `MAIL_MAILER=log` for a first run. Sign-in codes will be written to the backend logs instead of sent.

### 4. Start

```bash
./deploy
```

This builds the images, starts Postgres + API + frontend, runs migrations, and **replaces** any previous Dance with Death stack on this machine.

When it finishes it prints three URLs, for example:

```
  front  http://localhost:3000
  api    http://localhost:8000/api
  db     localhost:5432
```

If 3000 / 8000 / 5432 are busy, `./deploy` picks the next free ports unless you pin them in `.env.local`. **Use the URLs it printed**, not the examples above.

### 5. Create a staff user

Password must be at least 8 characters:

```bash
./admin-create you@example.com secretpass
```

With no arguments it prompts (needs a real terminal: `./admin-create`).

### 6. Open the app

- Public calendar: the **front** URL from step 4
- Staff: `http://localhost:<FRONTEND_PORT>/backoffice` — same email and password as `./admin-create`

To confirm the API is up:

```bash
curl "http://localhost:<BACKEND_PORT>/api/slots?date=2026-09-01&timezone=America/Caracas"
```

Replace the port with the one `./deploy` printed. A weekday date should return `{ "slots": [ … ] }`.

---

## After the first start

| You want to… | Do this |
| --- | --- |
| Pick up code changes | `./deploy` again (rebuilds images; Postgres data is kept) |
| Stop containers | see [Stop](#stop) |
| Wipe bookings and start empty | see [Reset the database](#reset-the-database) |
| Receive real emails | see [Mail](#mail) |

---

## How booking works

The calendar opens on the next weekday. If the visitor is signed in and already has an upcoming appointment, it opens on that day and marks their hour.

Without a session, anyone can see which hours are free or taken. Taken hours are not clickable.

1. Sign in from the person icon, or by tapping a **free** hour. A modal asks for email and a 6-digit code (15 minutes).
2. The session is stored in `localStorage` until they sign out (one week on the server). The name field appears after sign-in. If they already have an appointment, that name is filled in.
3. **Book:** pick a free hour, enter a name, press **Book**, confirm. The appointment is saved, then a booked email is sent (or logged, if mail is `log`). If the email fails, the booking still stands.
4. **Change:** pick another free future hour, press **Change**, confirm. The old hour is freed, then a change email is sent. The name can be edited in the same step.

If two people confirm the same hour, the first request that Postgres commits wins. The other gets “That slot is already taken.”

After the appointment time has passed, that email can book again.

---

## Backoffice

`/backoffice` is the same hour grid, with who booked each slot. Click a slot to see details or cancel. Cancel emails the client first; if that send fails, the appointment stays. The email includes a link back to the public calendar (`FRONTEND_URL`).

---

## Configuration

All of this is `.env.local`. `./deploy` injects it into the containers.

### Ports

Uncomment and set if you do not want auto-picked ports:

```env
FRONTEND_PORT=3000
BACKEND_PORT=8000
POSTGRES_PORT=5432
```

If you set a port that is already in use, `./deploy` exits instead of guessing.

### Mail

Default is `MAIL_MAILER=log`: messages go to the backend container logs, not a mailbox. Read them with:

```bash
docker ps --filter "label=com.dwd.service=backend" --format '{{.Names}}'
docker logs -f <that-name>
```

For SMTP (Gmail example):

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_USERNAME=you@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=you@gmail.com
MAIL_FROM_NAME="Dance with Death"
```

Gmail needs 2-step verification and an [app password](https://myaccount.google.com/apppasswords). Then `./deploy` again.

Cancellation emails include `FRONTEND_URL`. `./deploy` sets it to `http://localhost:<FRONTEND_PORT>` unless you override it.

### Postgres data

Files live at `./data/postgres` (or `POSTGRES_DATA_PATH`). Containers can be deleted; this folder is the database.

---

## API

Base URL: `http://localhost:<BACKEND_PORT>/api` (the **api** line from `./deploy`).

JSON only. `starts_at` is ISO-8601 UTC. `appointment` is `{ "name", "starts_at" }` or `null`.

Visitor and admin routes expect `Authorization: Bearer <token>`.

### Public

| Method | Path | Body / query | Response |
| --- | --- | --- | --- |
| `GET` | `/slots` | `date=YYYY-MM-DD&timezone=Area/City` | `{ slots: [{ time, available }] }` |
| `POST` | `/session` | `{ email }` | sends a sign-in code |
| `POST` | `/session/confirm` | `{ email, code }` | `{ token, email, appointment }` |
| `POST` | `/session/discard` | `{ email }` | drops an unused code |
| `POST` | `/admin/login` | `{ email, password }` | `{ token, user }` |

### Visitor

| Method | Path | Body | Response |
| --- | --- | --- | --- |
| `GET` | `/session/me` | | `{ email, appointment }` |
| `POST` | `/session/logout` | | |
| `POST` | `/appointments` | `{ name, date, time, timezone }` | creates or updates; `{ appointment }` |

### Admin

| Method | Path | Notes |
| --- | --- | --- |
| `GET` | `/admin/me` | `{ user }` |
| `POST` | `/admin/logout` | |
| `GET` | `/admin/slots` | same grid; taken slots include `booking: { id, name, email }` |
| `DELETE` | `/admin/appointments/{id}` | cancel and email the client |

---

## Stop

```bash
docker ps --filter "label=com.dwd.app=dance-with-death" -q | xargs -r docker rm -f
```

That removes the containers. `./data/postgres` is kept.

## Reset the database

This deletes all appointments, sessions, and staff users.

```bash
docker ps --filter "label=com.dwd.app=dance-with-death" -q | xargs -r docker rm -f
rm -rf data/postgres
./deploy
./admin-create you@example.com secretpass
```

---

## If something fails

| Symptom | What to check |
| --- | --- |
| `Missing .env.local` | Run `cp .env.local.example .env.local` from the repo root. |
| `APP_KEY is not set` | `APP_KEY=base64:…` must be in `.env.local`, not left empty. |
| `FRONTEND_PORT … is already in use` | Free that port, or omit the variable and let `./deploy` choose. |
| `No backend is running` on `./admin-create` | Run `./deploy` first and wait until it prints `done`. |
| Sign-in code never arrives | With `MAIL_MAILER=log`, the code is in the backend logs, not email. |
| Calendar shows the wrong hours | Slots follow the **browser timezone**. The API query uses that IANA name. |
| `Could not connect to PostgreSQL` | First boot can take a few seconds. If it persists, check `DB_*` in `.env.local` and `docker ps`. |

Repo layout if you need to find a file:

```
deploy              start / rebuild the stack
admin-create        create a /backoffice user
.env.local.example  copy to .env.local
backend/            Laravel API
frontend/           Nuxt site
```
