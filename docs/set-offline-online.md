# Panduan Instalasi Offline (PC Server Lokal dengan Docker) + Sinkronisasi ke Hosting

Panduan ini menjelaskan cara memasang aplikasi Luwes di **1 PC server lokal (CentOS) memakai Docker**, yang
dipakai oleh **3 PC client** di jaringan toko/gudang, lalu menyinkronkan databasenya **otomatis dua arah**
dengan aplikasi online di hosting: <https://newluwes.decaasoftwares.com/>.

Ringkasnya, di PC server cukup:

```bash
git clone https://github.com/ZensIDS/new_luwes.git luwes
cd luwes
make up          # pertama kali: buat .env, build, jalankan semua service
nano .env        # isi DB_SERVER_* (kredensial database hosting)
make restart
```

---

## 1. Gambaran Sistem

```
            INTERNET                                   JARINGAN LOKAL (LAN / WiFi toko)
 ┌──────────────────────────────┐                ┌───────────────────────────────────────────┐
 │ Hosting (Hostinger)          │                │  PC SERVER LOKAL (CentOS + Docker)        │
 │ newluwes.decaasoftwares.com  │◄── sinkron ────│   ├─ app       : web Laravel (Apache+PHP) │
 │ MySQL hosting (Remote MySQL) │   tiap 2 menit │   ├─ scheduler : sinkron DB otomatis      │
 └──────────────────────────────┘   (dua arah)   │   └─ mysql     : database lokal           │
          ▲                                      └───────────────▲───────────────────────────┘
          │ admin online                                         │ http://192.168.1.10
                                                  ┌──────────────┼───────────────┐
                                                  PC Client 1     PC Client 2     PC Client 3
                                                  (browser)       (browser)       (browser)
```

* **PC client** cukup membuka browser ke IP PC server. Tidak perlu instal apa pun.
* **PC server lokal** menjalankan 3 container Docker:
  * `app`: aplikasi web (port 80), otomatis menjalankan migrasi database saat start.
  * `scheduler`: menjalankan `php artisan schedule:work`, termasuk **sinkron DB ke hosting** tiap 2 menit.
  * `mysql`: database lokal, datanya tersimpan di Docker volume (aman walau container dihapus/di-update).
* Hosting tidak bisa menghubungi PC di jaringan lokal, tetapi PC server bisa menghubungi MySQL hosting
  lewat internet. Karena itu sinkron selalu dijalankan dari PC server.
* Saat internet putus, toko tetap bisa bekerja dengan database lokal. Begitu internet kembali, semua
  data yang tertunda otomatis dikirim/diambil pada sinkron berikutnya.
* Semua container memakai `restart: unless-stopped`: setelah PC server mati lampu / restart, aplikasi
  **otomatis jalan lagi**.

### Aturan sinkron

| Kejadian | Hasil |
|---|---|
| Data baru dibuat di lokal | Disalin ke hosting |
| Data baru dibuat di hosting | Disalin ke lokal |
| Data diubah di satu sisi saja | Perubahan disalin ke sisi lain |
| Data dihapus di satu sisi (sisi lain tidak mengubah) | Ikut dihapus di sisi lain |
| Data dihapus di satu sisi, tetapi **diubah** di sisi lain | Data **tidak** hilang: versi yang diubah dipulihkan ke dua sisi |
| Baris yang sama diubah di dua sisi, **kolom berbeda** (mis. nama di lokal, harga di hosting) | Digabung: nama dari lokal + harga dari hosting |
| **Stok / saldo kas** berubah di dua sisi (mis. terjual 3 di kasir offline, masuk 10 dari hosting) | Dijumlahkan: stok lama − 3 + 10 |
| Baris yang sama, **kolom yang sama** diubah berbeda di dua sisi | **KONFLIK**: baris **dilewati** (tidak disinkron), dua sisi tetap dengan datanya masing-masing, muncul **peringatan** di dashboard |
| Menghapus banyak data sekaligus (> 25% tabel, min. 20 baris) | **Ditahan** sebagai pengaman, muncul peringatan |

Tabel yang tidak disinkron (data teknis per-mesin): `migrations`, `jobs`, `failed_jobs`, `job_batches`,
`password_resets`, `personal_access_tokens`, `sessions`, `cache`, `cart_storage`, `db_sync_*`.

---

## 2. Kebutuhan

