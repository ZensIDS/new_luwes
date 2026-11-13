# TODOLIST: Revisi Campaign, Cetak Voucher/Label, Set Stock, Laporan Kassa, Filter Master Harga

Status: **PENGERJAAN** - Tahap A-D selesai, Tahap E selesai (menunggu cek manual). Berikutnya: Tahap F.

Cara kerja:
- Dikerjakan bertahap, satu tahap sekali jalan.
- Tiap tahap hanya mengirim file yang berubah (tanpa zip, path sesuai struktur project) + todolist yang sudah diupdate.
- Setelah tahap selesai, tunggu konfirmasi sebelum lanjut ke tahap berikutnya.
- Legenda: `[x]` selesai, `[ ]` belum, `[~]` sedang dikerjakan / menunggu cek.

## PROGRESS

| Tahap | Isi                                                          | Risiko             | Status |
|-------|--------------------------------------------------------------|--------------------|--------|
| A     | Urutan cetak label harga: yang berpromo dulu                 | Kecil, 1 file      | [x]    |
| B     | Cetak voucher: samakan dengan label harga (A4 68mm, mode)    | Sedang, 1-2 file   | [x]    |
| C     | Campaign: modal pilih produk + "Isi per bundle" (Bundle saja)| Sedang, 2-3 file   | [x]    |
| D     | Export Excel Set Stock (sudah / belum di-set)                | Sedang, ~4 file    | [x]    |
| E     | Laporan penjualan kassa harian                               | Terbesar, 5-6 file | [x]    |
| F     | Master Harga Jual: filter produk dengan / tanpa diskon       | Kecil, 2 file      | [ ]    |

Urutan: A -> B -> C -> D -> E -> F (yang kecil dulu, yang terbesar terakhir; F bisa dimajukan bila diminta).

---

## TAHAP A - Urutan cetak label harga  [x]
Halaman: cashier.print.products | File: app/Http/Controllers/CashierPrintController.php

Keputusan: "Promo" = produk yang di label tampil harga coret + persen diskon (diskon Master Harga Jual). Rafaksi / Flash Sale tidak dipakai.

- [x] A1. Setelah harga dihitung, urutkan: print_has_discount dulu, baru yang tidak; di dalam grup tetap A-Z nama.
- [x] A2. Berlaku untuk semua ukuran kertas dan mode; qty salinan per produk tidak berubah.
- [ ] A3. Cek manual oleh user: cetak campuran promo / non-promo, label berpromo harus paling depan.

---

## TAHAP B - Cetak voucher mengikuti label harga  [x]
Halaman: cashier.print.vouchers | File: resources/views/cashier/print-vouchers.blade.php, resources/views/vouchers/index.blade.php (controller tidak diubah, mode dibaca dari request di blade)

Keputusan: mode "Warna" voucher memakai aksen yang sama dengan label harga (pink).

Temuan: label harga sudah mendukung thermal 58/68/80, A4 (label 68 mm, 3 kolom, paper=100), mode Thermal/Warna, tinggi label maks 35 mm untuk 68 mm. Cetak voucher baru punya 58/68/80, tanpa A4 dan tanpa mode.

- [x] B1. Tambah opsi kertas "A4 (label 68 mm)" di halaman cetak voucher.
- [x] B2. Tambah "Mode cetak" (Thermal / Warna, aksen pink sama dengan label harga).
- [x] B3. Samakan ukuran, aturan tinggi 35 mm di 68 mm, @page, dan grid 3 kolom A4 dengan label harga.
- [x] B4. Aturan CSS disalin ke blade voucher (CSS label harga tidak disentuh, supaya label harga aman).
- [x] B5. Pilihan kertas + mode juga ditambahkan di halaman /voucher (tombol "Cetak voucher terpilih"), karena cetak dari sana langsung print=1 tanpa melewati toolbar print-vouchers.
- [ ] B6. Cek manual oleh user: dari /voucher, semua ukuran kertas + kedua mode.

---

## TAHAP C - Campaign: modal pilih produk + qty bundle  [x]
Halaman: /campaign/create dan edit | File: resources/views/campaigns/form.blade.php, app/Http/Controllers/CampaignController.php

