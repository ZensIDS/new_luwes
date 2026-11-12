# todolist_harga_beli.md — Tutup semua celah pengubah harga beli

Project: Laravel gudang/outlet (Archive.zip). Dikerjakan bertahap; tiap tahap hanya kirim file yang berubah (tanpa zip), lalu todolist diupdate dan dikirim ulang.

STATUS: Tahap 0, 1, 2 selesai (mode `log`, perilaku fitur belum berubah). Tahap 3 selesai (perilaku baru: terima DO memakai HPP berlaku). Tahap 4 selesai (penandaan HPP turunan, perilaku fitur tidak berubah). Tahap 5 selesai (command maintenance lewat pagar + command baru `hpp:restore-zero`). Tahap 6 selesai (mode `enforce` aktif, tes, seeder). Tahap 8 selesai (import produk tidak lagi menimpa harga beli produk lama). Tahap 7 (verifikasi manual) disiapkan: alat bantu dan panduan sudah ada, menunggu hasil uji dari pemilik project.

## 1. Aturan

Harga beli HANYA boleh berubah lewat 3 jalur resmi:

| Kode | Jalur | Titik kode |
|------|-------|-----------|
| A | Edit Product | `ProductController@store/update` (+ `ProductRequest`) |
| B | Outlet Purchases | `OutletPurchaseController@receive` -> `OutletStockService::receive` (HPP batch outlet) |
| C | Master Harga Jual | `OutletPriceController@storeProduct/updateProduct` + `syncLatestHpp` |

Yang dihitung "harga beli" (K1, final):
- `products.harga_beli`
- `products.harga_beli_besar`
- `owner_stocks.hpp` (HPP batch outlet; batch terbaru = "HPP terbaru" yang dipakai menghitung harga jual, lihat `LatestHpp`)

Harga di catatan PO gudang (`stocks`, `pembelian_products`, `stock_pembelians`, `delivery_order_items`) TIDAK termasuk cakupan guard.

Catatan: zip hanya berisi `app`, `database`, `resources`, `routes`. `config/` dan `tests/` tidak ada, jadi `config/dbsync.php` tidak bisa kuperiksa.

## 2. Hasil audit: celah yang ditemukan

| # | Tingkat | Celah | Lokasi |
|---|---------|-------|--------|
| G1 | Tinggi | Import produk menimpa `products.harga_beli` produk yang sudah ada lewat `upsert`, dan `harga_beli_besar` tidak ikut disinkronkan. Import sudah tidak dipakai (K3), dikerjakan paling akhir. | `Imports/ProductsImport.php` (baris 201, 221-240) |
| G2 | Tinggi | Penerimaan Delivery Order ke outlet membuat/mengubah batch `owner_stocks.hpp` dari `delivery_order_items.harga_beli` (= harga PO gudang), sehingga HPP terbaru outlet bisa berubah lalu harga jual. Bukan jalur A/B/C. | `DeliveryOrderController.php` (baris 307, 397) |
| G3 | Sedang | Edit penerimaan PO menimpa `stocks.harga_beli` batch gudang dari item PO; nilai ini mengalir ke G2. (PO tidak mengubah `products.harga_beli`, aman.) | `PembelianController.php` (baris 958-961, 1150-1153) |
| G4 | Sedang | Hook `saving` di `Product` menulis ulang `harga_beli_besar` setiap model produk disimpan bila pasangan kecil/besar tidak konsisten (dari `updateStockValue()`, `increment('update_count')`, batal terima PO, dll). | `Models/Product.php` (baris 66-81, 247-251); `PembelianController.php` (1328-1329) |
| G5 | Sedang | Tidak ada pagar terpusat. Query builder (`Product::whereKey()->update`, `upsert`, `insert`, `DB::table`) melewati event model, dan `OwnerStock` tidak punya guard untuk `hpp`. | `OutletPriceController.php` (941), `ProductsImport.php`, command console |
| G6 | Sedang | `OutletStockService::receive()` selalu menulis `hpp` dari parameter, dipakai 3 pemanggil: Outlet Purchase (sah), Delivery Order (G2), Retur Penjualan (turunan). Belum ada penanda sumber yang sah. | `Services/OutletStockService.php` (47-72); `RefundPenjualanController.php` (377) |
| G7 | Rendah | `deficitStock()` dan Set Stock menulis `hpp` batch, nilainya turunan dari `LatestHpp`. Perlu ditandai "turunan" agar lolos guard. | `OutletStockService.php` (225, 361) |
| G8 | Rendah | Command maintenance menulis harga beli/HPP: `harga-beli:repair`, `product:fix-harga-beli`, `BackfillHargaBeliBesar`, `BackfillOwnerStockLedger`. Sudah ada dry-run/`--apply`, tapi tidak lewat pagar. Migration data lama (`2026_09_03_000002`, `2026_10_15_084557`) sudah lewat, tidak diubah. | `Console/Commands/*` |
| G9 | Rendah | Seeder demo menulis `harga_beli`/`hpp`. Aman asal tidak dijalankan di production. | `database/seeders/DemoDataSeeder.php`, `OutletReportSeeder.php` |
| G10 | Risiko | DbSync (lokal <-> server) menyalin/menggabung baris `products` dan `owner_stocks` lewat query builder, termasuk `harga_beli`/`hpp`. Bukan input harga baru, tapi membawa nilai dari DB lain. | `Services/DbSync/*` |