**PC Server lokal**
* CentOS Stream 9 (atau Rocky Linux 9 / AlmaLinux 9, perintahnya sama). CentOS 7 sudah tidak didukung, jangan dipakai.
* RAM minimal 4 GB, SSD minimal 120 GB.
* Terhubung ke router/switch toko dengan **kabel LAN** (lebih stabil daripada WiFi).
* Internet (untuk sinkron ke hosting & download image Docker saat instalasi).
* Akses ke repository GitHub `ZensIDS/new_luwes` (akun GitHub yang diundang ke repo, atau *Personal Access Token*).

**PC Client (3 unit)**
* Windows / Linux apa saja dengan browser Google Chrome atau Firefox.
* Satu jaringan dengan PC server (router/switch yang sama).

**Hosting**
* Akses hPanel Hostinger (menu **Databases**), dan akses SSH / Terminal untuk menjalankan `php artisan`.

---

## 3. Pengaturan Dasar CentOS

Instal CentOS Stream 9 (pilihan *Server* atau *Minimal Install*), lalu login sebagai user dengan `sudo`.

```bash
# Update sistem & paket yang dibutuhkan
sudo dnf -y update
sudo dnf -y install git make openssl curl nano

# Zona waktu & sinkron jam (PENTING: jam yang salah membuat aturan "data terbaru" keliru)
sudo timedatectl set-timezone Asia/Jakarta
sudo dnf -y install chrony
sudo systemctl enable --now chronyd
timedatectl            # pastikan "System clock synchronized: yes"
```

### 3.1 Beri IP statis untuk PC server

PC client akan selalu membuka IP ini, jadi IP-nya **tidak boleh berubah**.

```bash
# Lihat nama koneksi & IP sekarang
nmcli connection show
ip -4 addr
```

Contoh: nama koneksi `enp3s0`, router `192.168.1.1`, IP server yang dipilih `192.168.1.10`
(pilih IP di luar rentang DHCP router agar tidak dipakai perangkat lain):

```bash
sudo nmcli connection modify enp3s0 \
  ipv4.method manual \
  ipv4.addresses 192.168.1.10/24 \
  ipv4.gateway 192.168.1.1 \
  ipv4.dns "8.8.8.8 1.1.1.1"
sudo nmcli connection up enp3s0

# Cek
hostname -I            # harus muncul 192.168.1.10
ping -c 3 google.com   # internet harus jalan
```

> Alternatif: atur **DHCP reservation** di router untuk MAC address PC server ke `192.168.1.10`.

---

## 4. Instalasi Docker & Aplikasi

### 4.1 Instal Docker + Docker Compose

```bash
# CentOS membawa podman yang bentrok dengan Docker, hapus dulu (aman bila belum terpasang)
sudo dnf -y remove podman buildah runc

sudo dnf -y install dnf-plugins-core
sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
sudo dnf -y install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker

# Agar bisa menjalankan docker tanpa sudo (logout lalu login lagi setelah perintah ini)
sudo usermod -aG docker $USER
```

Setelah login ulang, cek:

```bash
docker version
docker compose version     # harus v2.x
```

### 4.2 Ambil kode & jalankan

```bash
cd ~
git clone https://github.com/ZensIDS/new_luwes.git luwes
cd luwes
git checkout main          # versi yang SAMA dengan yang ada di hosting

make up
```

`make up` pertama kali akan:

1. Membuat file `.env` dari `.env.docker.example`, dengan `APP_KEY`, password database lokal, dan `APP_URL`
   (IP PC server) **dibuat otomatis**.
2. Build image aplikasi (PHP 8.2 + Apache + asset Vite). Pertama kali ±5–10 menit, tergantung internet.
3. Menjalankan `mysql`, `app` (otomatis `php artisan migrate`), dan `scheduler`.

Cek semua container sudah jalan:

```bash
make ps
#   luwes-app-1         Up ... (healthy)   0.0.0.0:80->80/tcp
#   luwes-mysql-1       Up ... (healthy)
#   luwes-scheduler-1   Up ...
make schedule
#   */2 * * * *  php artisan dbsync:run ....... Next Due: ...
```

> Tanpa `make`? Bisa juga: `cp .env.docker.example .env`, isi `APP_KEY` (`base64:` + `openssl rand -base64 32`),
> `DB_PASSWORD`, `DB_ROOT_PASSWORD`, lalu `docker compose up -d --build`.
> **Jangan** menjalankan `docker compose up` sebelum file `.env` ada (Docker akan membuat folder `.env` kosong).

### 4.3 Firewall

