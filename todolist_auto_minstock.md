# Todolist: Min Stok Otomatis (3 Bulan) → Auto Qty Cek Barang di PO

Status: **v7. Tahap A, B, C, D selesai dikerjakan. Belum diuji di database (lihat A6, B4, dan Tahap E); itu sisa pekerjaan.** Centang `[x]` setelah selesai.

## Tujuan (sesuai arahan)

| Sumber | Peran |
|---|---|
| `OutletLaporanService::minimumStock()` | **Sumber utama** min stok bila produk sudah punya 3 bulan data penjualan |
| `products.min_stock` | **Default** selama produk belum cukup 3 bulan data penjualan |
| `product_minimum_adjustments` | **Tambahan** di atas hasil hitung, untuk event/periode tertentu |

Aturan min stok **khusus gudang** (tidak berlaku di outlet). Penjualan outlet hanya menjadi bahan hitung.

```
min_dasar  = produk eligible 3 bulan ? hasil hitung 3 bulan (lihat K1) : products.min_stock
min_final  = adjustment aktif ? CEIL(min_dasar × (1 + persen/100)) : min_dasar
status     = stok_gudang < min_final  → OUT OF STOCK
qty_auto   = min_final − stok_gudang   (hanya bila out of stock, selain itu 0)
```

Contoh: min_final 10, stok 8 → OUT OF STOCK, qty otomatis **2**.

## Kondisi sekarang (hasil analisa)

- Form PO (`pembelians/create` & `edit`, modal Cek Barang) membaca `effective_min` dari `PembelianController::getAllProducts()` = `min_stock` manual + adjustment. **Tidak** memakai hasil 3 bulan.
- `minimumStock()` hanya dipakai laporan Minimal Stock (Excel & PDF).
- `minimumStock()` menghitung per **outlet × produk** dan memuat **seluruh riwayat penjualan** ke memori. Terlalu berat untuk dipanggil realtime di form PO.
- Pengembalian barang tanpa `penjualan_item_id` dialokasikan ke invoice terbaru lewat loop PHP (`returnedQuantities()`), sehingga tidak bisa diterjemahkan ke SQL biasa dengan hasil persis sama.

## Keputusan

- [x] **K1. Min stok untuk gudang.** Jawaban: aturan min stok tidak berlaku di outlet, khusus gudang.
  - [x] **K1b. Belum dijawab, dipakai default Opsi 1 (usulan) agar Tahap A bisa jalan; bisa diganti.** Cara menggabungkan penjualan semua outlet menjadi satu angka gudang per produk:
    - **Opsi 1 (usulan):** hitung per outlet dengan rumus yang sekarang (rata-rata × faktor frekuensi PO outlet tsb), lalu **jumlahkan** semua outlet. Eligible bila penjualan pertama produk di outlet mana pun sudah ≤ awal jendela.
    - **Opsi 2:** gabungkan dulu: total terjual semua outlet ÷ 3, lalu satu faktor frekuensi dari total PO semua outlet. Hasilnya cenderung lebih kecil (frekuensi PO gabungan hampir selalu 4 → faktor 0,625).
- [x] **K2. Adjustment.** Jawaban: sesuai usulan, berlaku ke min dasar (hasil 3 bulan maupun default manual).
- [x] **K3. Cara menyediakan angka.** Jawaban: **(a) realtime**, tanpa command/snapshot, query seringan mungkin. Tabel snapshot dan command terjadwal **tidak dibuat**.
- [x] **K4. Cakupan.** Jawaban: ikut yang terbaik. Keputusan: PO + laporan Minimal Stock dulu (Tahap A–C), lalu Tahap D setelah performa terukur aman (lonceng ada di setiap halaman, jadi perlu hati-hati).
- [x] **K5. Pembanding status.** Jawaban: samakan. Dipakai `stok < min` (sesuai contoh 8 < 10; bila stok = min, qty otomatis 0 sehingga tidak perlu ditandai out of stock). Lonceng/dashboard yang sekarang `<=` ikut berubah di Tahap D.
- [ ] **K6. Laporan Minimal Stock.** Karena min stok khusus gudang, laporan berubah dari baris per outlet×produk menjadi **per produk (gudang)**. Filter outlet pada laporan ini tidak dipakai. Mohon dicek bila tidak setuju.
- [x] **K7. Pengembalian tanpa `penjualan_item_id`.** Usulan: tetap pakai alokasi yang ada, tetapi hanya untuk produk yang diminta dan yang punya retur tak tertaut, agar hasil tetap sama dengan laporan lama.