Sudah diperiksa dan tidak menulis harga beli/HPP: `OutletPriceController@inlineUpdateProduct`, `saveOpname` gudang dan toko, `ExpiredStockService`, `DeficitStockService` (baca), `CashierSaleService` / `CartController`, `ProductsMinStockImport`, `RefundController` lama, item PO.

## 3. Keputusan (Tahap 0) — SELESAI

- [x] K1 — Cakupan: hanya `products.harga_beli`, `products.harga_beli_besar`, `owner_stocks.hpp`. Hanya Edit Product, Outlet Purchases, dan Master Harga Jual yang boleh mengubahnya; yang lain tidak boleh.
- [x] K2 — Penerimaan DO ke outlet: pilih (a). Batch outlet dari DO memakai HPP yang sedang berlaku (`LatestHpp` / `products.harga_beli`), bukan harga PO.
- [x] K3 — Import produk: sudah tidak digunakan, tidak masalah tidak disentuh. Dikerjakan PALING AKHIR (Tahap 8). Aturan saat dikerjakan: produk yang sudah ada tidak boleh berubah harga belinya.
- [x] K4 — Command maintenance dan DbSync (diserahkan ke keputusan terbaik, sesuai inti permintaan):
  - Command maintenance lama (G8) dipertahankan, tapi dibungkus `allow('maintenance')`, default dry-run, wajib `--apply`, dan setiap perubahan dicatat ke log. Tidak dihapus karena masih berguna untuk perbaikan data darurat.
  - DbSync (G10) hanya dicatat (bukan input harga baru). Bila ingin diperiksa `exclude_tables`-nya, kirim `config/dbsync.php`.
- [x] K5 (tambahan permintaan) — Command baru untuk memulihkan HPP yang tertimpa jadi 0: produk yang sudah terdaftar di Master Harga Jual, yang HPP-nya sekarang 0 padahal sebelumnya bukan 0 (tertimpa oleh jalur non-resmi), dikembalikan ke HPP terakhir (nilai > 0) sebelum jadi 0. Penyebab tertimpa ditutup oleh guard (Tahap 1-6); command ini hanya memulihkan data yang sudah terlanjur rusak.

## 4. Tahap pengerjaan

Mekanisme: `HargaBeliGuard::allow('<jalur>', fn () => ...)` dan hook `saving` di `Product` / `OwnerStock` yang menolak perubahan `harga_beli`, `harga_beli_besar`, `hpp` di luar `allow()`. Mode `log` dulu (hanya mencatat), lalu `enforce` di akhir supaya tidak ada fitur yang tiba-tiba error.