Docker membuka port 80 sendiri. Bila PC client tetap tidak bisa membuka aplikasi:

```bash
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --reload
```

---

## 5. Menyiapkan Database Hosting untuk Sinkron

### 5.1 Izinkan Remote MySQL di Hostinger

1. Login **hPanel** → **Websites** → pilih `newluwes.decaasoftwares.com` → **Databases** → **Remote MySQL**.
2. Di **Host**, isi IP publik internet toko (cek dari PC server: `curl -s ifconfig.me`).
   Jika IP publik toko **berubah-ubah** (umumnya IndiHome/ISP rumahan), centang **Any Host** (`%`).
3. Pilih database aplikasi (mis. `u1342798_luwes_kasir`), klik **Create**.
4. Catat **hostname/IP server MySQL** yang ditampilkan di halaman **Remote MySQL** / **Management**
   (contoh: `45.130.231.160` atau `srvXXX.hstgr.io`). Inilah nilai `DB_SERVER_HOST`.

> Karena database bisa diakses dari internet, gunakan **password database yang kuat**.

### 5.2 Atur `.env` di hosting

Tambahkan baris berikut ke `.env` aplikasi **di hosting**:

```dotenv
# Hosting = offset 2 (id genap). Server lokal = offset 1 (id ganjil).
DB_AUTO_INCREMENT_INCREMENT=2
DB_AUTO_INCREMENT_OFFSET=2

# Hosting TIDAK menjalankan sinkron
DB_SYNC_ENABLED=false
DB_SERVER_HOST=
```

Lalu, lewat SSH / Terminal hosting:

```bash
cd ~/domains/newluwes.decaasoftwares.com/public_html   # sesuaikan dengan folder aplikasi
php artisan migrate --force          # membuat tabel db_sync_runs & db_sync_rows
php artisan optimize:clear
```

> Mengapa offset? Dua database membuat data baru secara bersamaan. Dengan offset, data baru di lokal
> selalu ber-id ganjil dan di hosting ber-id genap, jadi **tidak pernah bentrok id**.

---

## 6. Isi `.env` di PC Server

File `.env` sudah dibuat oleh `make up`. Isi bagian **database hosting** (dari langkah 5.1):

```bash
nano .env
```

```dotenv
# --- Database HOSTING (tujuan sinkron) — WAJIB DIISI ---
DB_SERVER_CONNECTION=mysql
DB_SERVER_HOST=45.130.231.160
DB_SERVER_PORT=3306
DB_SERVER_DATABASE=u1342798_luwes_kasir
DB_SERVER_USERNAME=u1342798_xxxxx
DB_SERVER_PASSWORD='password_database_hosting'
```

> Password yang mengandung karakter `$`, `#`, atau spasi **wajib** diapit tanda kutip tunggal `'...'`.

Bagian lain sudah terisi otomatis dan **jangan diubah** kecuali perlu:

| Variabel | Nilai | Keterangan |
|---|---|---|
| `APP_URL` | `http://IP-PC-SERVER` | Diisi otomatis; sesuaikan bila IP server berubah |
| `APP_PORT` | `80` | Port web di PC server |
| `DB_HOST` | `mysql` | Nama container MySQL lokal, **jangan** diganti `127.0.0.1` |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD` | acak | Password MySQL lokal (dibuat otomatis) |
| `DB_AUTO_INCREMENT_INCREMENT` / `_OFFSET` | `2` / `1` | Server lokal = id ganjil (hosting = offset 2) |
| `DB_SYNC_ENABLED` | `true` | Sinkron otomatis aktif |
| `DB_SYNC_INTERVAL` | `2` | Menit antar sinkron |
| `DB_SYNC_CONFLICT` | `skip` | `skip` = konflik dilewati + peringatan; `newest` = otomatis pakai data terbaru |
| `DB_SERVER_TIMEOUT` | `5` | (opsional) batas waktu koneksi ke hosting, detik |
| `DB_SYNC_EXCLUDE_TABLES` | – | (opsional) tabel tambahan yang tidak disinkron, pisahkan koma |

> **Penting:** password `DB_PASSWORD` / `DB_ROOT_PASSWORD` dipakai saat volume MySQL **pertama kali** dibuat.
> Jangan diubah setelah `make up` pertama, atau MySQL lokal tidak bisa diakses.

Terapkan perubahan `.env`:

```bash
make restart
```

---

## 7. Ambil Data dari Hosting (Sinkron Pertama)

Database lokal masih kosong (hanya struktur tabel dari migrasi). Sinkron pertama menyalin semua data hosting.

```bash
# 1) Cek koneksi ke database hosting
make status
#   Koneksi : OK (35 ms, 10.11.x-MariaDB)

