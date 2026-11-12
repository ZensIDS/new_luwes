# Panduan Tahap 7 — Verifikasi manual pagar harga beli / HPP

Tujuan: membuktikan bahwa harga beli dan HPP HANYA berubah lewat Edit Product, Outlet Purchases, dan Master Harga Jual, dan fitur lain tetap jalan setelah mode `enforce`.

Sebaiknya dikerjakan di database salinan (staging) atau di jam sepi. Bila harus di production, backup database dulu.

## 0. Persiapan

1. Terapkan zip Tahap 4, 5, 6, 8 (dan file baru ini) ke project, lalu `php artisan config:clear`.
2. Pastikan `.env` tidak berisi `HARGA_BELI_GUARD_MODE=log`. Cek: `php artisan tinker` lalu `config('harga_beli.mode')` harus `enforce`.
3. Jalankan tes: `php artisan test --filter=HargaBeliGuardTest` (butuh `tests/TestCase.php` bawaan Laravel).
4. Pilih 1-2 produk uji dengan `satuan_besar` dan `konversi_qty` terisi, dan 1 outlet uji yang produknya sudah ada di Master Harga Jual. Catat kodenya, mis. `ABC-001`.
5. Ambil foto awal (hanya membaca): `php artisan harga-beli:snapshot --product=ABC-001`. Catat path CSV-nya.

Cara memakai foto: setelah tiap uji, jalankan `php artisan harga-beli:snapshot --compare=<path CSV> --product=ABC-001`. Command menampilkan kolom yang berubah (harga_beli, harga_beli_besar, hpp). Untuk uji yang harus "tidak berubah", hasil yang benar adalah "Tidak ada perubahan". Ambil foto baru setelah uji yang memang mengubah harga, supaya uji berikutnya dibandingkan dari kondisi terbaru.

Log pelanggaran: `storage/logs/laravel.log`, cari `[HargaBeliGuard]`. Baris `DITOLAK` berarti ada jalur yang belum sah; salin barisnya (target, caller, url) dan kirim ke pengembang.

## 1. Jalur resmi harus berhasil dan harga berubah

| # | Uji | Langkah | Hasil yang benar |
|---|-----|---------|------------------|
| 1 | Edit Product | Ubah harga beli produk uji, simpan | Berhasil tanpa error. Snapshot: `harga_beli` dan `harga_beli_besar` berubah dan konsisten (besar = kecil x konversi) |
| 2 | Outlet Purchases | Buat pembelian outlet untuk produk uji dengan harga baru, lalu terima | Berhasil. Snapshot: batch baru/terkait `owner_stocks.hpp` berubah ke harga pembelian |
| 3 | Master Harga Jual | Ubah HPP produk uji di outlet uji, simpan | Berhasil. Snapshot: `hpp` batch terbaru (atau `products.harga_beli` bila outlet tanpa batch) berubah. Riwayat HPP di halaman master harga mencatatnya |

## 2. Jalur lain TIDAK boleh mengubah harga beli

Ambil foto baru sebelum tiap uji, lakukan, lalu bandingkan. Semua hasil harus "Tidak ada perubahan" pada `harga_beli`, `harga_beli_besar`, dan `hpp` batch yang sudah ada (batch baru dari retur/Set Stock boleh muncul sebagai BARU dengan HPP turunan).

| # | Uji | Langkah | Catatan |
|---|-----|---------|---------|
| 4 | Terima DO ke outlet | Kirim DO berisi produk uji yang harga PO gudangnya BERBEDA dari HPP outlet, lalu terima di outlet | HPP terbaru outlet tidak berubah (K2-a). Batch baru dari DO memakai HPP berlaku, bukan harga PO |
| 5 | Edit penerimaan PO gudang | Ubah harga/qty penerimaan PO produk uji | `products.harga_beli` dan `owner_stocks.hpp` tidak berubah |
| 6 | Batal terima PO | Batalkan penerimaan PO produk uji | `harga_beli_besar` tidak ikut tertulis ulang |
| 7 | Set Stock toko | Set Stock produk uji (dua kali dalam 72 jam bila ingin menguji mode tambah) | Tidak ada perubahan HPP batch lama; batch Set Stock baru memakai HPP berlaku |
| 8 | Retur penjualan | Buat retur untuk produk uji | HPP batch asal tidak berubah |
| 9 | Opname gudang dan toko | Lakukan opname produk uji | Tidak ada perubahan harga |
| 10 | Penjualan sampai stok minus | Jual lebih dari stok sistem (kasir) | Batch MINUS terbentuk dengan HPP terbaru; HPP batch lain tidak berubah |
| 11 | Simpan produk bukan lewat 3 jalur | Mis. ubah `min_stock` lewat import min stock, atau buka dan simpan halaman yang menyimpan produk | `harga_beli_besar` tidak berubah |

## 3. Import produk (Tahap 8)

1. Siapkan file import dengan: satu produk yang sudah ada tetapi `harga_beli`-nya DIUBAH di file, dan satu produk baru dengan `harga_beli` terisi.
2. Jalankan import.
3. Hasil yang benar: produk lama tetap berharga lama (snapshot tidak berubah); produk baru terisi harga awal. Di log ada baris `maintenance products-import` berisi kode produk lama dan pasangan `[harga di DB, harga di file]`.

## 4. Command pemulihan HPP 0 (Tahap 5)

1. Dry-run: `php artisan hpp:restore-zero --outlet=<id> --product=ABC-001`. Periksa tabel dan CSV di `storage/app/hpp-restore-zero-*.csv`: sumber dan tanggal HPP pengganti harus masuk akal.
2. Untuk uji pada data contoh, buat HPP 0 dengan cara sah lebih dulu (mis. di database uji, bukan production), foto, lalu `--apply`.
3. Hasil yang benar: HPP kembali ke nilai > 0 terakhir; snapshot menunjukkan perubahan hanya pada batch/produk yang ditargetkan; produk tanpa riwayat > 0 dilewati dan dilaporkan.
4. Jalankan dry-run lagi: seharusnya tidak ada lagi kandidat untuk pasangan yang sudah dipulihkan.

## 5. Command maintenance lama

Cukup uji satu: `php artisan product:backfill-harga-beli-besar` (dry-run, tanpa `--apply`) harus berjalan tanpa error dan tidak mengubah apa pun (snapshot tidak berubah). Dengan `--apply` pada data uji, ada baris `[HargaBeliGuard] maintenance ...` di log.

## 6. Bila ada yang gagal

- Fitur error dengan pesan "Harga beli / HPP hanya boleh diubah lewat ...": ini guard yang menolak. Kirim baris `DITOLAK` dari log (target, caller, url) dan nama fitur/langkahnya. Darurat: set `HARGA_BELI_GUARD_MODE=log` di `.env`, `php artisan config:clear` (guard kembali hanya mencatat, fitur jalan seperti sebelum Tahap 6).
- Harga berubah padahal seharusnya tidak: kirim output `harga-beli:snapshot --compare` dan langkah yang dilakukan.
