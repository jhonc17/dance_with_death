#!/bin/sh
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
fi

# Write Laravel's .env from the container environment (.env.local → compose → env).
cat > .env <<EOF
APP_NAME="${APP_NAME:-Dance with Death}"
APP_ENV=${APP_ENV:-local}
APP_KEY=${APP_KEY}
APP_DEBUG=${APP_DEBUG:-true}
APP_URL=${APP_URL:-http://localhost:8000}
FRONTEND_URL=${FRONTEND_URL:-http://localhost:3000}
APP_TIMEZONE=${APP_TIMEZONE:-UTC}

DB_CONNECTION=pgsql
DB_HOST=${DB_HOST:-postgres}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE:-dance_with_death}
DB_USERNAME=${DB_USERNAME:-dance}
DB_PASSWORD=${DB_PASSWORD:-dance_secret}

CORS_ALLOWED_ORIGINS=${CORS_ALLOWED_ORIGINS:-http://localhost:3000}

CACHE_STORE=${CACHE_STORE:-file}

MAIL_MAILER=${MAIL_MAILER:-log}
MAIL_HOST="${MAIL_HOST:-}"
MAIL_PORT="${MAIL_PORT:-587}"
MAIL_SCHEME="${MAIL_SCHEME:-smtp}"
MAIL_USERNAME="${MAIL_USERNAME:-}"
MAIL_PASSWORD="${MAIL_PASSWORD:-}"
MAIL_FROM_ADDRESS="${MAIL_FROM_ADDRESS:-${MAIL_USERNAME:-noreply@dancewithdeath.test}}"
MAIL_FROM_NAME="${MAIL_FROM_NAME:-${APP_NAME:-Dance with Death}}"
EOF

if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY is not set. Put it in .env.local."
  exit 1
fi

php artisan config:clear >/dev/null 2>&1 || true

echo "Waiting for PostgreSQL at ${DB_HOST:-postgres}:${DB_PORT:-5432}..."
i=0
until php -r '
$host = getenv("DB_HOST") ?: "postgres";
$port = getenv("DB_PORT") ?: "5432";
$db   = getenv("DB_DATABASE") ?: "dance_with_death";
$user = getenv("DB_USERNAME") ?: "dance";
$pass = getenv("DB_PASSWORD") ?: "dance_secret";
try {
  new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $pass);
  exit(0);
} catch (Throwable $e) {
  fwrite(STDERR, $e->getMessage() . PHP_EOL);
  exit(1);
}
' 2>/tmp/db-wait.err; do
  i=$((i + 1))
  if [ "$i" -ge 30 ]; then
    echo "Could not connect to PostgreSQL."
    echo "Check that postgres is healthy and that DB_* in .env.local is right."
    cat /tmp/db-wait.err
    exit 1
  fi
  sleep 2
done

php artisan migrate --force

exec "$@"
