# Panduan Instalasi Offline (PC Server Lokal Debian + Docker) + Sinkronisasi ke Hosting

Panduan ini menjelaskan cara memasang aplikasi Luwes di **1 PC server lokal (Debian) memakai Docker**, yang
dipakai oleh **3 PC client** di jaringan toko/gudang, lalu menyinkronkan databasenya **otomatis dua arah**
dengan aplikasi online di hosting: <https://luwespacitan.com/>.

**Urutan pengerjaan:**

| Langkah | Dikerjakan di | Isi |
|---|---|---|
| 3 | PC server | Pengaturan dasar Debian (sudo, jam, IP statis) |
| 4 | Panel & SSH hosting | Izinkan Remote MySQL, atur `.env` hosting, migrasi |
| 5 | PC server | Instal Docker, `git clone`, `make up` (menanyakan kredensial hosting), sinkron pertama |
| 6 | 3 PC client | Buka `http://IP-PC-SERVER` di browser |

### Yang dipasang di PC server hanya Docker

PHP, Apache, MySQL, Composer, dan Node.js **tidak** dipasang di Debian; semuanya sudah ada di dalam container Docker.

| Dipasang di Debian (host) | Ada di dalam Docker (otomatis) |
|---|---|
| Docker Engine + Docker Compose | PHP 8.2 + ekstensi (pdo_mysql, gd, zip, intl, bcmath, opcache) |
| `git` (ambil kode dari GitHub) | Apache (web server) |
| `make` (perintah singkat `make up`, dll.) | MySQL 8 (database lokal) |
| | Composer & Node.js (hanya saat build image) |
| | Scheduler Laravel (sinkron otomatis), phpMyAdmin (opsional) |

Ringkasnya, setelah Docker, git, dan make terpasang:

```bash
git clone https://github.com/ZensIDS/new_luwes.git luwes
cd luwes
make up          # buat .env + tanya kredensial DB hosting + build + jalankan semua service
```

---

## 1. Gambaran Sistem

```
            INTERNET                                   JARINGAN LOKAL (LAN / WiFi toko)
 ┌──────────────────────────────┐                ┌───────────────────────────────────────────┐
 │ Hosting (Hostinger)          │                │  PC SERVER LOKAL (Debian + Docker)        │
 │ luwespacitan.com             │◄── sinkron ────│   ├─ app       : web Laravel (Apache+PHP) │
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
* **Debian 12 (bookworm)** atau **Debian 13 (trixie)**, 64-bit, instalasi baru. Instalasi tanpa desktop
  (*SSH server* + *standard system utilities*) sudah cukup. **Tidak perlu** memasang PHP, MySQL, atau Apache.
* RAM minimal 4 GB, SSD minimal 120 GB.
* Bila server berupa VM / **Proxmox LXC**: disk minimal **20 GB** (Docker butuh ±6 GB kosong saat build) dan untuk LXC aktifkan fitur *nesting* + *keyctl* (lihat Troubleshooting).
* Terhubung ke router/switch toko dengan **kabel LAN** (lebih stabil daripada WiFi).
* Internet (untuk sinkron ke hosting & download image Docker saat instalasi).
* Akses ke repository GitHub `ZensIDS/new_luwes` (akun GitHub yang diundang ke repo, atau *Personal Access Token*).

**PC Client (3 unit)**
* Windows / Linux apa saja dengan browser Google Chrome atau Firefox.
* Satu jaringan dengan PC server (router/switch yang sama).

**Hosting**
* Akses panel Niagahoster/Hostinger (hPanel) dengan username panel (mis. `u1342798`).
* Akses SSH / Terminal hosting untuk menjalankan `php artisan`.

---

## 3. Pengaturan Dasar Debian (PC Server)

### 3.1 Pasang `sudo`

Pada instalasi Debian yang mengisi password **root**, user biasa belum bisa memakai `sudo`. Login sebagai user
biasa (contoh `luwes`), lalu:

```bash
su -                          # masuk sebagai root (password root)
apt update && apt install -y sudo
usermod -aG sudo luwes        # ganti "luwes" dengan nama user Anda
exit
```

Logout lalu login lagi, cek dengan `sudo whoami` (hasilnya `root`).

### 3.2 Paket dasar & jam

```bash
sudo apt update && sudo apt -y full-upgrade
sudo apt install -y git make curl ca-certificates nano   # hanya ini; PHP/MySQL/Apache ada di Docker