### Tahap 1 — Guard terpusat (+ G4, G5) — SELESAI
- [x] `app/Support/HargaBeliGuard.php` (baru): `allow()`, `isAllowed()`, `checkModel()`, `assertAllowed()` (untuk query builder), mode log/enforce, jalur: edit-product, outlet-purchase, master-harga, turunan, maintenance, seeder
- [x] `app/Exceptions/HargaBeliGuardException.php` (baru): dilempar saat mode `enforce`
- [x] `config/harga_beli.php` (baru): `mode` = `log` | `enforce` (env `HARGA_BELI_GUARD_MODE`, default `log`), `log_channel`
- [x] `app/Models/Product.php`: hook guard; sinkron `harga_beli_besar` hanya saat `harga_beli` / `konversi_qty` / `harga_beli_besar` dirty atau produk baru (tutup G4)
- [x] `app/Models/OwnerStock.php`: hook guard untuk `hpp`
- Catatan: di mode `log`, simpan model di luar `allow()` hanya menulis log `[HargaBeliGuard]` (berisi target, nilai lama/baru, pemanggil, URL, user). Sampai Tahap 2 selesai, 3 jalur resmi juga akan tercatat; itu wajar.
- Catatan: pelanggaran lewat query builder (G5) belum tertangkap otomatis; `assertAllowed()` dipasang di titiknya pada Tahap 2-5.

### Tahap 2 — Bungkus 3 jalur resmi (A, B, C) + G6 — SELESAI
- [x] `ProductController.php`: `store` (`Product::create`) dan `update` (`$locked->update`) dalam `allow('edit-product')`
- [x] `OutletPriceController.php`: `storeProduct` (`Product::create`), `updateProduct` (`$locked->update`), dan blok tulis di `syncLatestHpp` (termasuk `Product::whereKey()->update`) dalam `allow('master-harga')`
- [x] `OutletPurchaseController.php`: `receive` memanggil `OutletStockService::receive()` dengan penanda `'outlet-purchase'`
- [x] `OutletStockService.php`: `receive()` punya parameter ke-9 opsional `?string $hppSource = null`; bila diisi, penulisan `hpp` batch (create/update `OwnerStock`) dibungkus `allow($hppSource)` lewat helper `writeHpp()`
- Catatan: `allow()` sengaja dipasang sempit, hanya di baris yang menulis harga beli / HPP, bukan membungkus seluruh transaksi. Dengan begitu penulisan lain di transaksi yang sama (mis. `settleDeficit` / G7) tetap tercatat di log sampai Tahap 4.
- Catatan: pemanggil `receive()` lain (`DeliveryOrderController` baris 397, `RefundPenjualanController` baris 377) belum mengirim `$hppSource`, jadi masih tercatat di log; dibereskan di Tahap 3 dan 4.
- Catatan: `inlineUpdateProduct` hanya mengubah `code`/`name`/`category_id`, tidak menulis harga beli, jadi tidak dibungkus.

### Tahap 3 — Delivery Order (G2/G3, K2-a) — SELESAI
- [x] `DeliveryOrderController.php` (`send`): batch outlet dari DO memakai `LatestHpp::forProduct(outlet, produk)` (HPP terbaru outlet, fallback `products.harga_beli`), dikirim ke `receive()` dengan penanda `'turunan'`. `delivery_order_items.harga_beli` tetap tersimpan sebagai catatan, tidak lagi dipakai untuk HPP outlet.
- [x] `OutletStockService.php` (`receive`): opsi `attributes['keep_batch_hpp']`. Bila batch yang sama sudah berisi stok (qty > 0), HPP batch dipertahankan, tidak ditimpa dan tidak melempar "Batch ... sudah memiliki HPP berbeda" (tanpa ini, DO kedua untuk batch yang sama bisa gagal setelah HPP berlaku berubah). Batch baru atau qty 0 memakai HPP berlaku.
- [x] Cek ulang `PembelianController.php` (G3): `stocks.harga_beli` hanya ditulis ke tabel gudang (`stocks`, `pembelian_products`, `stock_pembelians`) dan tidak menyentuh `products.harga_beli` / `owner_stocks.hpp`. Satu-satunya jalur ke HPP outlet adalah lewat DO, yang sudah diputus. Tidak ada perubahan kode.
- Catatan: bila produk belum punya batch outlet DAN `products.harga_beli` = 0, HPP berlaku = 0 sehingga batch DO masuk dengan HPP 0 (sebelumnya terisi harga PO). Ini mengikuti K2-a; isi HPP lewat Master Harga Jual / Edit Product. Tahap 5 (restore HPP 0) tidak memulihkan kasus ini karena memang belum pernah > 0.