## Tahap A. Service perhitungan yang bisa dipakai ulang

- [x] A1. Buat service baru (mis. `MinimumStockCalculator`) dengan input daftar produk dan tanggal acuan; output per produk: `min_dasar`, `sumber` (auto/manual), `rata2_terjual`, `min_final`, `adjustment_pct`.
- [x] A2. Ganti pengambilan penjualan menjadi agregasi SQL (`SUM(qty)` per outlet×produk di jendela, `MIN(created_at)` penjualan pertama), hanya untuk produk yang diminta, tanpa `->with('items')->get()` seluruh riwayat.
- [x] A3. Pertahankan aturan yang ada: jendela 3 bulan penuh sebelum bulan berjalan, hanya status paid/null, retur dikurangi, replacement ditambah, faktor frekuensi 2,5 / 1,875 / 1,25 / 0,625 (menurut K1b).
- [x] A4. Cek index yang dibutuhkan (penjualans: outlet_id+created_at+status; penjualan_items: product_id/penjualan_id; refund items). Tambah index lewat migration bila perlu.
- [x] A5. `minimumStock()` (laporan) memanggil service baru, sehingga laporan dan PO memakai **satu sumber angka**.
- [ ] A6. Verifikasi: **sebelum** mengganti file, download Excel Minimal Stock (Laporan Toko, tanpa filter outlet); **sesudah** mengganti file dan migrate, download lagi dengan filter yang sama, lalu bandingkan: semua kolom harus identik. (Sudah diuji: fungsi rumus `rule()` vs rumus lama pada 200.000 kombinasi acak, 0 beda. Query SQL belum bisa diuji tanpa database.)

## Tahap B. Integrasi adjustment

- [x] B1. Terapkan adjustment aktif (terbaru menurut `active_from desc, id desc`) di atas `min_dasar` sesuai K2. (Logika sudah ada di `forWarehouse()` sejak Tahap A; kini dipakai laporan.)
- [x] B2. Laporan Minimal Stock (Excel/PDF) mengikuti K6: per produk, tambah kolom **Adjustment %** dan **Min Stok Final**.
- [x] B3. Catatan sumber: `auto (3 bulan)` / `manual (belum 3 bulan)` + penanda bila ada adjustment (mis. `auto (3 bulan) + adj 20%`).
- [ ] B4. Verifikasi (oleh Anda): download Excel/PDF Minimal Stock; cek baris per produk, kolom Min Dasar/Adjustment %/Min Final, status Out of Stock (stok < min final), dan saran qty = min final - stok. Catatan: karena bentuk laporan berubah ke per produk, perbandingan identik dengan laporan lama (A6) tidak lagi bisa dilakukan setelah file Tahap B dipasang; bila A6 mau dilakukan, download Excel lama **sebelum** memasang file Tahap B.

## Tahap C. Sambungkan ke PO (Cek Barang)

- [x] C1. `PembelianController::getAllProducts()`: `effective_min` diambil dari `min_final` (`MinimumStockCalculator::forWarehouse()`), ditambah field `min_source` dan `min_adjustment_pct`. Satu kali agregasi untuk seluruh produk yang tampil (dibatasi ke produk supplier bila ada filter supplier), tanpa query per produk.
- [x] C2. `pembelians/create.blade.php` dan `pembelians/edit.blade.php`: `is_under_minimum` dari server kini `stok < min_final`; `suggestedQty` (sudah `min - stok`) hanya terisi untuk baris OUT OF STOCK, selain itu 0. Tampilan Min Stok memakai `??` (bukan `||`) supaya min final 0 tidak jatuh balik ke min manual.
- [x] C3. Modal Cek Barang: kolom Min Stok diberi badge `auto` / `manual` / `+adj X%`.
- [x] C4. Satuan konversi: qty otomatis tetap dalam satuan kecil seperti sebelumnya; tampilan stok dan min tetap lewat `fmtQtyK` (tidak diubah).
- [x] C5. Tidak ada query per produk di loop dan tidak ada koneksi database baru. Cache pendek **belum** ditambahkan; tunggu hasil ukur E8.

