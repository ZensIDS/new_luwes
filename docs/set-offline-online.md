# Panduan Instalasi Offline (PC Server Lokal) + Sinkronisasi ke Hosting

Panduan ini menjelaskan cara memasang aplikasi Luwes di **1 PC server lokal (CentOS)** yang dipakai oleh
**3 PC client** di jaringan toko/gudang, lalu menyinkronkan databasenya **otomatis dua arah** dengan
aplikasi online di hosting: <https://newluwes.decaasoftwares.com/>.

---

## 1. Gambaran Sistem

```
            INTERNET                                   JARINGAN LOKAL (LAN / WiFi toko)
 ┌──────────────────────────────┐                ┌───────────────────────────────────────────┐
 │ Hosting (Hostinger)          │                │  PC SERVER LOKAL (CentOS)  192.168.1.10   │
 │ newluwes.decaasoftwares.com  │◄── sinkron ────│  Apache + PHP + MySQL + aplikasi Luwes    │
 │ MySQL hosting (Remote MySQL) │   tiap 2 menit │  cron: php artisan schedule:run           │
 └──────────────────────────────┘   (dua arah)   └───────────────▲───────────────────────────┘
          ▲                                                       │ http://192.168.1.10
          │ admin online                          ┌───────────────┼───────────────┐
                                                  PC Client 1     PC Client 2     PC Client 3
                                                  (browser)       (browser)       (browser)
```

* **PC client** cukup membuka browser ke IP PC server. Tidak perlu instal apa pun.
* **PC server lokal** yang menjalankan sinkron. Hosting tidak bisa menghubungi PC di jaringan lokal,
  tetapi PC server bisa menghubungi MySQL hosting lewat internet.
* Saat internet putus, toko tetap bisa bekerja dengan database lokal. Begitu internet kembali, semua
  data yang tertunda otomatis dikirim/diambil pada sinkron berikutnya.

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
* Internet (untuk sinkron ke hosting).

**PC Client (3 unit)**
* Windows / Linux apa saja dengan browser Google Chrome atau Firefox.
* Satu jaringan dengan PC server (router/switch yang sama).

**Hosting**
* Akses hPanel Hostinger (menu **Databases**), dan akses SSH / Terminal untuk menjalankan `php artisan`.

---

## 3. Instalasi CentOS & Pengaturan Dasar PC Server

Instal CentOS Stream 9 (pilihan *Server* atau *Minimal Install*), lalu login sebagai `root`
(atau user dengan `sudo`).

```bash
# Update sistem
sudo dnf -y update

# Paket dasar
sudo dnf -y install epel-release
sudo dnf -y install git unzip curl wget nano tar policycoreutils-python-utils

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

## 4. Instalasi LAMP (Apache, MySQL, PHP)

### 4.1 Apache

```bash
sudo dnf -y install httpd
sudo systemctl enable --now httpd

# Buka firewall untuk HTTP
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --reload
```

Buka `http://192.168.1.10` dari PC client. Bila muncul halaman tes Apache, Apache sudah jalan.

### 4.2 MySQL 8

```bash
sudo dnf -y install mysql-server
sudo systemctl enable --now mysqld
sudo mysql_secure_installation      # set password root, hapus user anonim, dll.
```

Buat database & user aplikasi:

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE luwes_kasir CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'luwes'@'localhost' IDENTIFIED BY 'GantiPasswordKuat123!';
GRANT ALL PRIVILEGES ON luwes_kasir.* TO 'luwes'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> MySQL **tidak perlu** dibuka ke jaringan. PC client hanya mengakses lewat Apache (port 80).

### 4.3 PHP 8.2 (repo Remi)

```bash
sudo dnf -y install https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf -y module reset php
sudo dnf -y module enable php:remi-8.2
sudo dnf -y install php php-cli php-fpm php-mysqlnd php-pdo php-gd php-zip php-xml \
  php-mbstring php-intl php-bcmath php-opcache php-process php-curl php-fileinfo
php -v                                    # harus PHP 8.2.x
sudo systemctl enable --now php-fpm
sudo systemctl restart httpd
```