### Tahap 4 — Hpp turunan (G7) — SELESAI
- [x] `OutletStockService.php`: `deficitStock()` (create batch MINUS dan update HPP perkiraan episode minus baru) dan `setInitialStock()` (create batch Set Stock) dibungkus `HargaBeliGuard::allow('turunan')`
- [x] `RefundPenjualanController.php` (`store`): `receive()` retur mengirim penanda `'turunan'`; nilai HPP tetap HPP batch asal (fallback `products.harga_beli` bila batch asal tidak ada), tidak berubah
- Catatan: `allow()` dipasang sempit, hanya di baris create/update `hpp`. `adjust()` dan `settleDeficit()` tidak menulis `hpp`, jadi tidak perlu dibungkus.
- Catatan: dengan Tahap 4, semua pemanggil `OutletStockService::receive()` (Outlet Purchase, DO, Retur) dan penulisan `hpp` di service sudah bertanda jalur. Sisa penulisan tak bertanda tinggal command maintenance (Tahap 5), seeder dan import (Tahap 6/8).
- Catatan: perilaku tidak berubah; hanya log `[HargaBeliGuard]` untuk jalur ini yang hilang di mode `log`.

### Tahap 5 — Command maintenance + command restore HPP 0 (G8, K4, K5) — SELESAI
- [x] `HargaBeliGuard.php`: helper baru `logMaintenance($command, $message, $context)` (level info; daftar > 500 elemen dipotong jadi total + 500 pertama)
- [x] `RepairHargaBeli.php` (`harga-beli:repair`), `FixHargaBeliSatuanBesar.php` (`product:fix-harga-beli`, termasuk `--restore`), `BackfillHargaBeliBesar.php`, `BackfillOwnerStockLedger.php`: penulisan di bawah `--apply` dibungkus `allow('maintenance')`, perubahan dicatat lewat `logMaintenance()` SETELAH transaksi selesai (log tidak muncul bila dibatalkan). Dry-run default dan `--apply` sudah ada sebelumnya, tidak diubah.
- [x] Command baru `RestoreZeroHpp.php`, signature `hpp:restore-zero`:
  - [x] sasaran: pasangan outlet + produk di Master Harga Jual (aturan tidak terhapus, produk tidak terhapus) yang `LatestHpp::forProduct` = 0
  - [x] sumber HPP pengganti, urut prioritas: (1) log batch `owner_stocks` "hpp X -> 0" (2) riwayat Master Harga Jual (log `OutletPriceHpp`) HPP > 0 terakhir (3) HPP awal batch (log created) (4) log produk "harga_beli X -> 0". Sumber dan tanggalnya dicetak di tabel dan CSV
  - [x] tanpa riwayat > 0 dilewati dan dilaporkan; HPP yang sengaja dinolkan lewat Master Harga Jual (catatan log terakhir = 0) juga dilewati
  - [x] dry-run default: tabel + CSV `storage/app/hpp-restore-zero-<waktu>.csv`
  - [x] `--apply` (+ konfirmasi, `--force` untuk melewati): tulis lewat `allow('maintenance')`; ditulis ke batch HPP terbaru (`LatestHpp::stockFor`), atau `products.harga_beli` + `harga_beli_besar` bila outlet tidak punya batch (sama dengan `syncLatestHpp`). Tiap pasangan dihitung ulang sebelum ditulis; yang sudah terisi dilewati
  - [x] opsi `--outlet=` dan `--product=` (id atau kode, pisahkan koma)
  - [x] riwayat HPP Master Harga Jual dicatat (`OutletPriceHpp`, event `hpp_changed`) dan ringkasan ke log guard