# 2) Lihat rencana tanpa mengubah apa pun
make sync-dry

# 3) Jalankan sinkron pertama sambil melihat detail per tabel
make sync
#   Sinkron success: 48210 baris berubah, 0 konflik dilewati, 0 error, 95000 ms.

# 4) Sinkron selanjutnya berjalan otomatis tiap 2 menit oleh container scheduler
make logs-sync
```

> Bila langkah 3 dilewati, container `scheduler` tetap akan melakukan sinkron pertama sendiri dalam ±2 menit.

---

## 8. Menghubungkan 3 PC Client

1. Sambungkan PC client ke router/switch yang **sama** dengan PC server.
2. Cek IP server dari PC server: `make ip` (atau `hostname -I`) → contoh `192.168.1.10`.
   Bila `APP_PORT` di `.env` bukan 80, tambahkan port di alamat, mis. `http://192.168.1.10:8000`.
3. Di PC client, buka **Command Prompt** dan tes koneksi:
   ```
   ping 192.168.1.10
   ```
   Harus ada balasan (`Reply from 192.168.1.10 ...`). Jika *Request timed out*, cek kabel/WiFi dan
   firewall server (lihat bagian 4.3) dan `make ps` (container `app` harus *healthy*).
4. Buka browser (Chrome) di PC client, ketik: **`http://192.168.1.10`**.
5. Login memakai akun yang sama seperti di hosting (data user ikut tersinkron dari hosting).
6. Pastikan data (produk, stok, penjualan) sama dengan yang ada di
   <https://newluwes.decaasoftwares.com/>. Itu tandanya data sudah berhasil diambil dari DB server hosting.
7. Buat shortcut di desktop: klik kanan desktop → **New → Shortcut** → isi `http://192.168.1.10` → beri nama
   **Luwes Kasir**. Ulangi di ketiga PC client.

**Opsional: memakai nama alih-alih IP.** Di setiap PC client Windows, buka Notepad *as Administrator*,
edit `C:\Windows\System32\drivers\etc\hosts`, lalu tambahkan:

```
192.168.1.10   luwes.local
```

Setelah itu aplikasi bisa dibuka dengan `http://luwes.local`.

---

## 9. Kartu "Status Database Server Hosting" di Dashboard

Di dashboard (role admin-gudang, owner, superadmin), di bawah kartu **Stok Mendekati Expired**, ada kartu
status yang diperbarui otomatis setiap 60 detik (tombol ↻ untuk cek ulang):

| Label | Arti |
|---|---|
| 🟢 **Sehat · Terhubung** | Koneksi ke MySQL hosting OK dan sinkron terakhir berhasil |
| 🟡 **Peringatan** | Ada **konflik dilewati**, sebagian baris gagal, atau belum ada sinkron berhasil dalam 10 menit (cron mati / internet putus) |
| 🔴 **Error** | Tidak bisa terhubung ke database hosting (password salah, Remote MySQL belum diizinkan, internet mati) atau sinkron gagal total |
| ⚪ **Belum dikonfigurasi** | `DB_SERVER_*` belum diisi |

Di **hosting**, kartu yang sama menampilkan kapan **server lokal** terakhir melakukan sinkron. Jika PC server
mati atau internet toko putus, kartu di hosting berubah menjadi **Peringatan**.

Cek dari terminal PC server:

```bash
make status
```

### 9.1 Tombol aksi (khusus owner & superadmin, di server lokal)

| Tombol | Sama dengan perintah |
|---|---|
| **Cek Koneksi** | `php artisan dbsync:status` |
| **Lihat Rencana (dry-run)** | `php artisan dbsync:run --dry-run` (hanya menampilkan apa yang akan berubah, tidak mengubah data) |
| **Sinkron Sekarang** | `php artisan dbsync:run -v` |
| Pilihan **Modul** + tombol di atas | `php artisan dbsync:run --group=...` |
| Centang **Izinkan hapus massal** | `--force` |
| Tombol **Lokal / Hosting / Terbaru** di daftar konflik | `php artisan dbsync:resolve ...` |

