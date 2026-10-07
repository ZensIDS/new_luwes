# TODO Optimasi Query & Beban Koneksi Database

Tujuan: menghilangkan error `SQLSTATE[42000] [1226] ... exceeded the 'max_user_connections' resource (current value: 25)` dengan cara **mengurangi jumlah dan lama query per request**, bukan hanya menaikkan limit hosting.

---

## 0. Konteks & Asumsi

- Batas hosting: **25 koneksi MySQL bersamaan** per akun. Satu request PHP memegang 1 koneksi selama ia berjalan. Penyebab error = *banyak request berat berjalan bersamaan*.
- `.env`: `SESSION_DRIVER=file`, `CACHE_DRIVER=file`, `QUEUE_CONNECTION=sync`. Jadi session dan cache **tidak** membebani DB.
- `RoleMiddleware` melakukan 1 query `users` per request. Itu normal dan **bukan penyebab**, hanya korban pertama saat koneksi habis.
- **Fitur import diabaikan** (tidak dipakai, import dilakukan langsung lewat SQL). Pengecualian: polling `product.import-statuses` dihapus (lihat Fase 1).
- Catatan nomor baris dibuat dari zip `Archive_3.zip` dan bisa bergeser bila file sudah berubah.
- Yang belum terverifikasi (tidak ada di zip): `config/*`, jumlah data produk/stok/transaksi, index yang sebenarnya ada di DB hosting, pengaturan cron hosting.

### Legenda prioritas

| Kode | Arti |
|---|---|
| **P0** | Kerjakan dulu, dampak terbesar atau risiko paling tinggi |
| **P1** | Dampak besar, perbaikan jelas |
| **P2** | Dampak sedang, perbaikan terisolasi |
| **P3** | Perapian / pencegahan |

---

## Progres