# Zona waktu & sinkron jam (PENTING: jam yang salah membuat aturan "data terbaru" keliru)
sudo timedatectl set-timezone Asia/Jakarta
sudo timedatectl set-ntp true
timedatectl            # pastikan "System clock synchronized: yes"
```

> Jika `set-ntp` gagal (*NTP not supported*), pasang dulu: `sudo apt install -y systemd-timesyncd`.

### 3.3 Beri IP statis untuk PC server

PC client akan selalu membuka IP ini, jadi IP-nya **tidak boleh berubah**. Cara paling mudah: atur
**DHCP reservation** di router untuk MAC address PC server (lihat MAC dengan `ip link`). Bila tidak bisa,
atur di Debian:

```bash
ip -4 addr          # lihat nama kartu jaringan (mis. enp3s0) & IP sekarang
ip route            # lihat gateway/router (mis. "default via 192.168.1.1")
```

Contoh: kartu `enp3s0`, router `192.168.1.1`, IP server yang dipilih `192.168.1.10`
(pilih IP di luar rentang DHCP router agar tidak dipakai perangkat lain).

**Debian tanpa desktop** (memakai `/etc/network/interfaces`):

```bash
sudo nano /etc/network/interfaces
```

Ganti blok `enp3s0` yang berisi `dhcp` menjadi:

```
auto enp3s0
iface enp3s0 inet static
    address 192.168.1.10/24
    gateway 192.168.1.1
    dns-nameservers 8.8.8.8 1.1.1.1
```

```bash
sudo apt install -y resolvconf      # agar baris dns-nameservers dipakai
sudo systemctl restart networking   # atau reboot
```

**Debian dengan desktop** (NetworkManager):

```bash
nmcli connection show               # lihat nama koneksi, mis. "Wired connection 1"
sudo nmcli connection modify "Wired connection 1" ipv4.method manual \
  ipv4.addresses 192.168.1.10/24 ipv4.gateway 192.168.1.1 ipv4.dns "8.8.8.8 1.1.1.1"
sudo nmcli connection up "Wired connection 1"
```

Cek:

```bash
hostname -I            # harus muncul 192.168.1.10
ping -c 3 google.com   # internet harus jalan
```

---

## 4. Siapkan Database Hosting (dikerjakan dulu, sebelum PC server)

PC server membutuhkan izin masuk ke MySQL hosting. Siapkan ini lebih dulu agar saat aplikasi dijalankan di
langkah 5, sinkron langsung berhasil.

### 4.1 Izinkan Remote MySQL

1. Login panel **Niagahoster/Hostinger (hPanel)** memakai username panel (mis. `u1342798`).
2. Buka **Websites** → `luwespacitan.com` → **Databases** → **Remote MySQL**.
3. Cek IP publik internet toko dari PC server:
   ```bash
   curl -s ifconfig.me
   ```
4. Di **Host**, isi IP tersebut. Jika IP publik toko **berubah-ubah** (umumnya IndiHome / ISP rumahan),
   centang **Any Host** (`%`).
5. Pilih database aplikasi (mis. `u1342798_luwes_kasir`), klik **Create**.
6. Catat data untuk `.env` PC server (langkah 5.3):

| `.env` PC server | Ambil dari | Contoh |
|---|---|---|
| `DB_SERVER_HOST` | Halaman **Remote MySQL** / **Management** (hostname/IP MySQL) | `45.130.231.160` |
| `DB_SERVER_DATABASE` | `DB_DATABASE` di `.env` hosting | `u1342798_luwes_kasir` |
| `DB_SERVER_USERNAME` | `DB_USERNAME` di `.env` hosting (**bukan** username panel) | `u1342798_ricky` |
| `DB_SERVER_PASSWORD` | `DB_PASSWORD` di `.env` hosting | – |

> Bila password database tidak diketahui, reset di **Databases → Management**, lalu perbarui juga
> `DB_PASSWORD` di `.env` hosting dengan password baru tersebut.
> Karena database bisa diakses dari internet, gunakan **password yang kuat**.

### 4.2 Atur `.env` hosting & jalankan migrasi

Tambahkan baris berikut ke `.env` aplikasi **di hosting**:

```dotenv
# Hosting = offset 2 (id genap). Server lokal = offset 1 (id ganjil).
DB_AUTO_INCREMENT_INCREMENT=2
DB_AUTO_INCREMENT_OFFSET=2

