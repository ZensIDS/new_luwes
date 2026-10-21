# Luwes — perintah Docker untuk PC server lokal.  Jalankan "make help".
SHELL := /bin/bash
COMPOSE := docker compose
APP := $(COMPOSE) exec app
ARTISAN := $(COMPOSE) exec -u www-data app php artisan

.DEFAULT_GOAL := help
.PHONY: help init hosting up down restart build update ps logs logs-sync shell artisan migrate \
        schedule status sync sync-dry pma pma-down backup restore mysql ip

help: ## Tampilkan daftar perintah
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36mmake %-11s\033[0m %s\n", $$1, $$2}'

init: ## Buat .env dari .env.docker.example (APP_KEY & password DB lokal dibuat otomatis)
	@bash docker/init-env.sh

hosting: ## Isi / ganti kredensial database hosting (DB_SERVER_*) lalu restart
	@bash docker/init-env.sh --hosting
	@if [ -n "$$($(COMPOSE) ps -q app 2>/dev/null)" ]; then $(MAKE) --no-print-directory restart; fi

up: init ## Build & jalankan semua service (web, scheduler, mysql)
	$(COMPOSE) up -d --build
	@echo ""
	@echo "Menunggu aplikasi siap (migrasi database)..."
	@for i in $$(seq 1 60); do \
		[ "$$($(COMPOSE) ps --format '{{.Health}}' app 2>/dev/null)" = "healthy" ] && break; sleep 3; \
	done
	@$(COMPOSE) ps
	@echo ""
	@$(ARTISAN) dbsync:status || true
	@echo ""
	@echo "Aplikasi: $$(grep '^APP_URL=' .env | cut -d= -f2-)   (dibuka dari PC client)"

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