## Tahap D. Penyamaan di tempat lain (setelah Tahap C lolos uji performa)

- [x] D1. `LowStockService` (lonceng dan widget dashboard) memakai `min_final` dan `stok < min`. Peta min stok di-cache 10 menit (stok tetap realtime); cache dibuang saat adjustment disimpan.
- [x] D2. Halaman Produk (kolom Stock Minimum, notif habis/aman) dan modal "Pengaturan Min Stok" menampilkan angka final + sumbernya (`auto` / `manual` / `+adj`). Widget dashboard juga menampilkan sumber.
- [x] D3. Laporan pergerakan stok (PDF dan Excel) dan laporan Stok Barang (PDF dan Excel) memakai `min_final` dari `MinimumStockCalculator::forWarehouse()`. Rumus `qty_reorder = min × 2 − stok` dan pembanding status laporan-laporan itu (`stok <= min` untuk reorder, `stok > min` untuk Aman) **tidak diubah**, ditinjau terpisah (lihat catatan Tahap D3).
- [x] D4. Label form produk: `min_stock` menjadi **"Min Stok Default (sebelum ada 3 bulan data penjualan)"** (create dan edit).

## Tahap E. Pengujian

- [ ] E1. Produk eligible: min auto 10, stok 8 → OUT OF STOCK, qty otomatis 2.
- [ ] E2. Produk belum 3 bulan: memakai `products.min_stock`, penanda "manual".
- [ ] E3. Adjustment aktif (+20%): min naik sesuai rumus; setelah `active_until` lewat kembali ke angka dasar.
- [ ] E4. Adjustment tumpang tindih / di masa depan: yang dipakai benar.
- [ ] E5. Stok ≥ min: qty otomatis 0, status Normal.
- [ ] E6. Produk dijual di lebih dari satu outlet: angka gudang sesuai K1b.
- [ ] E7. Pergantian bulan: jendela bergeser dan hasil ikut berubah.
- [ ] E8. Waktu muat form PO dengan data besar (ribuan produk) tidak lebih lambat dibanding sekarang.
- [ ] E9. Retur tertaut dan tak tertaut: hasil sama dengan perhitungan lama (K7).

## Catatan pengerjaan Tahap A

- File baru: `app/Services/MinimumStockCalculator.php`. Isinya: `window()`, `rule()`, `isEligible()`, `salesAggregates()` (SQL agregat), `poCounts()`, `returnedQuantities()` (dipindah dari laporan, bisa dibatasi per produk, K7), dan `forWarehouse()` (min per produk untuk gudang, K1b Opsi 1 + adjustment, dipakai di Tahap C).
- `OutletLaporanService::minimumStock()` sekarang memanggil service itu; bentuk output (kolom/baris per outlet×produk) **tidak diubah** supaya bisa dibandingkan (A6). Perubahan ke per produk dikerjakan di Tahap B2.
- Index: migration `penjualans.created_at` (A4). Index lain (`penjualan_items.product_id`, `penjualan_id`) sudah ada lewat foreign key.
- Penjualan dengan `outlet_id` kosong tidak dihitung (di kode lama pun tidak pernah menjadi baris laporan).
- Retur tak tertaut (K7) masih memuat riwayat item untuk produk yang bersangkutan saja; ringan bila jarang.

## Catatan pengerjaan Tahap B