### 4.4 Composer & Node.js

```bash
# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version

# Node.js 20 (untuk build asset Vite)
sudo dnf -y module enable nodejs:20
sudo dnf -y install nodejs
node -v
```

---

## 5. Memasang Aplikasi di PC Server

```bash
sudo mkdir -p /var/www/luwes
sudo chown $USER:$USER /var/www/luwes
git clone <URL-REPO-LUWES> /var/www/luwes      # atau salin folder project via flashdisk/scp
cd /var/www/luwes
git checkout main                               # versi yang SAMA dengan yang ada di hosting

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env 2>/dev/null || touch .env  # lalu isi sesuai bagian 7
php artisan key:generate
php artisan storage:link
```

> **Penting:** kode di server lokal dan di hosting harus **versi yang sama** (migrasi database sama).
> Sinkron akan menolak berjalan bila daftar migrasi berbeda.

### 5.1 Hak akses folder & SELinux

```bash
sudo chown -R apache:apache /var/www/luwes/storage /var/www/luwes/bootstrap/cache
sudo chmod -R 775 /var/www/luwes/storage /var/www/luwes/bootstrap/cache

# SELinux: izinkan Apache menulis storage & cache
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/luwes/storage(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/luwes/bootstrap/cache(/.*)?"
sudo restorecon -Rv /var/www/luwes

# SELinux: izinkan PHP (Apache) membuka koneksi ke MySQL hosting
# (dipakai kartu "Status Database Server Hosting" di dashboard)
sudo setsebool -P httpd_can_network_connect_db 1
sudo setsebool -P httpd_can_network_connect 1
```

### 5.2 Virtual host Apache

```bash
sudo nano /etc/httpd/conf.d/luwes.conf
```

```apache
<VirtualHost *:80>
    ServerName 192.168.1.10
    DocumentRoot /var/www/luwes/public

    <Directory /var/www/luwes/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog /var/log/httpd/luwes-error.log
    CustomLog /var/log/httpd/luwes-access.log combined
</VirtualHost>
```

```bash
sudo mv /etc/httpd/conf.d/welcome.conf /etc/httpd/conf.d/welcome.conf.bak 2>/dev/null
sudo apachectl configtest      # harus "Syntax OK"
sudo systemctl restart httpd
```

---

## 6. Menyiapkan Database Hosting untuk Sinkron

### 6.1 Izinkan Remote MySQL di Hostinger

1. Login **hPanel** → **Websites** → pilih `newluwes.decaasoftwares.com` → **Databases** → **Remote MySQL**.
2. Di **Host**, isi IP publik internet toko (cek dari PC server: `curl -s ifconfig.me`).
   Jika IP publik toko **berubah-ubah** (umumnya IndiHome/ISP rumahan), centang **Any Host** (`%`).
3. Pilih database aplikasi (mis. `u1342798_luwes_kasir`), klik **Create**.
4. Catat **hostname/IP server MySQL** yang ditampilkan di halaman **Remote MySQL** / **Management**
   (contoh: `45.130.231.160` atau `srvXXX.hstgr.io`). Inilah nilai `DB_SERVER_HOST`.

> Karena database bisa diakses dari internet, gunakan **password database yang kuat**.

### 6.2 Atur `.env` di hosting

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

## 7. Atur `.env` di PC Server Lokal

```bash
cd /var/www/luwes
nano .env
```

```dotenv
APP_NAME=Luwes
APP_ENV=production
APP_DEBUG=false
APP_URL=http://192.168.1.10
APP_KEY=                             # sudah terisi oleh "php artisan key:generate"

# Database LOKAL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=luwes_kasir
DB_USERNAME=luwes
DB_PASSWORD=GantiPasswordKuat123!

# Server lokal = offset 1 (id ganjil)
DB_AUTO_INCREMENT_INCREMENT=2
DB_AUTO_INCREMENT_OFFSET=1

# Database HOSTING (tujuan sinkron), dari langkah 6.1
DB_SERVER_CONNECTION=mysql
DB_SERVER_HOST=45.130.231.160
DB_SERVER_PORT=3306
DB_SERVER_DATABASE=u1342798_luwes_kasir
DB_SERVER_USERNAME=u1342798_xxxxx
DB_SERVER_PASSWORD=password_database_hosting

# Sinkron otomatis
DB_SYNC_ENABLED=true
DB_SYNC_INTERVAL=2                   # menit
DB_SYNC_CONFLICT=skip                # skip = konflik dilewati + peringatan; newest = otomatis pakai data terbaru

SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
```