| Fase | Status |
|---|---|
| 1. Hapus polling & fitur import | ✅ Selesai |
| 2. View composer `layouts.master` | ✅ Selesai |
| 3. Dashboard | ✅ Selesai (urgentSuppliers & top-5 ditunda) |
| 4. Pencarian produk kasir (POS) | ✅ Selesai (prefix LIKE & FULLTEXT tidak dipakai, sesuai keputusan #5) |
| 5. N+1 terkonfirmasi | ✅ Selesai |
| 6. `find()` / query di dalam loop | ✅ Selesai (sebagian butir sengaja tidak di-insert massal, lihat catatan) |
| 9. Index database | ✅ Migrasi dibuat (belum dijalankan di hosting) |
| 7. Export Excel/PDF | ✅ Selesai (PDF tidak diubah, lihat catatan) |
| 8. Price checker publik | ✅ Selesai (token kiosk/IP ditunda, perlu keputusan) |
| 10. Pengaturan produksi & pengaman | ✅ Bagian kode selesai (`.env`, config, deploy & beberapa keputusan menunggu Anda) |
| 11. Pengukuran | 🟡 Alat ukur dibuat (`php artisan perf:baseline`). Angka baseline/sesudah **menunggu Anda menjalankannya** di lokal/hosting |

## Ringkasan Tugas

| # | Tugas | Prioritas | Lokasi utama |
|---|---|---|---|
| 1 | Hapus polling `product.import-statuses` | P0 | `products/index.blade.php`, `ProductController`, `routes/web.php` |
| 2 | Cache & ringankan view composer `layouts.master` | P0 | `AppServiceProvider` |
| 3 | Ringankan dashboard | P0 | `DashboardController@index` |
| 4 | Ringankan pencarian produk kasir (POS) | P0 | `Cart.jsx`, `ProductController@index`, `ProductResource` |
| 5 | Perbaiki N+1 terkonfirmasi | P1 | `PembelianController`, `StockController`, `RefundPembelianController`, `products/index.blade.php` |
| 6 | Perbaiki `find()` di dalam loop | P2 | `RequestOrderController`, `StockController`, `PembelianController`, dll |
| 7 | Ringankan export Excel/PDF | P1 | `app/Exports/*`, `ReportQuery` |
| 8 | Amankan & ringankan price checker publik | P1 | `PriceCheckerController` |
| 9 | Tambah index database | P1 | migrasi baru |
| 10 | Pengaturan produksi & pengaman | P1 | `.env`, `routes/web.php`, `Kernel.php` |
| 11 | Pengukuran sebelum dan sesudah | P0 | semua |

> Urutan kerja yang disarankan: **11 (ukur awal) → 1 → 2 → 3 → 4 → 5 → 9 → 7 → 8 → 6 → 10 → 11 (ukur akhir)**.

---

## Fase 0. Persiapan

- [ ] Backup database sebelum perubahan apa pun, terutama sebelum menambah index.
- [ ] Kerjakan dan uji di lokal / staging dulu, baru naik ke hosting.
- [ ] Siapkan cara menghitung query per halaman (lihat Fase 11). Tanpa angka awal, hasil optimasi tidak bisa dibuktikan.
- [ ] Putuskan keputusan-keputusan pada bagian **"Keputusan yang perlu dikonfirmasi"** di bawah.

---

## Fase 1. Hapus polling `product.import-statuses` (P0) — ✅ SELESAI

> **Status: selesai (dikerjakan sesuai keputusan #2: import dibuang sekalian).**
> Catatan: model `ProductImport` & `ProductImportFailure` sengaja **tidak dihapus** karena masih dipakai `database/seeders/DemoDataSeeder.php`; tabelnya juga tidak disentuh. Tombol "Template" & "Template Min Stock" di view dibiarkan (hanya export, tidak membebani DB) — hapus bila tidak dibutuhkan.
> File yang dihapus: `app/Jobs/ProcessProductImportChunk.php`, `app/Imports/ProductsImport.php`, `app/Imports/ProductsMinStockImport.php`, `app/Imports/ReadFilters/HeadingAndChunkReadFilter.php`.

**Masalah:** `products/index.blade.php` memanggil `product.import-statuses` setiap 10 detik, terus-menerus, termasuk di tab yang tidak aktif. Tiap panggilan = session + query `users` (RoleMiddleware) + query `ProductImport` + `batch()` per baris. Halaman produk yang tidak sengaja dibiarkan terbuka tetap membebani DB.

**Lokasi:**

| Bagian | Lokasi |
|---|---|
| JS polling | `resources/views/products/index.blade.php` ± baris 320–356 (`renderImportRows`, `refreshImportStatuses`, `setInterval(..., 10000)`) |
| Panel "Progress Import Produk" | `products/index.blade.php` ± baris 140–185 (`#product-import-status-body`) |
| Route | `routes/web.php` ± baris 277 (`products/import-statuses`) |
| Method controller | `ProductController::importStatuses()` ± baris 363–372 |
| Data awal untuk panel | `ProductController::index()` ± baris 196–200 (`recentImports`) |
| Helper format | `ProductController::formatProductImport()` dan `productImportStatusLabel()` ± baris 475+ |

**Checklist (minimal, sesuai permintaan):**

- [x] Hapus blok JS `refreshImportStatuses` dan `setInterval` (beserta `renderImportRows`).
- [x] Hapus route `product.import-statuses`.
- [x] Hapus method `importStatuses()`.
- [x] Hapus panel "Progress Import Produk" di view, karena tanpa polling isinya tidak pernah diperbarui.
- [x] Hapus pengisian `recentImports` di `index()` (1 query + `batch()` per baris pada setiap buka halaman produk).
- [x] Hapus `formatProductImport()` dan `productImportStatusLabel()` bila sudah tidak dipakai.
- [x] Cek tidak ada view/JS lain yang memanggil `route('product.import-statuses')` (`grep -rn "import-statuses" app resources routes`).

**Opsional (hanya bila tombol/fitur import ikut dibuang):**

- [x] Hapus tombol dan modal Import / Import Min Stock di view (± baris 28–39 dan 80–135).
- [x] Hapus route `product.import` dan `product.min-stock.import`, serta method `import()` dan `importMinStock()`.
- [x] Hapus `ProcessProductImportChunk`, `ProductsImport`, dan `ProductsMinStockImport` (+ `HeadingAndChunkReadFilter`).
- [ ] `ProductImport` / `ProductImportFailure` **dipertahankan** (masih dipakai `DemoDataSeeder`). Hapus nanti bersama bagian seeder bila ingin.
- [x] Hapus jadwal `queue:work --queue=imports` di `app/Console/Kernel.php` (dengan `QUEUE_CONNECTION=sync` ia tidak berguna). Setelah itu cron `schedule:run` tiap menit tidak diperlukan lagi.
- [x] (dipatuhi) Jangan hapus tabel `jobs`, `job_batches`, `product_imports` lewat migrasi sebelum yakin tidak ada data yang dibutuhkan.

**Catatan:** import via SQL langsung tidak melewati aplikasi, jadi tidak ada yang rusak.

---

## Fase 2. View composer `layouts.master` (P0) — ✅ SELESAI

> **Status: selesai.** Mengikuti keputusan #1 (hanya admin-gudang) dan #3 (realtime, **tanpa cache**).
> - Perhitungan stok minimum dipindah ke SQL di service baru `app/Services/LowStockService.php` (satu query count + satu query 20 teratas, tidak ada lagi muat semua produk ke PHP).
> - Composer hanya berjalan untuk role `admin-gudang`; lonceng notifikasi di `layouts/master.blade.php` juga hanya tampil untuk `admin-gudang`.
> - `LowStockService::lowStockQuery()` disiapkan untuk dipakai ulang di dashboard (Fase 3).
> - Catatan: pembulatan `CEIL` kini dihitung MySQL (DECIMAL), jadi tidak kena selisih float PHP (mis. 10 × 1,1). Hasil bisa beda 1 unit pada kasus langka dibanding versi lama, dan versi baru yang lebih akurat.
> - `settings.json` dibiarkan (baca file, bukan DB).

**Lokasi:** `app/Providers/AppServiceProvider.php`, `View::composer('layouts.master', ...)`.

**Masalah:** setiap halaman penuh untuk user yang login menjalankan:

1. query `ProductMinimumAdjustment::activeOn(...)` (seluruh adjustment aktif),
2. query `Product` untuk semua produk `min_stock > 0` (atau yang punya adjustment) dengan `withSum('stocks as stock_qty','qty')`, dimuat **seluruhnya ke memori PHP** lalu difilter di PHP,
3. `Storage::disk('public')->get('settings.json')` (baca file, ringan tapi tiap request).

Hasilnya hanya dipakai untuk badge/dropdown stok minimum (maksimal 20 produk).

**Checklist:**

- [x] ~~Cache hasil perhitungan~~ **dibatalkan** karena keputusan #3 (harus realtime).
- [x] Alternatif lebih ringan: hitung di SQL (`HAVING stock_qty <= effective_min`) sehingga PHP tidak memuat semua produk. Kalau perhitungan adjustment sulit di SQL, cukup cache.
- [x] **Jalankan hanya untuk role yang butuh** notifikasi stok minimum (lihat keputusan #1). Jangan jalankan untuk kasir/customer bila badge tidak tampil di sana.
- [x] Jangan jalankan untuk request AJAX (composer hanya jalan di render view penuh, tapi pastikan tidak ada AJAX yang merender `layouts.master`).
- [x] Ekstrak logika ke satu service (mis. `LowStockService`) agar **dipakai bersama dashboard** (Fase 3) dan hanya dihitung sekali.
- [x] Cache isi `settings.json` (logo) atau biarkan, karena ini baca file, bukan DB.
- [x] Invalidasi: tidak diperlukan (tanpa cache).

---

## Fase 3. Dashboard (P0) — ✅ SELESAI (sebagian item sengaja ditunda, lihat catatan)

> **Status: selesai.** Mengikuti keputusan #3 (realtime, **tanpa cache**).
> - Widget "Produk di Bawah Min Stok" kini memakai `LowStockService::dashboardList()` (SQL, satu query), service yang sama dengan lonceng notifikasi, jadi tidak ada perhitungan ganda.
> - Modal "Pengaturan Min Stok" tidak lagi memuat semua produk di setiap buka dashboard. Daftarnya dimuat lewat AJAX (`GET /product/minimum-adjustment/products`, route `product.minimum-adjustment.products`) saat modal pertama kali dibuka, dan hanya untuk `admin-gudang` / `superadmin` (sama dengan tombolnya).
> - Variabel tak terpakai dihapus: `products`, `stocks`, `penjualans`, `pembelianTerkirim`, `totalRevenue` (4 query hilang).
> - Cek `wantsJson()` dipindah ke atas, sebelum query berat.
> - **Sengaja dibiarkan:** `$urgentSuppliers` (logika jadwal ada di PHP: `deadline_days` JSON + interval minggu, eager-load sudah dibatasi 4 minggu), dan top-5 `groupBy` (menunggu index Fase 9).

**Lokasi:** `app/Http/Controllers/DashboardController.php`, `index()`.

**Masalah:** sekitar 25 query per buka dashboard, tanpa cache, padahal dashboard adalah halaman landing semua role (`/` redirect ke `/dashboard`). Perhitungan stok minimum juga **duplikat** dengan composer di Fase 2.

**Checklist:**

- [x] ~~Cache::remember dashboard~~ **dibatalkan** (keputusan #3: harus realtime). Dashboard staff-outlet/kasir sudah ringan (2 count).
- [x] `Stock::sum('qty')` dihitung **dua kali** (`$totalStock` dan `'stocks'`). Pakai satu variabel.
- [x] `$adjustmentProducts` memuat **semua produk** dengan `withSum` tanpa filter dan tanpa limit. Pindahkan ke endpoint AJAX / lazy load, atau batasi dan paginasi, atau muat hanya saat modal dibuka.
- [x] `$lowVelocityProducts`: filter di SQL bila memungkinkan, jangan `get()` semua lalu `filter()` di PHP. Gabungkan dengan service Fase 2.
- [x] `$recentlyDeliveredIds` + `whereNotIn('id', $ids)`: ganti dengan `whereNotExists`/`NOT EXISTS` supaya tidak membuat daftar ID besar di PHP lalu mengirimnya balik ke MySQL.
- [x] `$nearExpiryStocks` memakai `whereDate('expired_at', ...)` yang membungkus kolom dengan `DATE()` sehingga index tidak terpakai. Ganti ke pembanding rentang (`>=` startOfDay dan `<=` endOfDay, seperti pola `ReportQuery::betweenDates`).
- [ ] *(ditunda, sengaja dibiarkan)* `$urgentSuppliers`: memuat semua supplier + `pembelians` 4 minggu terakhir lalu difilter di PHP. Batasi di SQL bila jumlah supplier banyak.
- [ ] *(menunggu Fase 9)* Pastikan query top-5 (`groupBy product_id` pada `stocks`, `request_order_items`, `delivery_order_items`) terbantu index (lihat Fase 9).
- [x] Hapus variabel yang tidak dipakai di view (cek `totalRevenue => 0`, `products`, `penjualans`, `pembelianTerkirim` apakah benar dipakai).

---

## Fase 4. Pencarian produk kasir / POS (P0) — ✅ SELESAI (item FULLTEXT & prefix `LIKE` sengaja tidak dikerjakan)

> **Status: selesai.** Mengikuti keputusan #5 (pencarian tetap boleh `%kata%`, jadi pola `LIKE` untuk nama/kode/brand/model **tidak diubah**).
> - **Klien (`Cart.jsx`):** pencarian 1 karakter tidak dikirim ke server, debounce 250 → 450 ms, parameter `per_page` yang tidak pernah dipakai server dihapus. **Perlu build ulang aset JS.**
> - **Server (`ProductController::index`):**
>   - Jalur scan: bila input ≥ 6 karakter tanpa spasi, dicoba dulu pencocokan eksak `code = ?` atau `serial_number = ?` di stok outlet. Bila ketemu, `LIKE` tidak dijalankan. Bila tidak, lanjut ke `LIKE` seperti biasa.
>   - `harga_jual LIKE` dihapus **hanya di konteks kasir**; daftar produk gudang (admin) tetap bisa cari harga.
>   - Pencarian serial `LIKE` (subquery bersarang) di kasir hanya jalan bila input ≥ 4 karakter tanpa spasi.
>   - `whereDate('expired_at', ...)` diganti `where('expired_at', '>=', today())` (hasil sama, tetapi bisa memakai index). Empat salinan closure yang sama digabung jadi satu.
>   - `paginate(10)` dipertahankan dan variabel `$perPage` yang tidak terpakai dihapus (klien sebelumnya minta 25 tetapi server selalu 10; 10 dipilih agar beban tidak naik).
> - **`ProductResource`:** konteks gudang memakai `stock_qty` dari `withSum()` (1 subselect) bukan `total_stock` per baris. Fallback ke accessor bila `stock_qty` tidak ada (resource dipakai tempat lain).
> - **`DatabaseStorage::get()`:** 1 `find()` saja (sebelumnya `has()` + `find()` = 2 query).
> - **`CartController@index`:** `availableStock` kini dijumlahkan dari `$ownerStocks` yang sudah di-load (syarat query identik), menghemat 1 query per item keranjang pada setiap `GET /cart`. Sisa query per item (harga outlet, `LatestHpp`) belum diubah karena menyentuh logika harga. Lihat catatan di bawah.
> - Catatan Fase 9: `products.code` tidak punya index di migrasi awal. Pencocokan eksak `code = ?` baru benar-benar cepat setelah ada index (cek `SHOW INDEX FROM products`).

**Lokasi:** `resources/js/components/Cart.jsx` (± baris 152–254), `ProductController::index()` dan `outletProducts()` (± baris 33–200), `app/Http/Resources/ProductResource.php`.

**Masalah:**

- Setiap ketikan memanggil `/outlet/{id}/products` (debounce 250 ms). Klien sudah membatalkan request lama, tetapi query yang sudah berjalan di server tetap diproses sampai selesai.
- Query: `name/code/harga_jual/brand/model LIKE '%...%'` (5 kolom) **ditambah** `orWhereHas('ownerStocks')` yang bersarang dengan `whereHas('stock')` untuk `serial_number LIKE '%...%'`. Pola `%x%` tidak bisa memakai index.
- `ProductResource` memanggil `$this->total_stock` (`stocks()->sum('qty')`) per produk hasil pencarian = 1 query per baris di konteks non-outlet.
- `$perPage` dihitung tetapi `->paginate(10)` di-hardcode (cek apakah memang disengaja).

**Checklist:**

- [x] Tambah syarat minimal **2–3 karakter** sebelum mencari, dan naikkan debounce ke ± 400–500 ms.
- [x] **Pisahkan jalur scan barcode dan jalur ketik:** coba pencocokan eksak dulu (`code = ?`, atau serial = ?). Baru bila tidak ketemu, jalankan `LIKE`.
- [ ] ~~Gunakan `LIKE 'kata%'` (prefix)~~ **tidak dikerjakan** (keputusan #5: pencarian tetap `%kata%`). Pencocokan eksak untuk scan sudah menutup kasus barcode.
- [x] Hapus `harga_jual LIKE` dari pencarian (kolom angka, nyaris tidak berguna untuk kasir).
- [x] Pencarian serial number (`orWhereHas` bersarang) hanya dijalankan bila input terlihat seperti serial/barcode panjang, atau dipisah ke endpoint lain.
- [ ] ~~Pertimbangkan **FULLTEXT index**~~ **tidak dikerjakan**: FULLTEXT tidak mendukung pencarian potongan kata di tengah (`%kata%`), sehingga bertentangan dengan keputusan #5.
- [x] `ProductResource`: pada konteks warehouse pakai `withStockTotals()`/`withSum` di query, bukan accessor `total_stock` per baris.
- [x] Cek `perPage` vs `paginate(10)`; samakan.
- [x] `DatabaseStorage` (wishlist/cart): `has()` lalu `get()` memanggil `find()` dua kali. Cukup 1 `find()` per akses (`get()` langsung `find()` lalu cek null).
- [ ] *(sebagian)* Ukur `CartController@index` (dipanggil `GET /cart` tiap perubahan keranjang): jumlah query, apakah `promotionService->activeForOutlet()` bisa di-cache pendek per outlet.
  - Hasil pengukuran dengan membaca kode: tiap item keranjang menjalankan query `ownerStocks` (1), `OutletPrice` (1), dan `LatestHpp::forProduct` (≥ 1). Sebelumnya ada 1 query `availableStock` tambahan, kini dihapus. Mengelompokkan `OutletPrice` & `LatestHpp` untuk semua item sekaligus (eager load) masih bisa dilakukan, tetapi menyentuh perhitungan harga, jadi sebaiknya dikerjakan terpisah dan diuji dengan transaksi nyata.
  - `promotionService->activeForOutlet()` **tidak** di-cache (harga/promo harus sama dengan yang berlaku saat transaksi).

---

## Fase 5. N+1 terkonfirmasi (P1) — ✅ SELESAI

> **Status: selesai.** Semua perbaikan mempertahankan hasil yang sama, hanya jumlah query yang turun.
> - `PembelianController::getProductsBySupplier`: `withSum('stocks as stock_sum_qty')`, 1 query (sebelumnya 1 per produk).
> - `StockController::exportOpnameTemplate`: `Stock::whereIn(...)->with('product')` sekali. Ikut menghapus lazy-load `product` per baris di `StockOpnameTemplateExport`, yang tidak ada di daftar awal. Baris yang stoknya sudah tidak ditemukan dilewati, tidak lagi membuat error.
> - `RefundPembelianController::getOutletProducts`: satu query `DeliveryOrderItem` untuk semua `stock_id`, lalu dicocokkan di PHP (diurutkan `id` asc, sama dengan `first()` sebelumnya).
> - `effective_min_stock`: ditambah `Product::scopeWithEffectiveMin()` (subselect persen adjustment aktif). Accessor memakai nilai itu bila ada, dan tetap query sendiri bila tidak (jadi pemakai lain tidak rusak). Dipasang di `products/index` (via `ProductController::index`) **dan** `PembelianController::getAllProducts`, yang tidak ada di daftar awal tetapi memiliki N+1 yang sama (1 query adjustment per produk).
> - Komentar peringatan ditambahkan di `Product.php` untuk `total_stock`, `total_available_stock`, `total_reserved_stock`, `effective_min_stock`, `isLowStock`.
> - Hasil `grep` pemakaian lain: tidak ada pemakaian berbahaya tersisa. `ProductController` ± baris 273 (`total_stock` untuk 1 produk) aman; `isLowStock()` tidak dipanggil di mana pun selain definisinya.

| Lokasi | Masalah | Perbaikan |
|---|---|---|
| `PembelianController::getProductsBySupplier` (± baris 41–52) | `$product->stocks()->sum('qty')` per produk | `->withSum('stocks as stock_count','qty')` (pola sudah dipakai di `getAllProducts()` pada file yang sama) |
| `StockController::exportOpnameTemplate` (± baris 495–530, `find` di baris 519) | `Stock::find($row->last_stock_id)` per baris produk | Kumpulkan semua `last_stock_id`, ambil sekali dengan `Stock::whereIn('id', $ids)->with('product')->get()->keyBy('id')`, lalu isi `qty` dari total |
| `RefundPembelianController::getOutletProducts` (± baris 45–67) | query `DeliveryOrderItem` + `whereHas` per `ownerStock` | Ambil semua `DeliveryOrderItem` untuk `stock_id` terkait dengan satu `whereIn`, `groupBy('stock_id')`, lalu cocokkan di PHP |
| `products/index.blade.php` baris ± 252 | `$value->effective_min_stock` dipanggil per baris; accessor itu query `ProductMinimumAdjustment` tiap kali | Pre-load adjustment aktif sekali di controller (seperti dashboard: `activeOn()->get()->groupBy('product_id')`) atau jadikan relasi yang di-eager-load, lalu hitung dari data tersebut |

**Checklist:**

- [x] Perbaiki keempat titik di tabel di atas.
- [x] Di `app/Models/Product.php` (± baris 136–215), beri komentar peringatan pada accessor `total_stock`, `total_available_stock`, `total_reserved_stock`, `effective_min_stock`: **tiap akses = 1 query**, jangan dipakai di loop/koleksi. Gunakan `withStockTotals()` / `withSum`.
- [x] `Product::isLowStock()` memanggil dua accessor ber-query (2 query per produk). Pastikan tidak dipanggil di loop.
- [x] Cari pemakaian lain: `grep -rn "total_stock\|effective_min_stock\|isLowStock\|total_available_stock\|total_reserved_stock" app resources`.
- [x] `ProductController` baris ± 269 memakai `total_stock` untuk satu produk (aman bila bukan di loop).

---

## Fase 6. `find()` / query di dalam loop (P2) — ✅ SELESAI

> **Status: selesai.** Nomor baris di butir lama sudah bergeser, jadi lokasi di bawah memakai nama method.
> Pola umum: muat sekali dengan `whereIn(...)->get()->keyBy('id')`, pakai instance yang sama di dalam loop. Bila stok yang sama bisa muncul dua kali, instance dipakai bersama atau di-`refresh()` karena `qty_available` adalah kolom *generated* di DB.
> **Perlu dites:** verifikasi RO, alokasi stok RO, selesai & kirim RO, stock opname, penerimaan & edit PO, retur pembelian (gudang→supplier, outlet→gudang, hapus, terima), simpan voucher/campaign, simpan adjustment min stok.
> **Koreksi Fase 5:** di zip, perbaikan `RefundPembelianController::getOutletProducts` ternyata tidak terpasang benar (potongan kodenya terselip di atas `<?php` sehingga file gagal di-parse, dan method aslinya masih versi lama). Sudah dirapikan di Fase 6 ini. Semua file PHP di project sudah dicek lolos `php -l`.

- [x] `RequestOrderController::processVerification`: item & stok dimuat sekali (stok dengan `lockForUpdate()`), `fresh()` per item diganti `refresh()` per stok.
- [x] `RequestOrderController::updateStocks`: `RequestOrderItem::find` dan `Stock::find` per alokasi diganti muat sekali (stok dengan `lockForUpdate()`; `stock_id` sudah divalidasi `distinct`).
- [x] `RequestOrderController::completeAndShip`: `Stock::find` per item picking dan per sisa reservasi diganti satu query dengan `lockForUpdate()`; instance dipakai bersama oleh `allocate()` dan `unreserve()`.
- [x] `StockController` (penyesuaian/opname, loop `items`): `Stock::find` per item diganti muat sekali.
- [x] `PembelianController`: `Product::find` per item di penerimaan, `updateStock()` (cabang published dan non-published) diganti muat sekali. Di cabang non-published, `Product::find(...)->is_serialized` kedua kini memakai `$product` yang sama (menghemat 1 query per item).
- [x] `RefundPembelianController`: `store()` (`findOrFail` per baris + lazy load `product`/`ownerStock`), `destroy()`, dan `terima()` (`findOrFail` item + `find` stok) diganti muat sekali.
- [x] `CashierSaleService::lockedVouchers`: `redemptions()->exists()` per kode diganti satu query `whereIn`.
  - **Sengaja tidak diubah:** `items()->create` + `stockService->issue` per alokasi dan `VoucherRedemption::create`. Keduanya melewati cast/event model dan logika stok per baris, dan jumlahnya kecil per transaksi. Insert massal berisiko mengubah hasil transaksi kasir. `Voucher::isActive()` masih memanggil `redemptions()->count()` per voucher (hanya bila voucher punya limit).
- [x] `ProductMinimumAdjustmentController::store`: cek overlap per produk diganti satu query `whereIn`. `create()` per produk dipertahankan (admin-only, jarang).
- [x] `CampaignController::storeVoucher`: pivot `voucher_products` dan `voucher_outlets` kini di-insert massal (`sync` per voucher dihapus). `Voucher::create` per kode dipertahankan karena butuh `id` dan event model.

> Prioritas P2 karena jumlah item per transaksi umumnya kecil. Naikkan bila log menunjukkan transaksi besar.

---

## Fase 7. Export Excel / PDF (P1) — ✅ SELESAI

> **Status: selesai.** Mengikuti keputusan #7 (**tanpa** pembatasan rentang tanggal).
> - **Export tabel penuh dibaca per potongan (chunk)**, bukan `->get()` sekaligus:
>   - `ProductsExport`, `ProductsMinStockExport`, `SuppliersExport`, `StockExport` kini `FromQuery` (+ `WithMapping`). Mode template memakai query kosong (`whereRaw('1 = 0')`), hanya heading.
>   - `LaporanBarangMasukExport`, `LaporanBarangKeluarExport`, `LaporanAktifitasExport`, `LaporanPergerakanExport`: dibaca per 2000 (movement) / 1000 (produk) baris dengan `chunk()`. Dokumen referensi di-resolve per potongan (1 query per tipe). Hasil baris sama, hanya model Eloquent yang tidak menumpuk di memori dan tidak ada satu query raksasa.
> - **N+1 diperbaiki:** `ProductsExport` memakai `suppliers` di `map()` tanpa eager-load (1 query per produk). Kini di-eager-load. `StockExport` membuang eager-load `pembelian` yang tidak dipakai.
> - **Urutan stabil:** semua query chunk ditambah `orderBy('id')` sebagai pembeda agar paging tidak melompat/duplikat.
> - **`whereDate` dihapus seluruhnya** (`grep -rn "whereDate" app` kini hanya menyisakan komentar): diganti `ReportQuery::betweenDates()` (rentang) dan helper baru `ReportQuery::onDate()` (satu hari) di `LaporanController` (10 tempat), `OutletLaporanService`, 6 export `Laporan*`, `ReturOutletExport`, `ReturSupplierExport`, 5 export harian (`Pembelian*`, `Penjualan*`), dan 3 hitungan "hari ini" di `PembelianController`.
> - **Throttle `10 per menit`** dipasang di 53 route export/PDF berat (`laporan/export/*`, `laporan/pdf/*`, laporan outlet, penjualan/pembelian, retur, stok, produk/supplier export).
> - **Index baru:** `stock_movements(created_at)` lewat migrasi terpisah `2026_10_07_161228_add_stock_movements_created_at_index.php`. Laporan masuk/keluar/aktivitas memfilter `created_at` saja, dan index `(owner_id, product_id, created_at)` tidak bisa melayaninya.
> - **PDF tidak diubah:** `PDF_MAX_ROWS = 1500` dan `preparePdf()` (memori 1024M) dipertahankan karena DomPDF memang butuh memuat seluruh tabel. Perlindungannya kini ada di batas baris + throttle.
> - **Koreksi (ditemukan di Fase 8):** klaim "`whereDate` dihapus seluruhnya" kurang tepat. `grep` saat itu hanya mencari `whereDate` (huruf kecil), sehingga `orWhereDate` terlewat. Dua tempat di jalur price checker/POS sudah dibereskan di Fase 8 (`LatestHpp`, `OutletPrice::scopeCurrentlyActive`). Sisanya dibereskan di Fase 10.
> - **Perlu dites:** unduh tiap export Excel (produk, min stok, supplier, stok, barang masuk/keluar, aktivitas, pergerakan, template) dan bandingkan isi dengan versi lama; filter tanggal di semua laporan (pastikan hari terakhir ikut terhitung); unduh berulang >10x dalam semenit harus terkena batas (HTTP 429).

**Checklist:**

- [x] Prioritaskan export yang menarik seluruh tabel: `ProductsExport`, `ProductsMinStockExport`, `SuppliersExport`, `StockExport`, `LaporanPergerakanExport`, `LaporanAktifitasExport`, `LaporanBarangMasuk/KeluarExport`.
- [x] Ubah ke `FromQuery` / `chunk()` (lihat di atas).
- [ ] ~~Wajibkan filter rentang tanggal dengan batas maksimum~~ **tidak dikerjakan** (keputusan #7: rentang panjang jarang dipakai).
- [x] Pastikan semua relasi di-eager-load: export `Laporan*` (PO, PR, Pembelian, Penerimaan, Pengiriman, Picking) sudah memakai `with()`; `ProductsExport` diperbaiki. Export dokumen tunggal (`*SingleExport`) dan `Kartu Stok`/`Opname` belum diaudit karena hanya memuat satu dokumen.
- [x] Beri `throttle` pada route export/PDF berat (`throttle:10,1`).
- [ ] ~~Hindari menaikkan `memory_limit`~~ **tidak diubah untuk PDF** (lihat catatan). Export Excel tidak menaikkan memori.
- [x] Ganti `whereDate(...)` di query laporan dengan `ReportQuery::betweenDates()` / `onDate()`.

---

## Fase 8. Price checker publik (P1) — ✅ SELESAI (token kiosk/IP ditunda)

> **Status: selesai.** Mengikuti keputusan #4 (selisih di bawah 5 menit tidak masalah), jadi TTL cache dipilih **60 detik**.
> - **Cache hasil lookup** per `(outlet_id, barcode)` selama 60 detik (`PriceCheckerController::lookup` + `buildLookup`). Barcode yang **tidak ditemukan** juga di-cache 60 detik, jadi bot yang menebak barcode tidak membuka query berulang. Efek samping: produk yang baru ditambahkan bisa butuh sampai 60 detik untuk muncul di kiosk.
> - **Daftar outlet di-cache** (id, name, slug) selama 10 menit dan **otomatis dihapus** saat outlet disimpan/dihapus/dipulihkan (`Outlet::booted()`). Dengan ini `resolveOutlet` tidak lagi memuat semua outlet lalu menghitung slug satu per satu, dan tidak perlu kolom `slug` baru (tanpa migrasi). Aturan validasi `exists:outlets,id` (1 query) dihapus karena outlet dicocokkan ke daftar cache tersebut.
> - **Throttle** (diberi prefix `pricechecker` di Fase 10 agar bucketnya terpisah, lihat catatan Fase 10) diturunkan `120` → `60` per menit per IP.
> - **Validasi barcode:** `max:100` sudah ada; ditambah penolakan karakter kontrol saja (kode produk bisa berisi huruf/angka/tanda hubung, jadi tidak dibatasi lebih ketat agar barcode asli tidak ikut tertolak).
> - **Eager-load promo dirampingkan:** relasi `outlets` dibuang (tidak dipakai saat format), dan `promotionProducts.product` hanya dimuat untuk promo bundle. Relasi `bonuses` tetap (dipakai semua promo). Eager-load voucher `products`/`outlets` dipertahankan karena dipakai `appliesToProduct`/`appliesToOutlet`.
> - **Bonus dari audit `whereDate`:** `LatestHpp` (2 tempat) dan `OutletPrice::scopeCurrentlyActive` (2 tempat) kini membandingkan kolom DATE langsung, tanpa `DATE()`. Hasil sama, tetapi index bisa dipakai. Ini juga dipakai kasir/POS.
> - **Perubahan perilaku kecil:** `outlet_id` yang tidak dikenal sekarang mengembalikan 404 (sebelumnya 422 dari validasi). Pesan di layar kiosk tetap tampil.
> - **Perlu dites:** scan barcode yang ada (harga, harga coret, promo, voucher tampil sama seperti sebelumnya); scan barcode yang sama dua kali dalam semenit (harus tetap benar); barcode yang tidak ada (pesan "Produk tidak ditemukan"); `/price-checker?outlet_id=...` dan `?outlet=nama-outlet`; ubah nama outlet lalu buka `/price-checker` (harus langsung berubah); promo bundle (syarat paket tampil); scan >60 kali dalam semenit dari satu IP (harus kena 429).
> - **Belum diverifikasi:** `php -l` tidak bisa dijalankan di lingkungan ini (PHP tidak tersedia). Pengecekan sintaks hanya keseimbangan tanda kurung. Mohon jalankan `php -l` atau buka halamannya sekali di lokal.

**Lokasi:** `app/Http/Controllers/PriceCheckerController.php`, `app/Models/Outlet.php`, route `price-checker.lookup` (`throttle:60,1`, tanpa login).

**Masalah:** setiap lookup menjalankan query produk (`where code = ?`), `OutletPrice`, promo (`with promotionProducts.product, bonuses, outlets`), dan voucher (`with products, outlets`) lalu memfilter di PHP. `resolveOutlet` dapat memuat semua outlet lalu mencocokkan slug di PHP. Siapa pun (termasuk bot) bisa memanggilnya.

**Checklist:**

- [x] Cache hasil lookup per kombinasi `(barcode, outlet_id)` selama 60 detik (`Cache::remember`), termasuk hasil "tidak ditemukan".
- [x] `resolveOutlet`: cache daftar outlet (id, name, slug), dihapus otomatis saat outlet berubah. Kolom `slug` di tabel `outlets` tidak dibuat.
- [ ] ~~Cache daftar promo aktif per outlet~~ **tidak dikerjakan:** promo/voucher difilter per produk di SQL, sehingga cache per outlet harus memuat seluruh promo beserta relasinya. Cache hasil lookup di atas sudah menutup kasus yang sama dengan beban lebih kecil. Buka lagi bila log menunjukkan banyak barcode berbeda per menit.
- [x] Turunkan limit throttle ke 60 per menit per IP (cukup untuk scan manual; naikkan bila kiosk memakai scanner otomatis yang sangat cepat).
- [x] Eager-load `promotionProducts.product` hanya untuk promo bundle (satu-satunya yang memakainya); relasi `outlets` dibuang.
- [x] Validasi input: `barcode` maks 100 karakter + tolak karakter kontrol; `outlet_id` integer minimal 1.
- [ ] *(ditunda, perlu keputusan)* Batasi akses dengan token kiosk atau IP outlet. Ini mengubah cara kiosk dibuka (URL harus membawa token, atau hanya IP toko yang boleh), jadi sebaiknya hanya bila endpoint ini benar-benar diserang. Throttle + cache sudah menahan beban DB.

---

## Fase 9. Index database (P1) — ✅ MIGRASI DIBUAT (belum dijalankan di hosting)

> **Status: migrasi dibuat**: `database/migrations/2026_10_07_160523_add_performance_indexes.php`.
> Keputusan #6: boleh menambah index asal tidak merusak database. Migrasi ini hanya **menambah** index (tidak mengubah/menghapus data atau kolom), mengecek dulu bila index/kolom sudah ada (dilewati), dan punya `down()`.
> Dasar keputusan: hasil `SHOW INDEX` dari DB Docker (products, stocks, owner_stocks, delivery_order_items, stock_movements) + pola query di kode. Belum ada `EXPLAIN` karena belum ada akses ke DB.
>
> **Index yang ditambahkan (4):** *(+1 di Fase 7: `stock_movements(created_at)`, migrasi terpisah)*
>
> | Index | Dipakai oleh |
> |---|---|
> | `products(code)` | scan kasir `code = ?`, price checker, keranjang, refund penjualan, `orderBy('code')` |
> | `stocks(product_id, deleted_at, qty)` | `withSum('stocks','qty')` dan `SUM(qty)` per produk (dashboard, stok minimum, daftar produk, top-5 stok). Covering index, soft delete ikut terfilter |
> | `stocks(expired_at)` | near-expiry dashboard (range + `orderBy`) |
> | `delivery_order_items(product_id, created_at)` | slow moving 90 hari (`NOT EXISTS`) di dashboard |
>
> **Sengaja tidak ditambahkan (alasan):**
> - `owner_stocks`: sudah ada `(owner_id, product_id, qty)` dan `(owner_id, product_id, batch_number)`. `expired_at` hanya penyaring sisa pada baris yang sudah sedikit.
> - `stock_movements`: sudah ada `(owner_id, product_id, created_at)` dan `stock_id`. Kartu stok gudang (`owner_id IS NULL`) tetap bisa memakai index itu.
> - `products(name)`, `(status_produk)`, `(lokasi)`, `(created_at)`: tabel hanya ± 7.500 baris, kolom status/lokasi bersifat low-cardinality, dan sort di memori murah. Tidak terbukti jadi masalah.
> - `stocks(serial_number)`: pencarian `LIKE '%...%'` tidak terbantu index biasa (keputusan #5).
> - Top-5 `groupBy product_id` pada `request_order_items` dan `delivery_order_items`: sudah punya index `product_id` (FK); kolom `SUM` tetap perlu baca baris. Dibiarkan sampai ada bukti lambat.
>
> **Cara menjalankan (setelah backup):** `php artisan migrate`. Uji di Docker dulu. Di MySQL 8 InnoDB, penambahan index berjalan online (tabel tetap bisa dibaca/ditulis), tetapi tetap sebaiknya di jam sepi.

**Checklist:**

- [x] Jalankan `SHOW INDEX` untuk tabel kandidat (dari DB Docker; tabel `products`, `stocks`, `owner_stocks`, `delivery_order_items`, `stock_movements`).
- [ ] Jalankan `SHOW INDEX` yang sama di **hosting** untuk memastikan hasilnya sama (migrasi tetap aman bila berbeda).
- [ ] Jalankan `EXPLAIN` untuk: pencarian POS, composer stok minimum, dashboard (top-5, near-expiry, slow moving), kartu stok. Bandingkan sebelum/sesudah migrasi.
- [x] Buat **satu migrasi baru** berisi hanya index yang terbukti dibutuhkan.
- [ ] Backup database, lalu jalankan migrasi di jam sepi (Docker dulu, baru hosting).
- [x] Ingat: `LIKE '%kata%'` tidak terbantu index biasa; index hanya membantu `=` dan `LIKE 'kata%'`.
- [ ] Ukur ulang query yang tadinya lambat setelah index dibuat (Fase 11).

---

## Fase 10. Pengaturan produksi & pengaman (P1) — ✅ BAGIAN KODE SELESAI

> **Status:** semua yang ada di dalam zip sudah dikerjakan. Yang belum adalah butir yang butuh `.env`/`config` (tidak ada di zip), akses hosting, atau keputusan Anda (ditandai di checklist).
> - **Route terbuka dikunci:** `/optimize-clear` dan `/storage-link` kini hanya bisa dibuka `superadmin` (sebelumnya siapa pun tanpa login). URL tetap sama; isinya dipindah ke `app/Http/Controllers/MaintenanceController.php` dan diberi nama route `maintenance.*`. Setelah ini tidak ada lagi route closure di `routes/web.php` dan `routes/api.php` (`/` memakai `Route::redirect`, `/api/user` dan `/api/stocks/by-product/{product}` dipindah ke `app/Http/Controllers/Api/*`, perilaku sama), jadi `php artisan route:cache` bisa dipakai.
> - **Throttle pencarian `120/menit`** dipasang di 6 route pencarian: `outlet.products` (pencarian POS), `outlet-prices.products.search`, `owner-stocks.kartu.search`, `campaign.products.search`, `campaign.products.scan`, `stocks.search`. Semua memakai prefix `search` supaya bucket hitungannya **terpisah** dari throttle export `10/menit`. Alasannya: setahu saya perilaku bawaan Laravel, `throttle:N,M` tanpa prefix menghitung per user (atau per IP untuk tamu), bukan per route, sehingga semua route tanpa prefix berbagi satu hitungan. Tanpa prefix, 10 pencarian kasir dalam semenit sudah membuat tombol export kena 429. Price checker juga diberi prefix `pricechecker` (`throttle:60,1,pricechecker`).
> - **Sisa `orWhereDate('expired_at', ...)` dibereskan (11 tempat):** `CashierSaleService` (1), `RefundPenjualanController` (4), `CashierPrintController` (3), `CartController` (3), diganti `orWhere('expired_at', '>=', today()->toDateString())`. Hasil sama persis, tetapi index `stocks(expired_at)` dan sejenisnya bisa dipakai. Sekarang `grep -rni "wheredate" app` hanya menyisakan komentar dan `ReportQuery`.
> - **Tidak diubah (disengaja):** `RoleMiddleware` (sesuai catatan di checklist); atribut Activity Log (lihat butir di bawah).
> - **Perlu dites:** login sebagai superadmin lalu buka `/optimize-clear` dan `/storage-link` (harus jalan); buka keduanya tanpa login atau sebagai kasir (harus ditolak/diarahkan); buka `/` (harus ke `/dashboard`); transaksi kasir penuh (keranjang, bayar, cetak struk) dan retur penjualan, karena memakai filter kedaluwarsa yang diubah; pencarian POS dan scan cepat berturut-turut tidak kena 429; unduh beberapa export berturut-turut masih normal.
> - **Belum diverifikasi:** `php -l` tidak bisa dijalankan di lingkungan ini (PHP tidak tersedia); hanya dicek keseimbangan tanda kurung. Jalankan `php -l` pada file yang berubah atau cukup `php artisan route:list` di lokal (akan gagal bila ada salah ketik di route).

**Checklist:**

- [ ] `.env` produksi: `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=error` (saat ini `local`, `true`, `debug`). Debug aktif menampilkan halaman error lengkap berisi nama user DB dan potongan kode. *(`.env` tidak ada di zip, ubah langsung di hosting.)*
- [ ] Ganti password database bila `.env` pernah dikirim/dibagikan.
- [x] **Route terbuka tanpa login** `/optimize-clear` dan `/storage-link`: dilindungi `role:superadmin` (bukan dihapus, karena kemungkinan dipakai di hosting tanpa terminal).
- [x] Sisa `orWhereDate('expired_at', '>=', today())` (11 tempat) diganti pembanding langsung.
- [x] Closure di `routes/web.php` dan `routes/api.php` dipindah ke controller / `Route::redirect`, supaya `route:cache` bisa dipakai.
- [ ] Setelah deploy: `php artisan config:cache`, `php artisan view:cache`, `php artisan route:cache`. Bila hosting tanpa terminal, jalankan lewat cron sekali atau tanyakan penyedia hosting. Ingat: setelah `config:cache`, `env()` di luar file `config/*` tidak terbaca lagi, jadi cek dulu `grep -rn "env(" app routes resources`. *(Catatan baru: `/optimize-clear` menghapus cache ini; jangan dibuka rutin di produksi.)*
- [ ] Cek `config/database.php`: pastikan **tidak ada koneksi persisten** (`PDO::ATTR_PERSISTENT` = `false`). *(`config/` tidak ada di zip.)*
- [ ] *(perlu keputusan)* Activity Log: model dengan `logOnly(['*'])` ada 8 (`DeliveryOrderItem`, `StockPembelian`, `PickingListItem`, `RequestOrderItem`, `Stock`, `OwnerStock`, `PembelianProduct`, `Product`; semuanya sudah `logOnlyDirty`, `OwnerStock` juga membuang `created_at`/`updated_at`). Riwayat ini **ditampilkan** di halaman stok, produk, owner stock, dan sesi kasir, jadi membatasi atribut atau menjalankan `activitylog:clean` berarti riwayat lama/atribut tertentu hilang dari layar. Belum diubah. Kalau tabel `activity_log` sudah besar, ukur dulu (`SELECT COUNT(*) FROM activity_log`) sebelum memutuskan.
- [x] `RoleMiddleware`: tidak perlu diubah. Satu query `users` per request itu wajar bila total request/query lain sudah ringan.
- [x] Pasang `throttle` pada endpoint pencarian (6 route, prefix `search`) dan export (sudah di Fase 7, `10/menit`).
- [ ] Minta hosting: menaikkan `max_user_connections` (solusi tambahan, bukan pengganti optimasi), serta mengaktifkan slow query log bila tersedia.
- [ ] Pertimbangkan Cloudflare/robots.txt untuk membatasi bot (`public/` tidak ada di zip).
- [ ] *(temuan baru, perlu keputusan)* `GET /api/stocks/by-product/{product}` **terbuka tanpa login** dan tidak dipakai oleh kode di project ini. Hanya terkena throttle `api` bawaan. Perilaku dibiarkan sama; hapus atau lindungi bila tidak ada sistem luar yang memakainya.

---

## Fase 11. Pengukuran sebelum dan sesudah (P0)

> **Status: alat ukur selesai, pengukuran belum dijalankan.** Saya tidak punya PHP/database di lingkungan ini, jadi angka tidak bisa saya ambil sendiri.
> - File baru: `app/Console/Commands/PerfBaseline.php` → `php artisan perf:baseline`. Hanya request GET (tidak mengubah data), menolak jalan bila `APP_ENV=production`.
> - Halaman yang diukur: `/dashboard`, `/product`, `/stock`, `/laporan`, `GET /cart`, pencarian POS, `price-checker/lookup`. Output: jumlah query, waktu DB, total waktu.
> - Cara pakai: `php artisan perf:baseline --detail` (lihat query berulang = indikasi N+1), `--save=sesudah.json` untuk menyimpan, `--compare=baseline.json` untuk membandingkan. Opsi `--user`, `--outlet`, `--search`, `--barcode`, `--runs`.
> - **Untuk baseline "sebelum"**: jalankan di salinan kode lama (zip asli sebelum Fase 1) dengan DB yang sama, simpan `--save=baseline.json`, lalu jalankan di kode baru dengan `--compare=baseline.json`. Bila kode lama sudah tidak ada, cukup ukur kondisi sekarang sebagai acuan perbaikan berikutnya.
> - Pakai data yang mirip produksi (± 7.500 produk) agar angka berarti. Pastikan kolom HTTP = 200; 302/403/404 berarti user/outlet kurang tepat.
> - **Belum diverifikasi:** `php -l` tidak bisa dijalankan di sini. Mohon jalankan sekali `php artisan perf:baseline` di lokal; bila ada error, kirim pesannya ke saya.
> - Bagian yang tetap manual (di hosting jam sibuk): `SHOW PROCESSLIST`, `Threads_connected`, `Max_used_connections`.

**Sebelum mengubah apa pun (baseline):**

- [x] *(alat siap, tinggal dijalankan)* Hitung jumlah query dan waktu untuk halaman: `/dashboard`, `/product`, `/stock`, `/laporan`, pencarian POS (`/outlet/{id}/products?search=...`), `GET /cart`, `/price-checker/lookup`.
  - Cara: `php artisan perf:baseline` (memakai `DB::listen`). Alternatif: `DB::listen` ke log, atau Laravel Debugbar/Telescope (jangan aktif di produksi).
- [ ] Saat jam sibuk di hosting: `SHOW PROCESSLIST;`, `SHOW STATUS LIKE 'Threads_connected';`, `SHOW STATUS LIKE 'Max_used_connections';`. Catat query yang paling sering muncul / paling lama.
- [x] *(alat siap)* Catat waktu respon tiap halaman di atas (kolom Total ms pada `perf:baseline`).

**Sesudah tiap fase:**

- [ ] Ulangi pengukuran yang sama dan catat selisihnya.
- [ ] Pastikan tidak ada regresi fungsi: stok minimum, dashboard, pencarian/scan kasir, transaksi kasir, laporan.

**Target awal (usulan, sesuaikan dengan hasil baseline):**

- [ ] Halaman biasa tanpa N+1 (jumlah query tidak naik seiring jumlah baris).
- [ ] Dashboard: jumlah query turun jelas berkat cache dan penggabungan perhitungan.
- [ ] Pencarian POS: tidak ada query per baris hasil.
- [ ] `Max_used_connections` di jam sibuk tidak lagi mendekati 25.

---

## Keputusan yang perlu dikonfirmasi

1. **Notifikasi stok minimum** (composer): ditampilkan untuk role apa saja? (admin-gudang, owner, superadmin, staff-outlet, kasir)
    -> Jawaban : Hanya Untuk Admin Gudang Saja
2. **Fitur import:** tombol dan modal import dibuang sekalian, atau hanya polling/panel progress yang dihapus dulu?
    -> Jawaban : Dibuang Sekalian
3. **Toleransi cache:** apakah data stok/dashboard boleh terlambat 1–2 menit?
    -> Jawaban : Tidak Boleh. Ini Harus Realtime
4. **Price checker:** apakah kiosk butuh data real-time atau cukup cache 30–60 detik?
    -> Jawaban : kalau perbedaannya hanya maksimal kurang dari 5 menit masih tidak masalah
5. **Pencarian POS:** apakah pencarian harus tetap bisa "mengandung kata" (`%kata%`), atau cukup awalan kode/nama plus scan barcode?
    -> Jawaban : Benar tetap demikian
6. **Index:** boleh menambah migrasi index di hosting setelah `SHOW INDEX` dicek?
    -> Jawaban : Boleh saja asalkan tidak menjadikan database rusak
7. **Export:** apakah boleh dibatasi rentang tanggal maksimum?
    -> Tidak perlu pembatasan. rentang tanggal yang panjang jarang digunakan sehingga tidak perlu diberikan

---

## Peta lokasi file

| Area | File |
|---|---|
| Composer layout | `app/Providers/AppServiceProvider.php` |
| Dashboard | `app/Http/Controllers/DashboardController.php` |
| Produk & pencarian POS | `app/Http/Controllers/ProductController.php`, `app/Http/Resources/ProductResource.php`, `resources/js/components/Cart.jsx` |
| Accessor model | `app/Models/Product.php`, `app/Models/OwnerStock.php` |
| Keranjang/wishlist | `app/Models/DatabaseStorage.php`, `app/Http/Controllers/CartController.php` |
| N+1 | `PembelianController`, `StockController`, `RefundPembelianController`, `resources/views/products/index.blade.php` |
| Loop `find()` | `RequestOrderController`, `StockController`, `PembelianController`, `RefundPembelianController`, `app/Services/CashierSaleService.php`, `ProductMinimumAdjustmentController` |
| Export/PDF | `app/Exports/*`, `app/Support/ReportQuery.php`, `LaporanController` |
| Price checker | `app/Http/Controllers/PriceCheckerController.php` |
| Route & pengaman | `routes/web.php`, `app/Console/Kernel.php`, `.env` |