Keputusan:
- Kolom "Qty untuk 1 promo" (required_qty) hanya tampil untuk Bundle, label "Isi per bundle". Flash Sale / Rafaksi / Voucher: disembunyikan (tidak dipakai).
- Pemilihan barang tetap lewat modal cek barang (sama untuk semua tipe).

Temuan: sekarang select2 AJAX (min 2 huruf) + scan barcode + tabel produk terpilih. Modal harus paging + cari dari server (produk bisa ribuan, tidak memuat semua sekaligus).

- [x] C1. Tombol "Pilih Produk" membuka modal: kolom cari, tabel centang, paging server (perluas endpoint campaign.products.search).
- [x] C2. Pilihan bertahan saat pindah halaman / ganti pencarian; penghitung "N dipilih"; produk yang sudah dipilih langsung tercentang.
- [x] C3. Tombol "Tambahkan" memasukkan ke tabel produk di bawah; scan barcode tetap ada.
- [x] C4. Mode edit: produk lama tetap tampil dan qty-nya tidak hilang.
- [x] C5. Kolom "Isi per bundle" hanya muncul untuk tipe Bundle (berganti otomatis saat tipe diubah).
- [x] C5b. Modal (dan scan barcode) hanya menampilkan produk yang harga jualnya sudah diatur (aturan harga aktif di Master Harga Jual, minimal 1 outlet). Produk lama di mode edit tetap tampil.
- [ ] C6. Cek manual oleh user: buat + edit campaign Bundle dan Flash Sale.

---

## TAHAP D - Export Excel Set Stock  [x]
Halaman: /set-stock?outlet_id=2 | File: export class baru, route, SetStockController, view Set Stock (sekitar 4 file)

Keputusan:
- "Belum di-set" = belum ada di rentang tanggal yang dipilih.
- Item yang masih di keranjang (draft active, belum disimpan) dihitung "sudah", status "Di keranjang (belum disimpan)".
- Qty untuk yang belum di-set ditulis "-".

Temuan: data di set_stock_draft_items (barcode, qty, user, waktu), tidak dihapus setelah disimpan (done / active). Filter tanggal memakai waktu item (WIB), default hari ini; produk di banyak draft diambil yang terbaru. "Belum" = produk punya harga jual aktif di outlet (outlet_prices) dan tidak ada di draft pada rentang itu. Styling: BaseLaporanTokoExport + SheetWriter (seperti Daftar Barang Outlet). Akses: superadmin dan staff-outlet (OutletAccess). Bila outlet:reset-data dijalankan, draft ikut terhapus.

- [x] D1. Tombol "Export Excel" di halaman Set Stock membuka modal: jenis (Sudah / Belum / Cetak semua) + rentang tanggal (default hari ini).
- [x] D2. Export class baru + route + method di SetStockController (akses mengikuti OutletAccess).
- [x] D3. Kolom: Barcode, Nama Barang, Kategori, Qty, Status.
- [x] D4. "Cetak semua": yang sudah di atas, yang belum di bawah; status "Sudah" / "Belum di-set" / "Di keranjang (belum disimpan)".
- [x] D5. Hanya produk yang harga jualnya sudah diatur; qty belum di-set = "-".
- [ ] D6. Cek manual oleh user: ketiga jenis export + rentang tanggal.

---

## TAHAP E - Laporan penjualan kassa harian  [x]
File: Export baru, LaporanController, routes/web.php, laporan/outlet.blade.php, laporan/partials/_modal.blade.php (+ _script), OutletLaporanService / CashierSession bila perlu.

Keputusan:
- Kassa = nama kasir pembuka sesi, plus daftar shift di kassa itu.
- Tambah kolom: saldo awal, bon, setoran tutup kasir, total setoran (bon + setoran tutup kasir).
- Sesi belum ditutup ikut tampil dengan tanda "BELUM TUTUP".
- Kresek tanpa kolom sendiri (sudah masuk penjualan, ikut total). Voucher dan cashback ikut di total.

Temuan: data sudah ada di cashier_sessions, penjualans, cashier_drawer_entries, dan CashierSession::breakdownFor() (dipakai Tutup Kasir). Penjualan yang sudah dihapus otomatis tidak terhitung.