Variabel tambahan (opsional):

| Variabel | Default | Fungsi |
|---|---|---|
| `DB_SERVER_TIMEOUT` | `5` | Batas waktu (detik) koneksi ke MySQL hosting |
| `DB_SERVER_SSL_CA` | – | Path sertifikat CA bila hosting mewajibkan SSL |
| `DB_SYNC_EXCLUDE_TABLES` | – | Tabel tambahan yang tidak disinkron, pisahkan koma |
| `DB_SYNC_PREFER` | `local` | Pada mode `newest`, sisi yang menang bila `updated_at` sama persis |

---

## 8. Ambil Data dari Hosting (Sinkron Pertama)

Database lokal masih kosong. **Jangan** menjalankan `migrate --seed` / `db:seed`. Cukup buat strukturnya,
lalu tarik semua data dari hosting:

```bash
cd /var/www/luwes
php artisan migrate --force          # hanya struktur tabel, TANPA seed
php artisan config:cache

# 1) Cek koneksi ke database hosting
php artisan dbsync:status
#   Koneksi : OK (35 ms, 10.11.x-MariaDB)

# 2) Lihat rencana tanpa mengubah apa pun
php artisan dbsync:run --dry-run
#   products   tarik 1250 | kirim 0 | ...

# 3) Jalankan sinkron pertama (menyalin semua data hosting ke lokal)
php artisan dbsync:run -v
#   Sinkron success: 48210 baris berubah, 0 konflik dilewati, 0 error, 95000 ms.

# 4) Jalankan sekali lagi: hasilnya harus 0 baris berubah
php artisan dbsync:run
```

Sinkron pertama bisa memakan beberapa menit, tergantung jumlah data dan kecepatan internet.

---

## 9. Jalankan Sinkron Otomatis (Cron)

```bash
sudo crontab -u apache -e
```

Tambahkan baris:

```cron
* * * * * cd /var/www/luwes && php artisan schedule:run >> /dev/null 2>&1
```

Cek jadwal:

```bash
sudo -u apache php /var/www/luwes/artisan schedule:list
#   */2 * * * *  php artisan dbsync:run ..... Next Due: ...
```

Sekarang sinkron berjalan otomatis setiap `DB_SYNC_INTERVAL` menit.

---

## 10. Menghubungkan 3 PC Client

1. Sambungkan PC client ke router/switch yang **sama** dengan PC server.
2. Cek IP server dari PC server: `hostname -I` → contoh `192.168.1.10`.
3. Di PC client, buka **Command Prompt** dan tes koneksi:
   ```
   ping 192.168.1.10
   ```
   Harus ada balasan (`Reply from 192.168.1.10 ...`). Jika *Request timed out*, cek kabel/WiFi dan
   firewall server (`sudo firewall-cmd --list-services` harus memuat `http`).
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

## 11. Kartu "Status Database Server Hosting" di Dashboard

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

Cek dari terminal:

```bash
php artisan dbsync:status
```

### 11.1 Tombol aksi (khusus owner & superadmin, di server lokal)

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

Isi modul bisa diubah di `config/dbsync.php` bagian `groups`.

---

## 12. Menangani Konflik

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

**Dari terminal:** pilih versi yang benar, lalu jalankan di PC server:

```bash
php artisan dbsync:resolve products 4 --keep=server   # pakai versi hosting
php artisan dbsync:resolve products 4 --keep=local    # pakai versi lokal
php artisan dbsync:resolve products 4 --keep=newest   # pakai yang terakhir diubah
```