# Hosting TIDAK menjalankan sinkron
DB_SYNC_ENABLED=false
DB_SERVER_HOST=
```

Lalu, lewat SSH / Terminal hosting (pastikan kode hosting sudah versi terbaru):

```bash
cd ~/domains/luwespacitan.com/public_html   # sesuaikan dengan folder aplikasi
php artisan migrate --force          # membuat tabel db_sync_runs & db_sync_rows
php artisan optimize:clear
```

> Mengapa offset? Dua database membuat data baru secara bersamaan. Dengan offset, data baru di lokal
> selalu ber-id ganjil dan di hosting ber-id genap, jadi **tidak pernah bentrok id**.

---

## 5. Instal Docker & Jalankan Aplikasi (PC Server)

### 5.1 Instal Docker Engine + Docker Compose

Pakai repository resmi Docker (paket `docker.io` bawaan Debian tidak membawa `docker compose` v2):

```bash
# Hapus paket Docker lama/bawaan Debian bila ada (aman bila belum terpasang)
for pkg in docker.io docker-doc docker-compose podman-docker containerd runc; do sudo apt remove -y $pkg; done

# Tambahkan repository Docker
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
https://download.docker.com/linux/debian $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Instal
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker

# Agar bisa menjalankan docker tanpa sudo (logout lalu login lagi setelah perintah ini)
sudo usermod -aG docker $USER
```

Setelah login ulang, cek:

```bash
docker version
docker compose version             # harus v2.x
docker run --rm hello-world        # harus muncul "Hello from Docker!"
```

### 5.2 Ambil kode aplikasi

```bash
cd ~
git clone https://github.com/ZensIDS/new_luwes.git luwes
cd luwes
git checkout main          # versi yang SAMA dengan yang ada di hosting
```

> Repo private: GitHub akan meminta username + **Personal Access Token** (bukan password akun).
> Buat token di GitHub → *Settings → Developer settings → Personal access tokens*.

### 5.3 Jalankan aplikasi

```bash
make up
```

Saat pertama kali, `make up` akan:

1. Membuat `.env` dari `.env.docker.example` dan **otomatis** mengisi `APP_KEY`, password database lokal
   (`DB_PASSWORD`, `DB_ROOT_PASSWORD`), dan `APP_URL` (IP PC server).
2. **Menanyakan kredensial database hosting** (data dari langkah 4.1):
   ```
   Kredensial database HOSTING (panduan langkah 4.1). Tekan Enter tanpa isi untuk melewati.
     DB_SERVER_HOST     (IP/hostname MySQL hosting): 45.130.231.160
     DB_SERVER_PORT     [3306]:
     DB_SERVER_DATABASE (mis. u1342798_luwes_kasir): u1342798_luwes_kasir
     DB_SERVER_USERNAME (user database, BUKAN username panel): u1342798_ricky
     DB_SERVER_PASSWORD (tidak tampil saat diketik): ********
   ```
   Password dengan karakter khusus (`$`, `#`, spasi) otomatis disimpan dengan aman.
   Bila dilewati (Enter), isi nanti dengan `make hosting`.
3. Build image aplikasi (PHP 8.2 + Apache + asset). Pertama kali ±5–10 menit, tergantung internet.
4. Menjalankan `mysql`, lalu `app` (otomatis `php artisan migrate`), lalu `scheduler`.
5. Menunggu aplikasi siap, lalu menampilkan status container dan **hasil cek koneksi ke database hosting**:
   ```
   luwes-app-1         Up ... (healthy)   0.0.0.0:80->80/tcp
   luwes-mysql-1       Up ... (healthy)
   luwes-scheduler-1   Up ...

   Kesehatan       : WARNING — Belum pernah sinkron. Sinkron pertama berjalan otomatis dalam 2 menit ...
   Koneksi         : OK (35 ms, 10.11.x-MariaDB)

   Aplikasi: http://192.168.1.10   (dibuka dari PC client)
   ```

Jika `Koneksi : GAGAL — Access denied ...`, periksa langkah 4.1, lalu isi ulang kredensial:

```bash
make hosting       # tanya ulang DB_SERVER_* lalu restart otomatis
make status        # cek lagi
```

### 5.4 Isi `.env` (opsional, bila perlu mengubah manual)