Keputusan implementasi (bisa diubah bila tidak sesuai):
- Sesi dipilih berdasarkan tanggal buka (opened_at). Sesi yang dibuka hari sebelumnya dan masih terbuka tidak ikut tampil di tanggal ini.
- Angka penjualan memakai CashierSession::breakdownFor() (sama dengan Tutup Kasir / History Kasir), jadi retur penjualan tidak dikurangkan di laporan ini.
- Sesi BELUM TUTUP: Uang Seharusnya dihitung berjalan (tunai + kas masuk - bon); Setoran Tutup Kasir, Uang Disisakan, Uang Fisik, Selisih diisi "-".
- Kolom Kassa = nama pembuka sesi (shift pertama); Akun Kassa = akun login; kolom Shift = semua shift beserta jamnya.
- Route dibatasi role superadmin | owner | staff-outlet (memuat angka kas); kartu disembunyikan untuk role lain.

- [x] E1. Kartu baru "Penjualan Kassa" di Laporan Outlet; modal laporan mode "satu tanggal" + outlet.
- [x] E2. Satu baris per sesi kassa, kolom: Nama Kassa (akun), Nama Kasir (looping semua shift), Saldo Awal, Penjualan Tunai, Penjualan per metode bayar (kolom dinamis), Bon, Setoran (setoran tutup kasir), Setoran Total (Bon + Setoran). Tanpa kolom lain (revisi dari rancangan awal).
- [x] E3. Tanda "BELUM TUTUP" untuk sesi yang belum ditutup.
- [x] E4. Baris total di bawah; styling mengikuti export Laporan Toko lain.
- [x] E5. Pastikan penjualan terhapus tidak terhitung.
- [ ] E6. Cek manual oleh user: satu hari dengan beberapa sesi + satu sesi belum tutup.

---

## TAHAP F - Master Harga Jual: filter diskon / tanpa diskon  [ ]
Halaman: outlet-prices.index | File: app/Http/Controllers/OutletPriceController.php, resources/views/outlet-prices/index.blade.php

Temuan: daftar produk (pricePage) dipakai oleh folder, "Semua Barang", "Terakhir Ditambahkan", dan hasil pencarian. Diskon tersimpan di outlet_prices: disc_brand_value (Disc Reg), disc_tambahan_value + disc_tambahan_2/3/4_value (Disc 1-4), disc_toko_value. Filter bisa langsung di query (tanpa hitung per baris), jadi ringan.

Rencana (usulan, mengikuti definisi "promo" label di Tahap A):
- Dengan diskon = salah satu dari Disc Reg / Disc 1-4 / Disc Toko bernilai > 0.
- Tanpa diskon = semua kolom diskon itu 0/kosong.

- [ ] F1. Parameter filter baru (mis. `promo` = semua / diskon / tanpa), divalidasi; berlaku di pricePage untuk semua mode daftar produk (folder, semua, terakhir, pencarian).
- [ ] F2. Dropdown "Diskon: Semua / Dengan diskon / Tanpa diskon" di bar atas daftar produk, tampil aktif bila dipilih, ada tombol reset.
- [ ] F3. Filter ikut terbawa saat pindah halaman (paging) dan saat redirect setelah simpan / edit / hapus (LAST_VIEW_SESSION dan indexParams).
- [ ] F4. Jumlah hasil / pagination mengikuti filter; kartu folder tidak berubah (hitungan folder tetap total).
- [ ] F5. Cek manual oleh user: filter di dalam folder, di Semua Barang, dan saat pencarian.

Pertanyaan untuk F (bila tidak dijawab, dipakai usulan di kurung):
- F-a. Filter tampil juga di layar kartu folder (pilih filter -> langsung tampil semua produk terfilter), atau cukup di dalam daftar produk? (usulan: cukup di dalam daftar produk)
- F-b. Diskon nominal kecil yang jika dipersenkan di bawah 1% (tidak tampil di label) tetap dihitung "dengan diskon"? (usulan: ya, dihitung dengan diskon)
- F-c. Perlu dipertimbangkan aturan harga yang sudah kedaluwarsa / belum aktif? (usulan: tidak, filter hanya dari nilai diskon yang tersimpan)

---

## LOG PENGERJAAN