**Modul.** Tabel saling terkait (produk ↔ stok ↔ pembelian ↔ penjualan), jadi sinkron sebagian dipilih per
**modul**, bukan per tabel. Tabel induknya otomatis ikut. Contoh: memilih *Penjualan & Kasir* juga menyinkron
`products`, `owner_stocks`, `stocks`, `outlets`, `users`, `categories`, dst. Daftar tabel yang ikut tampil di bawah
pilihan modul. **Tidak memilih modul = semua tabel (disarankan)**. Sinkron hanya mengirim baris yang berubah,
jadi sinkron semua tabel tetap cepat.

| Kunci modul (`--group`) | Isi |
|---|---|
| `master` | User, outlet, kategori, supplier, bank, metode bayar, salesman, slider |
| `produk_stok` | Produk, harga outlet, stok gudang & outlet, mutasi & penyesuaian stok |
| `pembelian` | Pembelian (PO), penerimaan, retur supplier, belanja outlet, beserta stok & kas |
| `distribusi` | Request order, picking list, delivery order, beserta stok |
| `penjualan` | Penjualan, kasir/shift, retur penjualan, nomor invoice, beserta stok & kas |
| `promo` | Promo & voucher |
| `keuangan` | Kas, pengeluaran, pendapatan lain |
| `log` | Log aktivitas |
| `lainnya` | Tabel yang belum masuk modul mana pun (muncul otomatis bila ada) |

Isi modul bisa diubah di `config/dbsync.php` bagian `groups` (lalu `make update`).

Dari terminal: `make sync`, `make sync-dry`, atau `make artisan cmd="dbsync:run --group=penjualan"`.

---

## 10. Menangani Konflik

Konflik terjadi bila **kolom yang sama** pada **baris yang sama** diubah ke nilai berbeda di lokal dan di
hosting di antara dua sinkron. Contoh: nama produk #4 diganti "A" di kasir offline dan "B" di hosting.
Baris tersebut **tidak disinkron** (tidak ada data yang tertimpa), dan peringatan muncul di dashboard:

```
products: KONFLIK id 4 dilewati (name: lokal "A" vs hosting "B").
Selesaikan: php artisan dbsync:resolve products 4 --keep=local|server|newest
```

**Dari dashboard:** pada daftar *Konflik dilewati*, klik **Lihat perbedaan** untuk membuka tabel perbandingan
per kolom: nilai **saat sinkron terakhir**, nilai **lokal**, dan nilai **hosting** saat ini, beserta statusnya:

| Status | Arti |
|---|---|
| 🔴 Konflik | Kolom diubah berbeda di dua sisi (penyebab baris dilewati) |
| 🔵 Lokal berubah | Hanya berubah di lokal |
| 🟠 Hosting berubah | Hanya berubah di hosting |
| 🟢 Digabung | Digabung otomatis (stok/saldo dijumlahkan, waktu terbaru dipakai) |

Kolom versi yang terakhir diubah ditandai *(terbaru)*. Lalu klik **Lokal**, **Hosting**, atau **Terbaru**
(khusus owner & superadmin).

**Dari terminal PC server:** pilih versi yang benar, lalu jalankan:

```bash
make artisan cmd="dbsync:resolve products 4 --keep=server"   # pakai versi hosting
make artisan cmd="dbsync:resolve products 4 --keep=local"    # pakai versi lokal
make artisan cmd="dbsync:resolve products 4 --keep=newest"   # pakai yang terakhir diubah
```

Bila ingin konflik **otomatis** diselesaikan dengan data terbaru (tanpa dilewati), ubah `.env` server lokal:
`DB_SYNC_CONFLICT=newest`, lalu `make restart`.

---

## 11. Pemeliharaan & Hal yang WAJIB Diperhatikan

* **Update aplikasi:** jalankan `php artisan migrate --force` di **hosting**, lalu di PC server:
  ```bash
  make update        # git pull + build ulang + migrasi otomatis
  ```
  Selama migrasi hosting & lokal belum sama, sinkron berhenti dengan pesan
  *"Struktur database berbeda. Jalankan php artisan migrate di ..."*.
* **Jangan pernah** menjalankan `migrate:fresh`, `db:wipe`, `docker compose down -v`, atau mengosongkan tabel
  di salah satu sisi. `down -v` **menghapus volume database lokal**. Penghapusan massal akan ditahan
  pengaman sinkron, tetapi lebih baik jangan sampai terjadi. Jika memang disengaja:
  `make artisan cmd="dbsync:run --group=produk_stok --force"`.