- `OutletLaporanService::minimumStock()` ditulis ulang: satu baris per **produk (gudang)** dari `MinimumStockCalculator::forWarehouse()`. Filter outlet tidak dipakai (K6); dropdown Outlet pada modal laporan dihilangkan.
- Produk yang tampil: ada di stok outlet, atau terjual di jendela 3 bulan, atau min final > 0. Diurutkan menurut nama produk.
- `forWarehouse()` kini juga mengembalikan `first_sale` (penjualan pertama paling awal di semua outlet).
- Kolom lama per outlet (Jumlah PO, PO/Bulan, Faktor) dihapus dari laporan karena bersifat per outlet; rumusnya tetap dipakai di dalam perhitungan. Kolom baru: Min Stock Dasar, Adjustment %, Min Stock Final, Sumber Min Stock.
- File berubah: `app/Services/MinimumStockCalculator.php`, `app/Services/OutletLaporanService.php`, `app/Exports/OutletMinimalStockExport.php`, `resources/views/exports/pdf/outlet/minimal-stock.blade.php`, `resources/views/laporan/outlet.blade.php`, `resources/views/laporan/index.blade.php`.
- Belum dijalankan: PHP tidak tersedia di lingkungan kerja ini, jadi syntax dan hasil query belum bisa dites (sama seperti Tahap A).

## Catatan pengerjaan Tahap C

- File berubah: `app/Http/Controllers/PembelianController.php`, `resources/views/pembelians/create.blade.php`, `resources/views/pembelians/edit.blade.php`.
- `getAllProducts()` tidak lagi memakai `withEffectiveMin()` (scope itu tetap ada karena masih dipakai `ProductController`, diganti di Tahap D).
- Tanpa filter supplier, kalkulator dijalankan untuk semua produk (`null`); dengan filter supplier hanya untuk produk supplier itu.
- Belum dijalankan: PHP tidak tersedia di lingkungan kerja ini, jadi syntax dan query belum dites. Ukur waktu muat form PO (E8) sebelum lanjut ke Tahap D.

## Catatan pengerjaan Tahap D (D1, D2, D4)

- File berubah: `app/Services/LowStockService.php` (ditulis ulang; `baseQuery()` dan `lowStockQuery()` dihapus karena tidak dipakai di tempat lain), `app/Providers/AppServiceProvider.php` (hanya komentar), `app/Http/Controllers/ProductMinimumAdjustmentController.php`, `app/Http/Controllers/ProductController.php`, `resources/views/dashboard/index.blade.php`, `resources/views/products/index.blade.php`, `resources/views/products/create.blade.php`, `resources/views/products/edit.blade.php`.
- Perubahan perilaku: kandidat lonceng kini produk dengan min final > 0 (sebelumnya `min_stock > 0` atau ada adjustment), dan status memakai `stok < min` (sebelumnya `<=`, sesuai K5). Widget dashboard sebelumnya hanya produk dengan `min_stock > 0` manual; kini semua produk dengan min final > 0.
- Lonceng bisa tertinggal sampai 10 menit terhadap perubahan min stok (mis. ganti `min_stock` produk atau pergantian bulan); stok berubah realtime.
- Scope `withEffectiveMin()` di model `Product` tetap ada (masih dipakai `isLowStock()` dan accessor).
- Belum dijalankan: PHP tidak tersedia di lingkungan kerja ini.

## Catatan pengerjaan Tahap D3

- File berubah: `app/Http/Controllers/LaporanController.php` (`pdfPergerakan`, `pdfStok`), `app/Exports/LaporanPergerakanExport.php`, `app/Exports/StockExport.php`, `resources/views/exports/pdf/laporan-stok.blade.php`.
- Tiap laporan memanggil `forWarehouse()` sekali untuk semua produk, bukan per baris.
- Yang sengaja tidak diubah: laporan ini masih memakai `stok <= min` untuk Saran Reorder dan `stok > min` untuk status Aman, sehingga stok = min tampil Kritis dan Saran Reorder Ya, berbeda dari aturan PO/lonceng (`stok < min`, K5). Perlu keputusan Anda bila mau disamakan (K8 di bawah).
- Keputusan baru: [ ] **K8.** Samakan pembanding laporan Pergerakan dan Stok Barang dengan K5 (`stok < min`)? Dan rumus `qty_reorder = min × 2 − stok` tetap atau diganti `min − stok` seperti qty otomatis PO?

## Urutan kerja

A, B, C, D (selesai dikerjakan) → uji A6/B4/E oleh Anda → jawab K6/K8 bila perlu. Tiap tahap dikirim sebagai file yang berubah saja (bukan zip, bukan ditempel di chat), dan todolist ini diupdate setiap ada perubahan.