Semua nilai sudah terisi oleh `make up`. Bila perlu mengubah, `nano .env` lalu **wajib** `make restart`.

| Variabel | Nilai | Keterangan |
|---|---|---|
| `DB_SERVER_*` | dari langkah 4.1 | Kredensial database hosting (lebih mudah lewat `make hosting`) |
| `APP_URL` | `http://IP-PC-SERVER` | Diisi otomatis; sesuaikan bila IP server berubah |
| `APP_PORT` | `80` | Port web di PC server |
| `DB_HOST` | `mysql` | Nama container MySQL lokal, **jangan** diganti `127.0.0.1` |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD` | acak | Password MySQL lokal (dibuat otomatis) |
| `DB_AUTO_INCREMENT_INCREMENT` / `_OFFSET` | `2` / `1` | Server lokal = id ganjil (hosting = offset 2) |
| `DB_SYNC_ENABLED` | `true` | Sinkron otomatis aktif |
| `DB_SYNC_INTERVAL` | `2` | Menit antar sinkron |
| `DB_SYNC_CONFLICT` | `newest` | `newest` = data yang terakhir diubah menang (lokal menang bila waktunya sama); `skip` = konflik dilewati + peringatan |
| `DB_SERVER_TIMEOUT` | `5` | (opsional) batas waktu koneksi ke hosting, detik |
| `DB_SYNC_EXCLUDE_TABLES` | – | (opsional) tabel tambahan yang tidak disinkron, pisahkan koma |

> Mengisi password manual di `.env`: bila mengandung `$`, `#`, atau spasi, **wajib** diapit kutip tunggal,
> mis. `DB_SERVER_PASSWORD='ab$cd#12'`.
>
> **Penting:** `DB_PASSWORD` / `DB_ROOT_PASSWORD` dipakai saat volume MySQL **pertama kali** dibuat.
> Jangan diubah setelah `make up` pertama, atau MySQL lokal tidak bisa diakses.
>
> **Jangan** menjalankan `docker compose up` sebelum file `.env` ada (Docker akan membuat folder `.env` kosong).
> Selalu gunakan `make up`.

### 5.5 Cek koneksi & sinkron pertama

Database lokal masih kosong (hanya struktur tabel). Sinkron pertama menyalin semua data dari hosting.

```bash
# 1) Cek koneksi ke database hosting (sudah ditampilkan juga di akhir "make up")
make status
#   Koneksi : OK (35 ms, 10.11.x-MariaDB)
#   (Bila "Access denied ... @'IP'" -> periksa langkah 4.1, lalu "make hosting")

# 2) Lihat rencana tanpa mengubah apa pun
make sync-dry

# 3) Jalankan sinkron pertama sambil melihat detail per tabel
make sync
#   Sinkron success: 48210 baris berubah, 0 konflik dilewati, 0 error, 95000 ms.

# 4) Selanjutnya sinkron berjalan otomatis tiap 2 menit oleh container scheduler
make logs-sync
```

> Bila `make sync` (no. 3) dilewati, container `scheduler` tetap akan melakukan sinkron pertama sendiri dalam ±2 menit.
> Setelah mengubah `.env` kapan pun, jalankan `make restart`.

### 5.6 Firewall (bila ada)

Debian **tidak** memasang firewall secara bawaan, dan Docker membuka port 80 sendiri. Bila server memakai
`ufw` dan PC client tetap tidak bisa membuka aplikasi:

```bash
sudo ufw allow 80/tcp
sudo ufw status
```

---

## 6. Menghubungkan 3 PC Client

1. Sambungkan PC client ke router/switch yang **sama** dengan PC server.
2. Cek IP server dari PC server: `make ip` (atau `hostname -I`) → contoh `192.168.1.10`.
   Bila `APP_PORT` di `.env` bukan 80, tambahkan port di alamat, mis. `http://192.168.1.10:8000`.
3. Di PC client, buka **Command Prompt** dan tes koneksi:
   ```
   ping 192.168.1.10
   ```
   Harus ada balasan (`Reply from 192.168.1.10 ...`). Jika *Request timed out*, cek kabel/WiFi dan
   firewall server (lihat bagian 5.6) dan `make ps` (container `app` harus *healthy*).
