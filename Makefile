# Luwes — perintah Docker untuk PC server lokal.  Jalankan "make help".
SHELL := /bin/bash
COMPOSE := docker compose
APP := $(COMPOSE) exec app
ARTISAN := $(COMPOSE) exec -u www-data app php artisan

.DEFAULT_GOAL := help
.PHONY: help init up down restart build update ps logs logs-sync shell artisan migrate \
        schedule status sync sync-dry pma pma-down backup restore mysql ip

help: ## Tampilkan daftar perintah
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36mmake %-11s\033[0m %s\n", $$1, $$2}'

init: ## Buat .env dari .env.docker.example (APP_KEY & password DB lokal dibuat otomatis)
	@if [ -d .env ]; then echo "ERROR: .env adalah folder (dibuat Docker). Hapus dengan: rm -rf .env"; exit 1; fi
	@if [ -f .env ]; then \
		echo ".env sudah ada, tidak diubah."; \
	else \
		cp .env.docker.example .env; \
		sed -i "s|^APP_KEY=.*|APP_KEY=base64:$$(openssl rand -base64 32)|" .env; \
		sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$$(openssl rand -hex 16)|" .env; \
		sed -i "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=$$(openssl rand -hex 16)|" .env; \
		IP=$$(hostname -I 2>/dev/null | awk '{print $$1}'); \
		if [ -n "$$IP" ]; then sed -i "s|^APP_URL=.*|APP_URL=http://$$IP|" .env; fi; \
		echo ".env dibuat. APP_URL=http://$$IP"; \
	fi
	@grep -q '^DB_SERVER_HOST=.\+' .env || echo -e "\033[33mPERHATIAN: DB_SERVER_* di .env belum diisi -> sinkron ke hosting belum jalan. Isi lalu jalankan: make restart\033[0m"

up: init ## Build & jalankan semua service (web, scheduler, mysql)
	$(COMPOSE) up -d --build
	@echo ""
	@echo "Aplikasi: $$(grep '^APP_URL=' .env | cut -d= -f2-)   (cek status: make ps)"

down: ## Hentikan semua service (data tetap aman di volume)
	$(COMPOSE) --profile tools down

restart: ## Restart service (wajib setelah mengubah .env)
	$(COMPOSE) up -d --force-recreate app scheduler

build: ## Build ulang image tanpa cache
	$(COMPOSE) build --no-cache

update: ## Ambil kode terbaru dari GitHub, build ulang, migrasi otomatis
	git pull
	$(COMPOSE) up -d --build

ps: ## Status container
	$(COMPOSE) ps

logs: ## Log semua service (Ctrl+C untuk keluar)
	$(COMPOSE) logs -f --tail=100

logs-sync: ## Log scheduler / sinkron
	$(COMPOSE) logs -f --tail=100 scheduler

shell: ## Masuk ke shell container app
	$(COMPOSE) exec app bash

artisan: ## Jalankan perintah artisan, contoh: make artisan cmd="dbsync:run --group=penjualan"
	$(ARTISAN) $(cmd)

migrate: ## Jalankan migrasi database
	$(ARTISAN) migrate --force

schedule: ## Lihat jadwal scheduler (php artisan schedule:list)
	$(COMPOSE) exec -u www-data scheduler php artisan schedule:list

status: ## Cek koneksi ke database hosting & sinkron terakhir
	$(ARTISAN) dbsync:status

sync-dry: ## Lihat rencana sinkron tanpa mengubah data
	$(ARTISAN) dbsync:run --dry-run

sync: ## Sinkron sekarang (detail per tabel)
	$(ARTISAN) dbsync:run -v

pma: ## Jalankan phpMyAdmin di port PMA_PORT (default 8080)
	$(COMPOSE) --profile tools up -d phpmyadmin

pma-down: ## Matikan phpMyAdmin
	$(COMPOSE) --profile tools stop phpmyadmin

mysql: ## Masuk ke MySQL lokal
	$(COMPOSE) exec mysql sh -c 'mysql -uroot -p"$$MYSQL_ROOT_PASSWORD" "$$MYSQL_DATABASE"'

backup: ## Backup database lokal ke folder backups/
	@mkdir -p backups
	$(COMPOSE) exec -T mysql sh -c 'mysqldump -uroot -p"$$MYSQL_ROOT_PASSWORD" --single-transaction --routines "$$MYSQL_DATABASE"' \
		| gzip > backups/luwes_$$(date +%F_%H%M).sql.gz
	@ls -lh backups | tail -n 3

restore: ## Pulihkan backup, contoh: make restore file=backups/luwes_2026-10-20_2200.sql.gz
	@test -n "$(file)" || (echo "Pakai: make restore file=backups/xxx.sql.gz"; exit 1)
	gunzip -c $(file) | $(COMPOSE) exec -T mysql sh -c 'mysql -uroot -p"$$MYSQL_ROOT_PASSWORD" "$$MYSQL_DATABASE"'

ip: ## Tampilkan IP PC server (dibuka dari PC client)
	@hostname -I | awk '{print "Buka dari PC client: http://"$$1}'
