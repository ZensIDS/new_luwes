#!/usr/bin/env bash
# Membuat / melengkapi .env untuk PC server lokal (dipanggil oleh "make init", "make up", "make hosting").
#
#   bash docker/init-env.sh            -> buat .env bila belum ada (+ tanya kredensial hosting)
#   bash docker/init-env.sh --hosting  -> isi ulang kredensial database hosting (DB_SERVER_*)
#
# Hanya memakai bash + coreutils (tidak butuh php/openssl di host): semua aplikasi berjalan di Docker.
set -euo pipefail
cd "$(dirname "$0")/.."

ENV_FILE=.env
EXAMPLE=.env.docker.example
YELLOW=$'\033[33m'
GREEN=$'\033[32m'
RESET=$'\033[0m'

# Ganti nilai KEY=... di .env tanpa sed, supaya karakter khusus di password ($ / & |) aman.
set_env() {
    local key=$1 value=$2 tmp found=0
    tmp=$(mktemp)
    while IFS= read -r line || [ -n "$line" ]; do
        if [[ $line == "$key="* ]]; then
            printf '%s=%s\n' "$key" "$value"
            found=1
        else
            printf '%s\n' "$line"
        fi
    done < "$ENV_FILE" > "$tmp"
    [ "$found" = 1 ] || printf '%s=%s\n' "$key" "$value" >> "$tmp"
    cat "$tmp" > "$ENV_FILE"
    rm -f "$tmp"
}

get_env() {
    grep -E "^$1=" "$ENV_FILE" | tail -n 1 | cut -d= -f2- || true
}

# Nilai dengan karakter khusus diapit kutip tunggal (literal untuk Laravel & Docker Compose).
quote() {
    local v=$1
    if [[ $v =~ [[:space:]\$\#\"\\\`] ]]; then
        printf "'%s'" "$v"
    else
        printf '%s' "$v"
    fi
}

random_hex() { head -c "$1" /dev/urandom | od -An -tx1 | tr -d ' \n'; }

interactive() { [ -t 0 ] || [ "${INIT_INTERACTIVE:-0}" = 1 ]; }

ask_hosting() {
    echo
    echo "Kredensial database HOSTING (panduan langkah 4.1). Tekan Enter tanpa isi untuk melewati."
    read -rp "  DB_SERVER_HOST     (IP/hostname MySQL hosting): " host
    if [ -z "$host" ]; then
        echo "${YELLOW}Dilewati. Isi nanti dengan: make hosting${RESET}"
        return
    fi
    read -rp "  DB_SERVER_PORT     [3306]: " port
    read -rp "  DB_SERVER_DATABASE (mis. u1342798_luwes_kasir): " database
    read -rp "  DB_SERVER_USERNAME (user database, BUKAN username panel): " username
    read -rsp "  DB_SERVER_PASSWORD (tidak tampil saat diketik): " password
    echo

    # Host MySQL bukan URL: buang "http://", "/..." dan ":port" bila ikut diketik.
    local cleaned=${host#*://}
    cleaned=${cleaned%%/*}
    if [[ $cleaned == *:* ]]; then
        port=${port:-${cleaned##*:}}
        cleaned=${cleaned%%:*}
    fi
    if [ "$cleaned" != "$host" ]; then
        echo "${YELLOW}DB_SERVER_HOST diubah menjadi: $cleaned (tanpa http:// atau /)${RESET}"
        host=$cleaned
    fi

    if [ -z "$database" ] || [ -z "$username" ] || [ -z "$password" ]; then
        echo "${YELLOW}PERHATIAN: database/username/password hosting ada yang kosong -> koneksi ke hosting akan gagal.${RESET}"
        echo "${YELLOW}Lengkapi nanti dengan: make hosting (atau edit .env lalu: make restart)${RESET}"
    fi

    if [[ $password == *"'"* ]]; then
        echo "${YELLOW}PERHATIAN: password mengandung tanda kutip tunggal ('), periksa baris DB_SERVER_PASSWORD di .env.${RESET}"
    fi

    set_env DB_SERVER_HOST "$host"
    set_env DB_SERVER_PORT "${port:-3306}"
    set_env DB_SERVER_DATABASE "$database"
    set_env DB_SERVER_USERNAME "$username"
    set_env DB_SERVER_PASSWORD "$(quote "$password")"
    echo "${GREEN}Kredensial hosting disimpan di .env.${RESET}"
}

if [ -d "$ENV_FILE" ]; then
    echo "ERROR: .env adalah folder (dibuat Docker). Hapus dengan: rm -rf .env" >&2
    exit 1
fi

if [ "${1:-}" = "--hosting" ]; then
    [ -f "$ENV_FILE" ] || { echo "ERROR: .env belum ada, jalankan: make up" >&2; exit 1; }
    ask_hosting
    exit 0
fi

if [ -f "$ENV_FILE" ]; then
    echo ".env sudah ada, tidak diubah."
else
    cp "$EXAMPLE" "$ENV_FILE"
    set_env APP_KEY "base64:$(head -c 32 /dev/urandom | base64)"
    set_env DB_PASSWORD "$(random_hex 16)"
    set_env DB_ROOT_PASSWORD "$(random_hex 16)"

    ip=$(hostname -I 2>/dev/null | awk '{print $1}')
    [ -n "$ip" ] && set_env APP_URL "http://$ip"
    echo "${GREEN}.env dibuat${RESET} (APP_KEY & password database lokal dibuat otomatis, APP_URL=http://${ip:-localhost})."

    if interactive; then
        ask_hosting
    fi
fi

# Build image + MySQL butuh ruang disk; cek lebih awal daripada gagal di tengah build.
docker_root=$(docker info --format '{{.DockerRootDir}}' 2>/dev/null || true)
docker_root=${docker_root:-/var/lib/docker}
[ -d "$docker_root" ] || docker_root=/
free_gb=$(df -Pk "$docker_root" | awk 'NR==2 {print int($4 / 1048576)}')
if [ "${free_gb:-0}" -lt "${MIN_FREE_GB:-6}" ]; then
    echo "${YELLOW}PERHATIAN: sisa disk untuk Docker ($docker_root) hanya ${free_gb} GB, minimal ${MIN_FREE_GB:-6} GB.${RESET}" >&2
    echo "${YELLOW}Build bisa gagal dengan 'No space left on device'. Perbesar disk dulu (lihat panduan, bagian Troubleshooting).${RESET}" >&2
    if [ "${SKIP_DISK_CHECK:-0}" != 1 ]; then
        echo "Lanjutkan tetap dengan: SKIP_DISK_CHECK=1 make up" >&2
        exit 1
    fi
fi

if [ -z "$(get_env DB_SERVER_HOST)" ]; then
    echo "${YELLOW}PERHATIAN: DB_SERVER_* belum diisi -> sinkron ke hosting belum berjalan. Isi dengan: make hosting${RESET}"
fi
