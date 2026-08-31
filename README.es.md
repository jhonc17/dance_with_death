# Dance with Death

Calendario público para reservar una cita de una hora. El visitante entra con un código al correo; el equipo gestiona las reservas en un backoffice.

[English](README.md) · [Español](README.es.md)

**No** hace falta tener PHP, Node ni Postgres en la máquina. Docker construye y arranca todo.

---

## Qué es

| Pieza | Para qué |
| --- | --- |
| **Frontend** | Sitio estático Nuxt. `/` es el calendario público. `/backoffice` es solo para el equipo. |
| **API** | Laravel, JSON. Sesiones, huecos, reservas, correo. |
| **Postgres** | Citas y tokens. Los datos quedan en el host, en `./data/postgres`. |

Los huecos son **09:00–18:00 en el reloj del visitante**, **lunes a viernes**. En base de datos, `starts_at` va en UTC. Una cita vigente por correo.

---

## Requisitos

- [Docker](https://docs.docker.com/get-docker/) con Compose (`docker compose version` tiene que funcionar)
- Git
- `openssl` (para generar `APP_KEY`; suele venir en Linux/macOS)

---

## Arrancar desde cero

### 1. Clonar

```bash
git clone <url-de-este-repo> dance_with_death
cd dance_with_death
```

### 2. Archivo de entorno

```bash
cp .env.local.example .env.local
```

Los secretos van solo en `.env.local`. Está fuera de git. No lo subas.

### 3. Clave de la app

Laravel exige `APP_KEY` antes de que la API arranque. Genera una y pégala en `.env.local`:

```bash
echo "APP_KEY=base64:$(openssl rand -base64 32)"
```

La línea debe verse como `APP_KEY=base64:……=` sin comillas y sin espacios alrededor del `=`.

Para la primera prueba deja `MAIL_MAILER=log`. Los códigos de acceso salen en los logs del backend, no al correo.

### 4. Arrancar

```bash
./deploy
```

Construye las imágenes, levanta Postgres + API + frontend, corre las migraciones y **sustituye** cualquier stack anterior de Dance with Death en esta máquina.

Al terminar imprime tres URLs, por ejemplo:

```
  front  http://localhost:3000
  api    http://localhost:8000/api
  db     localhost:5432
```

Si 3000 / 8000 / 5432 están ocupados, `./deploy` elige el siguiente puerto libre, salvo que los fijes en `.env.local`. **Usa las URLs que imprimió**, no los ejemplos de arriba.

### 5. Crear un usuario de staff

La contraseña debe tener al menos 8 caracteres:

```bash
./admin-create you@example.com secretpass
```

Sin argumentos las pide por consola (hace falta una terminal de verdad: `./admin-create`).

### 6. Abrir la app

- Calendario público: la URL **front** del paso 4
- Equipo: `http://localhost:<FRONTEND_PORT>/backoffice` — el mismo correo y contraseña de `./admin-create`

Para comprobar que la API responde:

```bash
curl "http://localhost:<BACKEND_PORT>/api/slots?date=2026-09-01&timezone=America/Caracas"
```

Cambia el puerto por el que imprimió `./deploy`. Un día laborable debe devolver `{ "slots": [ … ] }`.

---

## Después del primer arranque

| Quieres… | Haz esto |
| --- | --- |
| Aplicar cambios de código | `./deploy` otra vez (reconstruye imágenes; Postgres se conserva) |
| Parar los contenedores | ver [Parar](#parar) |
| Borrar reservas y empezar vacío | ver [Resetear la base de datos](#resetear-la-base-de-datos) |
| Recibir correos de verdad | ver [Correo](#correo) |

---

## Cómo se reserva

El calendario abre en el próximo día hábil. Si el visitante tiene sesión y una cita vigente, abre en ese día y marca su hora.

Sin sesión cualquiera ve qué horas están libres u ocupadas. Las ocupadas no se pueden pulsar.

1. Inicia sesión con el icono de persona, o tocando una hora **libre**. Un modal pide el email y un código de 6 dígitos (15 minutos).
2. La sesión queda en `localStorage` hasta que cierren sesión (en el servidor, una semana). Con sesión aparece el campo de nombre. Si ya hay cita, ese nombre viene relleno.
3. **Reservar:** elige una hora libre, pon el nombre, pulsa **Book**, confirma. La cita se guarda y después se envía el correo de reserva (o se escribe en el log, si el mailer es `log`). Si el correo falla, la reserva se queda.
4. **Cambiar:** elige otra hora libre futura, pulsa **Change**, confirma. La hora anterior queda libre y después se envía el correo de cambio. El nombre se puede editar en el mismo paso.

Si dos personas confirman la misma hora, gana la primera petición que Postgres confirma. La otra recibe “That slot is already taken.”

Cuando la cita ya pasó, ese correo puede volver a reservar.

---

## Backoffice

`/backoffice` es la misma grilla, con quién reservó cada hueco. Clic en un hueco para ver detalles o cancelar. Primero se envía el correo al cliente; si eso falla, la cita sigue. El correo incluye el enlace al calendario público (`FRONTEND_URL`).

---

## Configuración

Todo esto va en `.env.local`. `./deploy` lo inyecta en los contenedores.

### Puertos

Descomenta y fíjalos si no quieres que se elijan solos:

```env
FRONTEND_PORT=3000
BACKEND_PORT=8000
POSTGRES_PORT=5432
```

Si el puerto que pones ya está ocupado, `./deploy` sale con error; no adivina otro.

### Correo

Por defecto `MAIL_MAILER=log`: los mensajes van a los logs del contenedor backend, no a una bandeja. Para leerlos:

```bash
docker ps --filter "label=com.dwd.service=backend" --format '{{.Names}}'
docker logs -f <ese-nombre>
```

SMTP (ejemplo Gmail):

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

Gmail pide verificación en 2 pasos y una [contraseña de aplicación](https://myaccount.google.com/apppasswords). Después, `./deploy` otra vez.

Los correos de cancelación usan `FRONTEND_URL`. `./deploy` la pone en `http://localhost:<FRONTEND_PORT>` si no la defines.

### Datos de Postgres

Los archivos están en `./data/postgres` (o `POSTGRES_DATA_PATH`). Puedes borrar los contenedores; esa carpeta es la base de datos.

---

## API

URL base: `http://localhost:<BACKEND_PORT>/api` (la línea **api** de `./deploy`).

Solo JSON. `starts_at` es ISO-8601 UTC. `appointment` es `{ "name", "starts_at" }` o `null`.

Las rutas de visitante y admin esperan `Authorization: Bearer <token>`.

### Público

| Método | Ruta | Body / query | Respuesta |
| --- | --- | --- | --- |
| `GET` | `/slots` | `date=YYYY-MM-DD&timezone=Area/City` | `{ slots: [{ time, available }] }` |
| `POST` | `/session` | `{ email }` | envía un código de acceso |
| `POST` | `/session/confirm` | `{ email, code }` | `{ token, email, appointment }` |
| `POST` | `/session/discard` | `{ email }` | tira un código sin usar |
| `POST` | `/admin/login` | `{ email, password }` | `{ token, user }` |

### Visitante

| Método | Ruta | Body | Respuesta |
| --- | --- | --- | --- |
| `GET` | `/session/me` | | `{ email, appointment }` |
| `POST` | `/session/logout` | | |
| `POST` | `/appointments` | `{ name, date, time, timezone }` | crea o actualiza; `{ appointment }` |

### Admin

| Método | Ruta | Notas |
| --- | --- | --- |
| `GET` | `/admin/me` | `{ user }` |
| `POST` | `/admin/logout` | |
| `GET` | `/admin/slots` | la misma grilla; si está ocupado, `booking: { id, name, email }` |
| `DELETE` | `/admin/appointments/{id}` | cancela y avisa al cliente por correo |

---

## Parar

```bash
docker ps --filter "label=com.dwd.app=dance-with-death" -q | xargs -r docker rm -f
```

Eso quita los contenedores. `./data/postgres` se conserva.

## Resetear la base de datos

Borra citas, sesiones y usuarios de staff.

```bash
docker ps --filter "label=com.dwd.app=dance-with-death" -q | xargs -r docker rm -f
rm -rf data/postgres
./deploy
./admin-create you@example.com secretpass
```

---

## Si algo falla

| Síntoma | Qué mirar |
| --- | --- |
| `Missing .env.local` | Desde la raíz del repo: `cp .env.local.example .env.local`. |
| `APP_KEY is not set` | En `.env.local` tiene que haber `APP_KEY=base64:…`, no vacío. |
| `FRONTEND_PORT … is already in use` | Libera ese puerto, o quita la variable y deja que `./deploy` elija. |
| `No backend is running` en `./admin-create` | Primero `./deploy` y espera a que imprima `done`. |
| El código de acceso no llega | Con `MAIL_MAILER=log` el código está en los logs del backend, no en el correo. |
| El calendario muestra otras horas | Los huecos siguen la **zona horaria del navegador**. La API usa ese nombre IANA. |
| `Could not connect to PostgreSQL` | El primer arranque puede tardar unos segundos. Si sigue, revisa `DB_*` en `.env.local` y `docker ps`. |

Si necesitas encontrar un archivo:

```
deploy              arranca / reconstruye el stack
admin-create        crea un usuario de /backoffice
.env.local.example  copiar a .env.local
backend/            API Laravel
frontend/           sitio Nuxt
```
