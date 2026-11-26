#!/bin/sh
#
# RYADA container entrypoint (issue #36, PR 1).
#
# No wait loops on purpose: compose starts this container only after mysql and redis
# report healthy (`depends_on` with `condition: service_healthy`).

set -eu

cd /var/www/html

# 1. Public storage link. The named volume is mounted at storage/, so the link is created
#    once and survives container recreation; storage:link itself is idempotent.
if [ ! -e public/storage ] && [ ! -L public/storage ]; then
    php artisan storage:link --no-interaction || true
fi

# 2. Drop any compiled config left behind by a previous command. The image ships no
#    config.php, so this is a cheap no-op on a fresh container.
php artisan config:clear --no-interaction || true

# 3. Migrations are opt-in and only for the FPM server container: compose sets
#    RUN_MIGRATIONS=true for the app service. One-off commands
#    (`docker compose run --rm app php artisan test`) must not touch the database.
if [ "${RUN_MIGRATIONS:-false}" = "true" ] && [ "${1:-}" = "php-fpm" ]; then
    php artisan migrate --force --no-interaction
fi

# 4. The test suite owns its configuration in phpunit.xml (SQLite :memory:, array cache,
#    sync queue, testing environment). PHPUnit's <env> entries do not override variables
#    that already exist in the process environment, and the container's env_file exports
#    the runtime values, so they have to be removed for the test command only. This is a
#    container concern; phpunit.xml and the application stay untouched.
is_test_command() {
    [ "${1:-}" = "php" ] && [ "${2:-}" = "artisan" ] && [ "${3:-}" = "test" ] && return 0
    [ "${1:-}" = "vendor/bin/pest" ] && return 0
    [ "${1:-}" = "vendor/bin/phpunit" ] && return 0

    return 1
}

if is_test_command "$@"; then
    # phpdotenv opens the environment file on every application boot. The image ships no
    # .env (the container is configured through real environment variables), and PHPUnit
    # records the resulting missing-file warning as a test warning on every test. An empty
    # file is enough: it contributes no values and Dotenv never overrides existing ones.
    # It exists only for this command and is removed again below.
    created_env=0

    if [ ! -f .env ]; then
        : >.env
        created_env=1
    fi

    set +e
    env \
        -u APP_ENV \
        -u APP_MAINTENANCE_DRIVER \
        -u BCRYPT_ROUNDS \
        -u BROADCAST_CONNECTION \
        -u CACHE_STORE \
        -u DB_CONNECTION \
        -u DB_DATABASE \
        -u DB_URL \
        -u LOG_CHANNEL \
        -u MAIL_MAILER \
        -u QUEUE_CONNECTION \
        -u SESSION_DRIVER \
        -u PULSE_ENABLED \
        -u TELESCOPE_ENABLED \
        -u NIGHTWATCH_ENABLED \
        "$@"
    status=$?
    set -e

    if [ "$created_env" = "1" ]; then
        rm -f .env
    fi

    exit "$status"
fi

# 5. exec so PID 1 is php-fpm and SIGTERM/SIGINT reach it (clean restarts and stops).
exec "$@"