- Catatan: tidak perlu memanggil `syncLatestHpp`; harga jual dihitung langsung dari HPP terbaru dan tidak disimpan, jadi ikut benar begitu HPP dipulihkan.
- Catatan: `RepairHargaBeli` dan `BackfillOwnerStockLedger` menulis lewat `DB::table` (tidak memicu hook model). `allow('maintenance')` dipasang eksplisit supaya lolos guard saat mode `enforce`.
- Catatan: `hpp:restore-zero` hanya memulihkan batch yang menjadi HPP terbaru. Batch lain yang HPP-nya 0 (qty > 0) tidak disentuh; periksa di Tahap 7.
- Catatan: tidak bisa menjalankan `php -l` (PHP tidak tersedia saat pengerjaan); kurung/kurawal sudah dicek seimbang. Jalankan `php artisan list | grep hpp` dan dry-run dulu sebelum `--apply`.

### Tahap 6 — Enforce + tes — SELESAI
- [x] Audit penulis `harga_beli` / `harga_beli_besar` / `hpp` yang tersisa sebelum enforce: tidak ada penyimpanan model di luar jalur A/B/C/turunan/maintenance/seeder. Sisa penulisan mentah hanya `Imports/ProductsImport.php` (Tahap 8). `FixSetStockAdditive`, `ResetOutletData` (hapus baris), `SetOutletPriceByBrand`, `OutletPriceRoundReport`, `PerfBaseline`, DbSync tidak menulis harga beli/HPP lewat model.
- [x] `config/harga_beli.php`: `mode` default `enforce` (env `HARGA_BELI_GUARD_MODE=log` untuk kembali ke mode catat); kunci baru `allow_demo_seeder` (env `HARGA_BELI_ALLOW_DEMO_SEEDER`, default false)
- [x] `app/Exceptions/HargaBeliGuardException.php`: `render()` ramah user: web kembali ke halaman sebelumnya dengan pesan error `harga_beli`, JSON status 422. Transaksi yang sedang berjalan ikut batal.
- [x] `app/Support/HargaBeliGuard.php`: `seederAllowed()` (production ditolak kecuali `allow_demo_seeder`)
- [x] `database/seeders/DemoDataSeeder.php` dan `OutletReportSeeder.php` (G9): dibungkus `allow('seeder')`; di production berhenti dengan pesan error kecuali diizinkan lewat config
- [x] `tests/Feature/HargaBeliGuardTest.php` (baru, tanpa database): jalur resmi/turunan/maintenance/seeder lolos; Product/OwnerStock yang berubah di luar jalur ditolak (termasuk record baru); kolom lain bebas; `assertAllowed()`; izin dicabut walau error; allow bersarang; mode log; seeder di production
- [x] Tes pemindai kode: (1) penanda jalur (`'edit-product'`, `'turunan'`, dst.) hanya boleh ada di file yang terdaftar di `PATH_OWNERS`; (2) penulisan mentah (`DB::table('products'|'owner_stocks')->update/insert/upsert`, `Product::upsert/insert`, `Product::...->update`) tanpa `HargaBeliGuard` akan menggagalkan tes. Pengecualian sementara: `ProductsImport.php` (`RAW_WRITE_PENDING`, hapus di Tahap 8)
- Catatan: `tests/` tidak ada di zip awal. Tes memakai `Tests\TestCase` bawaan Laravel; bila belum ada di project, buat `tests/TestCase.php` + `tests/CreatesApplication.php` standar. Jalankan: `php artisan test --filter=HargaBeliGuardTest`.
- Catatan: tes belum sempat dijalankan (PHP tidak tersedia saat pengerjaan). Pola pemindai sudah disimulasikan dan lolos pada kode sekarang.
- Catatan: bila `.env` server sudah berisi `HARGA_BELI_GUARD_MODE=log`, nilai itu mengalahkan default; hapus atau ubah ke `enforce`, lalu `php artisan config:clear`. Cek dulu log `[HargaBeliGuard] di luar jalur resmi` bersih sebelum enforce di production; bila ada baris tersisa, kirim ke saya.

