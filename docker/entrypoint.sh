#!/bin/sh
# Entrypoint container Luwes.
#   CONTAINER_ROLE=app       : migrasi database, lalu jalankan Apache (web)
#   CONTAINER_ROLE=scheduler : jalankan scheduler Laravel (sinkron DB otomatis, dll.)
set -e
cd /var/www/html

if [ -d .env ]; then
    echo "ERROR: .env berupa folder. Hapus folder .env, jalankan 'make init' di host, lalu 'make up'." >&2
    exit 1
fi
if [ ! -f .env ]; then
    echo "ERROR: file .env tidak ditemukan. Jalankan 'make init' di host terlebih dahulu." >&2
    exit 1
fi

# Volume storage bisa kosong (mis. setelah reset): pastikan struktur folder ada & milik www-data.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

as_www() {
    setpriv --reuid=www-data --regid=www-data --init-groups "$@"
}

as_www php artisan config:cache >/dev/null
as_www php artisan view:cache >/dev/null

case "${CONTAINER_ROLE:-app}" in
    app)
        php artisan storage:link >/dev/null 2>&1 || true

        if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
            i=0
            until as_www php artisan migrate --force; do
                i=$((i + 1))
                if [ "$i" -ge 30 ]; then
                    echo "ERROR: migrasi gagal setelah 30 percobaan." >&2
                    exit 1
                fi
                echo "Menunggu database siap... ($i)"
                sleep 3
            done
        fi

        exec "$@"
        ;;
    scheduler)
        echo "Scheduler Luwes berjalan. Jadwal:"
        as_www php artisan schedule:list || true
        exec setpriv --reuid=www-data --regid=www-data --init-groups php artisan schedule:work
        ;;
    *)
        exec "$@"
        ;;
esac