4. Buka browser (Chrome) di PC client, ketik: **`http://192.168.1.10`**.
5. Login memakai akun yang sama seperti di hosting (data user ikut tersinkron dari hosting).
6. Pastikan data (produk, stok, penjualan) sama dengan yang ada di
   <https://luwespacitan.com/>. Itu tandanya data sudah berhasil diambil dari DB server hosting.
7. Buat shortcut di desktop: klik kanan desktop → **New → Shortcut** → isi `http://192.168.1.10` → beri nama
   **Luwes Kasir**. Ulangi di ketiga PC client.

**Opsional: memakai nama alih-alih IP.** Di setiap PC client Windows, buka Notepad *as Administrator*,
edit `C:\Windows\System32\drivers\etc\hosts`, lalu tambahkan:

```
192.168.1.10   luwes.local
```

Setelah itu aplikasi bisa dibuka dengan `http://luwes.local`.

---

## 7. Kartu "Status Database Server Hosting" di Dashboard

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

### 7.1 Tombol aksi (khusus owner & superadmin, di server lokal)

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

## 8. Menangani Konflik

Konflik terjadi bila **kolom yang sama** pada **baris yang sama** diubah ke nilai berbeda di lokal dan di
hosting di antara dua sinkron. Contoh: nama produk #4 diganti "A" di kasir offline dan "B" di hosting.

**Default (`DB_SYNC_CONFLICT=newest`): diselesaikan otomatis berdasarkan waktu.** Server lokal adalah sumber
utama dan hosting sebagai backup online, tetapi perubahan dari hosting tetap ikut masuk ke lokal:

* Kolom yang hanya diubah di **satu sisi** diambil dari sisi itu, jadi perubahan lokal & hosting digabung.
* Kolom yang diubah di **dua sisi** memakai nilai dari baris yang **terakhir diubah** (`updated_at`, atau
  `created_at` bila tabel tidak punya `updated_at`). Bila waktunya sama persis, **lokal** yang menang
  (`DB_SYNC_PREFER=local`).
* Stok/saldo (`additive_columns`) dijumlahkan, nomor invoice (`max_columns`) diambil yang terbesar.
* Baris dengan id sama di dua sisi tetapi isinya berbeda (id bentrok, biasanya terjadi sebelum
  `DB_AUTO_INCREMENT_OFFSET` diset): untuk tabel log (`activity_log`, `stock_movements`) **kedua baris
  disimpan** (baris hosting dipindah ke id baru). Untuk tabel lain dipakai versi terbaru dan muncul peringatan
  di hasil sinkron.

> Waktu "terbaru" diambil dari jam masing-masing server, jadi pastikan jam PC server lokal benar
> (`timedatectl` → *System clock synchronized: yes*).

**Mode manual (`DB_SYNC_CONFLICT=skip`):** baris konflik **tidak disinkron** (tidak ada data yang tertimpa),
dan peringatan muncul di dashboard:

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

Bila hanya `created_at` / `updated_at` yang berbeda, baris **tidak** dianggap konflik: kedua sisi otomatis
disamakan ke versi terbaru.

**Banyak konflik sekaligus:** tombol **Selesaikan Semua Konflik** (di sebelah *Sinkron Sekarang*) menyamakan
semua baris konflik dari sinkron terakhir dengan satu pilihan: **Pakai semua dari Hosting**, **Pakai semua dari
Lokal**, atau **Pakai yang terbaru (per baris)**. Bila ada modul yang dipilih, hanya konflik di tabel modul itu
yang diproses. Pilihan ini menimpa data sisi lain dan tidak bisa dibatalkan, jadi lihat dulu beberapa
perbedaannya.

**Dari terminal PC server:** pilih versi yang benar, lalu jalankan:

```bash
make artisan cmd="dbsync:resolve products 4 --keep=server"   # pakai versi hosting
make artisan cmd="dbsync:resolve products 4 --keep=local"    # pakai versi lokal
make artisan cmd="dbsync:resolve products 4 --keep=newest"   # pakai yang terakhir diubah
```

Dashboard **hosting** hanya bisa *melihat* perbedaan (database lokal tidak terjangkau dari hosting). Pada mode
`newest` server lokal menyamakan baris itu otomatis pada sinkron berikutnya; pada mode `skip` selesaikan dari
dashboard / terminal server lokal.

---

## 9. Pemeliharaan & Hal yang WAJIB Diperhatikan

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