### Tahap 7 — Verifikasi manual (alat bantu SIAP, pengujian oleh pemilik project)
- Alat bantu: `app/Console/Commands/HargaBeliSnapshot.php` (`php artisan harga-beli:snapshot`, hanya membaca): simpan "foto" `products.harga_beli`, `harga_beli_besar`, `owner_stocks.hpp`, lalu `--compare=<csv>` untuk melihat yang berubah. Opsi `--product=` (id/kode).
- Panduan langkah per butir: `panduan_tahap7.md` (persiapan, 11 uji alur, import, restore HPP 0, command lama, dan cara kembali ke mode `log` bila darurat).
- Centang butir di bawah hanya setelah diuji.
- [ ] Mode `enforce` aktif (`php artisan tinker`: `config('harga_beli.mode')` = `enforce`) dan log `[HargaBeliGuard]` tidak berisi pelanggaran baru selama pemakaian normal
- [ ] Edit Product mengubah harga beli: berhasil
- [ ] Outlet Purchase diterima: HPP batch berubah
- [ ] Master Harga Jual ubah HPP: berhasil
- [ ] Terima DO: HPP terbaru outlet tidak berubah (K2-a)
- [ ] Edit penerimaan PO, batal terima PO, Set Stock, retur, opname: harga beli produk tidak berubah
- [ ] `harga_beli_besar` tidak berubah saat simpan produk yang bukan lewat 3 jalur
- [ ] Import produk: produk lama dengan harga beli berbeda di file tetap berharga lama (cek log `products-import`); produk baru terisi harga awal
- [ ] Command restore HPP 0: dry-run lalu apply pada data contoh, HPP kembali ke nilai terakhir > 0

### Tahap 8 — Import produk (G1, K3) — SELESAI
- [x] `Imports/ProductsImport.php`: `harga_beli` dihapus dari daftar kolom `upsert` untuk produk yang sudah ada (harga beli di file diabaikan). Produk BARU tetap boleh membawa harga awal: `Product::insert` dibungkus `allow('edit-product')`.
- [x] Harga di file yang berbeda dari harga di database (produk lama) dicatat ke log: `[HargaBeliGuard] maintenance products-import ... diabaikan` berisi `kode => [harga di DB, harga di file]`, supaya terlihat bila pengguna mengira import mengubah harga.
- [x] `tests/Feature/HargaBeliGuardTest.php`: `ProductsImport.php` masuk `PATH_OWNERS['edit-product']`; `RAW_WRITE_PENDING` kosong (dijaga tes); tes baru memastikan daftar kolom update `Product::upsert` tidak memuat `harga_beli`.
- [x] `Exports/ProductsExport.php`: tidak diubah. Ekspor hanya membaca `harga_beli` dan `harga_beli_besar`; kolom `harga_beli` pada file import tetap dibaca tetapi hanya dipakai untuk produk baru.
- Catatan: kolom `harga_beli_besar` tidak ada di import, jadi produk baru dari import tidak punya harga besar (sama seperti sebelumnya).
- Catatan: tes belum sempat dijalankan (PHP tidak tersedia); regex tes upsert sudah disimulasikan pada kode sekarang.

## 5. Urutan kirim file

Tahap 0 (selesai) -> 1 -> 2 -> 3 -> 4 -> 5 -> 6 -> 8 (selesai) -> 7 (verifikasi manual, tersisa). Tahap 1 dan 2 tidak mengubah perilaku (mode `log`); perilaku baru mulai di Tahap 3. Tiap tahap selesai: update todolist, kirim todolist + file yang berubah saja (tanpa zip).