- Analisis selesai, semua pertanyaan Tahap A-E sudah dijawab.
- Tahap A selesai: CashierPrintController.php, produk diurutkan print_has_discount dulu lalu A-Z (strcasecmp). Berlaku semua kertas/mode, qty salinan tidak berubah, view tidak disentuh. Menunggu cek manual (A3).
- Todolist diubah ke format checklist per tahap dan Tahap F (filter Master Harga) ditambahkan. Belum ada kode F yang diubah.
- Tahap B selesai: print-vouchers.blade.php. Tambah kertas A4 (label 68 mm, grid 3 kolom, garis titik-titik, @page A4 margin 3mm), pilihan Mode cetak Thermal/Warna (pink #e0577a + latar #fbe6ea, strip berwarna), label 68 mm tingginya dikunci 34,5 mm (barcode 7,5 mm, baris info maks 2 baris). Kertas 58/80 dan tampilan thermal lama tidak berubah. Menunggu cek manual (B6).
- Perbaikan Tahap B: hasil cetak masih sama karena tombol "Cetak voucher terpilih" di /voucher (vouchers/index.blade.php) langsung membuka print=1 tanpa parameter paper/mode. Ditambahkan dropdown Mode cetak + Ukuran kertas (80/68/58/A4) di form tombol itu, sama seperti Master Harga Jual.
- Penyesuaian Tahap B: barcode voucher diperkecil (tinggi 6 mm, 5 mm di 68 mm/A4, lebar 70%), nominal diperbesar (58 mm: 28px, 68 mm/A4: 32px, 80 mm: 36px). File: print-vouchers.blade.php.
- Tahap C selesai: resources/views/campaigns/form.blade.php + CampaignController.php (searchProducts menambah page/per_page). Select2 produk diganti tombol "Pilih Produk" + modal (cari, centang, paging server 20/halaman, pilihan bertahan, "N dipilih", tombol Tambahkan). Scan barcode, hapus produk, dan mode edit (qty lama tetap) memakai logika yang sama. Kolom "Isi per bundle" hanya tampil untuk Bundle (header dan baris), Flash Sale/Voucher disembunyikan. Menunggu cek manual (C6).
- Penyesuaian Tahap C: daftar produk di modal Pilih Produk dan scan barcode dibatasi ke produk yang harga jualnya sudah diatur (CampaignController::priceSetProducts, whereHas outletPrices currentlyActive). Scan produk tanpa harga jual menampilkan pesan khusus.
- Tahap D selesai: app/Exports/SetStockExport.php (baru, kerangka BaseLaporanTokoExport + SheetWriter), SetStockController::export, route set-stock.export (GET, throttle 20/menit, di grup role superadmin|staff-outlet), tombol + modal Export Excel di set-stock/index.blade.php. Kolom: Barcode, Nama Barang, Kategori, Qty, Status (warna per status), baris TOTAL. Sudah = item draft pada rentang tanggal WIB (terbaru per produk); Belum = produk berharga jual aktif di outlet yang tidak ada di daftar sudah; qty belum = "-". Menunggu cek manual (D6).
- Tahap E selesai: app/Exports/KassaHarianExport.php (baru, kerangka BaseLaporanTokoExport + SheetWriter), LaporanController::exportOutletKassa, route laporan.outlet.kassa (GET, throttle 10/menit), kartu "Penjualan Kassa Harian" di laporan/outlet.blade.php, modal mode satu tanggal (_modal) + preset Hari ini / Kemarin (_script). Satu baris per sesi: Outlet, Kassa, Akun Kassa, Shift, Jam Buka/Tutup, Status, Saldo Awal, Jumlah Transaksi, Total Penjualan, Tunai, metode bayar lain (kolom dinamis), Voucher (lembar + Rp, termasuk cashback), Bon, Kas Masuk, Setoran Tutup Kasir, Total Setoran (bon + setoran tutup), Uang Disisakan, Uang Seharusnya, Uang Fisik, Selisih (hijau = pas, merah = selisih). Sesi belum tutup berlatar kuning dengan status BELUM TUTUP. Baris TOTAL di bawah. Menunggu cek manual (E6).
- Revisi Tahap E: kolom laporan dipangkas sesuai permintaan (tanpa jam, status, voucher, kas masuk, uang disisakan/seharusnya/fisik, selisih). Nama Kassa = akun kassa, Nama Kasir = daftar shift. Sesi belum tutup: baris kuning, "(BELUM TUTUP)" di nama kassa, setoran "-". Bila semua outlet dipilih, dikelompokkan per outlet. File: KassaHarianExport.php, outlet.blade.php (deskripsi kartu).