* **Jangan** menjalankan stack ini (dengan `DB_SYNC_ENABLED=true`) di lebih dari **satu** PC server.
* **Backup harian** database lokal ke folder `backups/`:
  ```bash
  make backup
  # otomatis tiap malam jam 22:00 (crontab user yang menjalankan docker):
  crontab -e
  0 22 * * * cd $HOME/luwes && make backup >/dev/null 2>&1
  0 23 * * * find $HOME/luwes/backups -name 'luwes_*.sql.gz' -mtime +14 -delete
  ```
  Memulihkan: `make restore file=backups/luwes_2026-10-20_2200.sql.gz`. Data lama hasil restore akan ikut
  tersinkron ke hosting, jadi lakukan hanya bila benar-benar perlu.
* Melihat isi database lewat browser: `make pma` lalu buka `http://IP-SERVER:8080`
  (login `root` + `DB_ROOT_PASSWORD` dari `.env`). Matikan lagi dengan `make pma-down`.

---

## 12. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| `Access denied for user '...'@'IP'` | IP toko belum ditambahkan di **Remote MySQL** hosting (pakai *Any Host*), atau `DB_SERVER_USERNAME` / `DB_SERVER_PASSWORD` salah. Username database (`u1342798_xxx`) berbeda dengan username login panel (`u1342798`). |
| `Connection timed out` / `Can't connect to MySQL server` | Internet putus, `DB_SERVER_HOST` salah, atau ISP memblokir port 3306. Tes: `curl -v telnet://DB_SERVER_HOST:3306`. |
| `isi DB_ROOT_PASSWORD di .env` saat `docker compose up` | `.env` belum dibuat. Jalankan `make init` (atau `make up`). |
| Error `.env berupa folder` | `docker compose up` dijalankan sebelum `.env` ada. `rm -rf .env && make up`. |
| `Struktur database berbeda` | Versi aplikasi berbeda. Samakan kode (`make update`) dan jalankan `php artisan migrate --force` di hosting. |
| `kemungkinan id bentrok` | Hosting belum memakai `DB_AUTO_INCREMENT_OFFSET=2`. Perbaiki `.env` hosting, lalu selesaikan baris itu (bagian 10). |
| `Penghapusan N baris ... ditahan` | Ada penghapusan massal. Periksa apakah disengaja; jika ya, jalankan dengan `--force`. |
| Peringatan "belum ada sinkron berhasil dalam 10 menit" | Container scheduler mati: `make ps`, `make logs-sync`, lalu `make restart`. |
| Container `app` tidak *healthy* / web error 500 | `make logs`, dan log Laravel: `make shell` lalu `tail -100 storage/logs/laravel-*.log`. |
| PC client tidak bisa membuka `http://IP-SERVER` | Cek `ping`, `make ps`, firewall (bagian 4.3), dan apakah port 80 dipakai program lain (`sudo ss -ltnp`, cari `:80`); bila ya, ganti `APP_PORT` di `.env` lalu `make restart`. |
| `permission denied ... docker.sock` | User belum masuk grup docker: `sudo usermod -aG docker $USER`, lalu logout & login. |
| Perubahan `.env` tidak berpengaruh | Jalankan `make restart` (konfigurasi di-cache saat container start). |

---

## 13. Ringkasan Perintah (`make help`)

```bash
make up                 # build & jalankan semua service (pertama kali juga membuat .env)
make restart            # wajib setelah mengubah .env
make update             # git pull + build ulang + migrasi
make down               # hentikan semua service (data aman)
make ps                 # status container
make logs / logs-sync   # log semua service / log sinkron
make ip                 # IP yang dibuka dari PC client

make status             # cek koneksi hosting & sinkron terakhir   (php artisan dbsync:status)
make sync-dry           # rencana sinkron tanpa mengubah data       (php artisan dbsync:run --dry-run)
make sync               # sinkron sekarang                          (php artisan dbsync:run -v)
make schedule           # jadwal scheduler                          (php artisan schedule:list)
make artisan cmd="dbsync:run --group=penjualan"                     # sinkron satu modul (+ tabel terkait)
make artisan cmd="dbsync:run --group=produk_stok --force"           # izinkan penghapusan massal yang ditahan
make artisan cmd="dbsync:resolve products 4 --keep=server"          # selesaikan konflik

make backup             # backup DB lokal ke backups/
make restore file=...   # pulihkan backup
make mysql              # masuk MySQL lokal
make pma / pma-down     # phpMyAdmin di port 8080
make shell              # masuk container app
```
