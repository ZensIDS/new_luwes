# todolist_harga_beli.md — Tutup semua celah pengubah harga beli

Project: Laravel gudang/outlet (Archive.zip). Dikerjakan bertahap; tiap tahap hanya kirim file yang berubah (tanpa zip), lalu todolist diupdate dan dikirim ulang.

STATUS: Tahap 0, 1, 2 selesai (mode `log`, perilaku fitur belum berubah). Tahap 3 selesai (perilaku baru: terima DO memakai HPP berlaku). Berikutnya Tahap 4.

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

### Tahap 4 — Hpp turunan (G7)
- [ ] `OutletStockService.php`: `deficitStock()` dan Set Stock ditandai `allow('turunan')`
- [ ] `RefundPenjualanController.php`: retur pakai hpp batch asal, ditandai turunan

### Tahap 5 — Command maintenance + command restore HPP 0 (G8, K4, K5)
- [ ] `RepairHargaBeli.php`, `FixHargaBeliSatuanBesar.php`, `BackfillHargaBeliBesar.php`, `BackfillOwnerStockLedger.php`: bungkus `allow('maintenance')`, default dry-run, `--apply`, log perubahan
- [ ] Command baru (mis. `RestoreZeroHpp.php`, signature `hpp:restore-zero`):
  - [ ] cari produk yang terdaftar di Master Harga Jual (per outlet) yang HPP terbarunya sekarang 0 padahal pernah > 0
  - [ ] cari HPP terakhir > 0 sebelum jadi 0 dari riwayat batch `owner_stocks` (urut waktu terbaru ke lama); bila riwayat batch tidak cukup, cek sumber lain (`products.harga_beli`, ledger `owner_stock` bila ada) dan laporkan sumbernya
  - [ ] produk yang memang selalu 0 (tidak pernah > 0) dilewati dan dilaporkan, bukan ditebak
  - [ ] dry-run default: tampilkan tabel produk, outlet, HPP sekarang, HPP pengganti, sumber batch/tanggal
  - [ ] `--apply` menulis lewat `allow('maintenance')`; produk tanpa riwayat HPP > 0 dilewati dan dilaporkan
  - [ ] opsi `--outlet=` dan `--product=` untuk membatasi cakupan
  - [ ] setelah apply, panggil logika sinkron HPP terbaru (`syncLatestHpp`) bila perlu agar harga jual ikut benar

### Tahap 6 — Enforce + tes
- [ ] `config/harga_beli.php`: `mode` = `enforce`
- [ ] `tests/Feature/HargaBeliGuardTest.php` (baru): 3 jalur resmi lolos, jalur lain ditolak
- [ ] Tes sederhana yang gagal bila ada penulisan `harga_beli` / `hpp` baru di luar daftar jalur (tangkap `DB::table`, `upsert`, `insert`)
- [ ] Seeder demo: bungkus `allow('seeder')` atau pastikan tidak jalan di production (G9)

### Tahap 7 — Verifikasi manual
- [ ] Edit Product mengubah harga beli: berhasil
- [ ] Outlet Purchase diterima: HPP batch berubah
- [ ] Master Harga Jual ubah HPP: berhasil
- [ ] Terima DO: HPP terbaru outlet tidak berubah (K2-a)
- [ ] Edit penerimaan PO, batal terima PO, Set Stock, retur, opname: harga beli produk tidak berubah
- [ ] `harga_beli_besar` tidak berubah saat simpan produk yang bukan lewat 3 jalur
- [ ] Command restore HPP 0: dry-run lalu apply pada data contoh, HPP kembali ke nilai terakhir > 0

### Tahap 8 — Import produk (G1, K3) — PALING AKHIR
- [ ] `Imports/ProductsImport.php`: hapus `harga_beli` dari daftar kolom `upsert` untuk produk yang sudah ada (produk baru boleh isi harga awal)
- [ ] (opsional) `Exports/ProductsExport.php`: template mengikuti keputusan di atas

## 5. Urutan kirim file

Tahap 0 (selesai) -> 1 -> 2 -> 3 -> 4 -> 5 -> 6 -> 7 -> 8. Tahap 1 dan 2 tidak mengubah perilaku (mode `log`); perilaku baru mulai di Tahap 3. Tiap tahap selesai: update todolist, kirim todolist + file yang berubah saja (tanpa zip).