## 10. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| `Access denied for user '...'@'IP'` | IP toko belum ditambahkan di **Remote MySQL** hosting (pakai *Any Host*), atau `DB_SERVER_USERNAME` / `DB_SERVER_PASSWORD` salah (isi ulang dengan `make hosting`). Username database (`u1342798_xxx`) berbeda dengan username login panel (`u1342798`). |
| `Connection timed out` / `Can't connect to MySQL server` | Internet putus, `DB_SERVER_HOST` salah, atau ISP memblokir port 3306. Tes: `curl -v telnet://DB_SERVER_HOST:3306`. |
| `isi DB_ROOT_PASSWORD di .env` saat `docker compose up` | `.env` belum dibuat. Jalankan `make init` (atau `make up`). |
| `sudo: command not found` / user tidak ada di sudoers | Jalankan langkah 3.1 (pasang `sudo` lewat `su -`). |
| Error `.env berupa folder` | `docker compose up` dijalankan sebelum `.env` ada. `rm -rf .env && make up`. |
| `Struktur database berbeda` | Versi aplikasi berbeda. Samakan kode (`make update`) dan jalankan `php artisan migrate --force` di hosting. |
| `kemungkinan id bentrok` | Hosting belum memakai `DB_AUTO_INCREMENT_OFFSET=2`. Perbaiki `.env` hosting, lalu selesaikan baris itu (bagian 8). |
| `Penghapusan N baris ... ditahan` | Ada penghapusan massal. Periksa apakah disengaja; jika ya, jalankan dengan `--force`. |
| Peringatan "belum ada sinkron berhasil dalam 10 menit" | Container scheduler mati: `make ps`, `make logs-sync`, lalu `make restart`. |
| Container `app` tidak *healthy* / web error 500 | `make logs`, dan log Laravel: `make shell` lalu `tail -100 storage/logs/laravel-*.log`. |
| PC client tidak bisa membuka `http://IP-SERVER` | Cek `ping`, `make ps`, firewall (bagian 5.6), dan apakah port 80 dipakai program lain (`sudo ss -ltnp`, cari `:80`); bila ya, ganti `APP_PORT` di `.env` lalu `make restart`. |
| `permission denied ... docker.sock` | User belum masuk grup docker: `sudo usermod -aG docker $USER`, lalu logout & login. |
| `make: command not found` | `sudo apt install -y make` |
| Browser menampilkan kode PHP (`<?php use Illuminate\Contracts\Http\Kernel; ...`) | `.htaccess` di root project (khusus hosting, handler LiteSpeed `x-lsphp81`) terbaca Apache Docker. Sudah ditangani di `docker/apache/vhost.conf` & `.dockerignore`; jalankan `make update` (atau `git pull && make up`). Jangan hapus `.htaccess` itu, hosting masih memakainya. |
| `No space left on device` saat `make up` (build gagal di `apt-get install`) | Disk penuh. Docker butuh **minimal ±6 GB kosong** (image aplikasi + MySQL + image dasar saat build). Cek: `df -h /`. Bersihkan sisa build gagal: `docker system prune -af`, lalu perbesar disk. Di **Proxmox LXC** (jalankan di host Proxmox): `pct resize <CTID> rootfs +20G`, atau *Container → Resources → Root Disk → Volume Action → Resize*. Setelah itu `make up` lagi. |
| Docker di **Proxmox LXC**: `permission denied`, `operation not permitted`, `failed to create shim task` | Aktifkan *nesting* (dan *keyctl* untuk container *unprivileged*) di host Proxmox: `pct set <CTID> --features nesting=1,keyctl=1` lalu `pct reboot <CTID>`. Tes di dalam LXC: `docker run --rm hello-world`. |
| Salah mengisi kredensial hosting saat `make up` | Aman, aplikasi lokal tetap jalan, hanya sinkron yang gagal (status merah di dashboard). Perbaiki dengan `make hosting`, atau edit `.env` lalu `make restart`. `DB_SERVER_HOST` diisi host MySQL hosting (tanpa `http://`), **bukan** IP PC server lokal. |
| Perubahan `.env` tidak berpengaruh | Jalankan `make restart` (konfigurasi di-cache saat container start). |

---

## 11. Ringkasan Perintah (`make help`)

```bash
make up                 # build & jalankan semua service (pertama kali: buat .env + tanya kredensial hosting)
make hosting            # isi / ganti kredensial database hosting (DB_SERVER_*) lalu restart
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