Bila ingin konflik **otomatis** diselesaikan dengan data terbaru (tanpa dilewati), ubah `.env` server lokal:
`DB_SYNC_CONFLICT=newest`, lalu `php artisan config:cache`.

---

## 13. Pemeliharaan & Hal yang WAJIB Diperhatikan

* **Update aplikasi:** update kode & jalankan `php artisan migrate --force` di **hosting dan server lokal**.
  Selama migrasi belum sama, sinkron berhenti dengan pesan
  *"Struktur database berbeda. Jalankan php artisan migrate di ..."*.
* **Jangan pernah** menjalankan `migrate:fresh`, `db:wipe`, atau mengosongkan tabel di salah satu sisi.
  Penghapusan massal akan ditahan pengaman, tetapi lebih baik jangan sampai terjadi. Jika memang disengaja:
  `php artisan dbsync:run --table=nama_tabel --force`.
* **Jangan** mengisi `DB_SERVER_*` + `DB_SYNC_ENABLED=true` di lebih dari **satu** PC server.
* **Backup harian** database lokal:
  ```bash
  sudo mkdir -p /backup && sudo crontab -e
  # tambahkan:
  0 22 * * * mysqldump -u luwes -p'GantiPasswordKuat123!' luwes_kasir | gzip > /backup/luwes_$(date +\%F).sql.gz
  0 23 * * * find /backup -name 'luwes_*.sql.gz' -mtime +14 -delete
  ```
* Setelah mengubah `.env`: `php artisan config:cache`.

---

## 14. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| `Access denied for user ...` | Username/password `DB_SERVER_*` salah, atau IP toko belum ditambahkan di **Remote MySQL** (pakai *Any Host*). |
| `Connection timed out` / `Can't connect to MySQL server` | Internet putus, `DB_SERVER_HOST` salah, atau ISP memblokir port 3306. Tes: `curl -v telnet://DB_SERVER_HOST:3306`. |
| Kartu dashboard Error, tetapi `php artisan dbsync:status` OK | SELinux memblokir Apache: `sudo setsebool -P httpd_can_network_connect_db 1`. |
| `DB_AUTO_INCREMENT_INCREMENT=2 ... belum diset` | Tambahkan `DB_AUTO_INCREMENT_*` di `.env` server lokal (bagian 7), lalu `php artisan config:cache`. |
| `Struktur database berbeda` | Versi aplikasi berbeda. Samakan kode, lalu `php artisan migrate --force` di dua sisi. |
| `kemungkinan id bentrok` | Hosting belum memakai `DB_AUTO_INCREMENT_OFFSET=2`. Perbaiki `.env` hosting, lalu selesaikan baris itu dengan `dbsync:resolve`. |
| `Penghapusan N baris ... ditahan` | Ada penghapusan massal. Periksa apakah disengaja; jika ya, jalankan dengan `--force`. |
| Peringatan "belum ada sinkron berhasil dalam 10 menit" | Cron tidak jalan: cek `sudo crontab -u apache -l` dan `sudo systemctl status crond`. |
| PC client tidak bisa membuka `http://192.168.1.10` | Cek `ping`, firewall (`sudo firewall-cmd --add-service=http --permanent && sudo firewall-cmd --reload`), dan `sudo systemctl status httpd`. |
| Halaman error 500 | Lihat `storage/logs/laravel.log` dan `/var/log/httpd/luwes-error.log`; cek hak akses folder `storage` (bagian 5.1). |

---

## 15. Ringkasan Perintah

```bash
php artisan dbsync:status                               # cek koneksi & sinkron terakhir
php artisan dbsync:run --dry-run                        # lihat rencana tanpa mengubah data
php artisan dbsync:run -v                               # sinkron sekarang (detail per tabel)
php artisan dbsync:run --group=penjualan                # sinkron satu modul (+ tabel terkait)
php artisan dbsync:run --table=products                 # sinkron satu tabel (+ tabel induknya)
php artisan dbsync:run --table=stock_movements --force  # izinkan penghapusan massal yang ditahan
php artisan dbsync:resolve products 4 --keep=server     # selesaikan konflik
```
