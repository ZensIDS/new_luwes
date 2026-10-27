# TODOLIST — Revisi Set Stock, Pembulatan, Nota 68, Pendapatan Lain, Master Harga Jual, Index Penjualan, Desain Label Harga

Status: `[ ]` belum · `[~]` dikerjakan · `[x]` selesai
Aturan kerja: bertahap · tiap tahap hanya kirim file yang berubah/baru (file card, bukan zip, tidak ditempel di chat) · todolist ini di-update tiap tahap selesai.
Revisi ini mandiri (tidak terkait revisi MMC).

---

## Keputusan yang sudah final

| # | Keputusan |
|---|-----------|
| K1 | Pembulatan harga: ke ATAS kelipatan 100 (19.870→19.900, 4.510→4.600) |
| K2 | Pembulatan total nota dihapus; data lama tetap tampil (baris "Pembulatan" hanya muncul jika nilainya > 0) |
| K3 | Aturan harga baru otomatis dibulatkan (tanpa command); preview + hitungan pembulatan terlihat di form |
| K4 | Dibuat artisan command untuk membulatkan ulang seluruh aturan harga lama (dengan mode `--dry-run`) |
| K5 | Set Stock: qty MENIMPA stok (tercatat sebagai adjustment + StockMovement) |
| K6 | Set Stock: akses superadmin + staff-outlet |
| K7 | Set Stock: HPP = HPP terakhir tersimpan di aturan harga jual (sama dengan LatestHpp) |
| K8 | Nota: tombol ukuran ke-3 (68mm) selain 58 dan 80; berlaku juga di label produk & voucher |
| K9 | Pendapatan Lain: Select2 produk ajax, minimal ketik beberapa karakter (pola seperti pilih barang di master harga jual) |
| K10 | Tanggal Pendapatan Lain disembunyikan, otomatis = waktu input (created at) |
| K11 | Strategi pembulatan = A: dibulatkan otomatis di `PriceCalculator` sebagai langkah terakhir; command `round-all` untuk laporan before/after + menyegarkan data turunan |
| K12 | Draft Set Stock: SATU keranjang per toko (dipakai bersama) agar tidak terjadi konflik input |
| K13 | Folder nama berawalan angka/simbol digabung ke folder "#/Lainnya" |
| K14 | Export master harga jual: kolom sama seperti tabel index saat ini (tanpa kolom Action); HPP dipisah kolom satuan kecil/besar, komponen persen disimpan sebagai angka persen |
| K15 | Desain label harga mengikuti gambar referensi: nama produk, badge HARGA PROMO, harga jual besar, Harga Normal dicoret, lingkaran DISKON %, barcode + angka kode |
| K16 | Persen diskon = (harga coret − harga jual) ÷ harga coret × 100, dibulatkan ke bilangan bulat; bila 0% atau tidak ada harga coret, badge/lingkaran/harga normal tidak ditampilkan |
| K17 | Label punya opsi "Mode cetak": Thermal (hitam putih, default) atau Warna (pink); layout satu template, beda hanya variabel warna. Ukuran kertas 58/68/80 mm + A4 (label 95 mm) untuk printer warna |
| K18 | Set Stock "menimpa": seluruh qty ditaruh di batch yang jadi HPP terbaru (sama dengan LatestHpp), batch lain produk itu di toko yang sama dijadikan 0 (tercatat adjustment + movement); HPP batch lama tidak diubah; batch MINUS tidak disentuh (ditutup lewat Stok Minus) |
| K19 | Produk belum punya batch: dibuat batch baru bernomor otomatis `SET-YYYYMMDD-{id produk}` dengan HPP = HPP terbaru (K7) |
| K20 | Simpan Set Stock = satu transaksi; bila ada satu item gagal, SEMUA dibatalkan, daftar item gagal ditampilkan, keranjang tetap utuh |
| K21 | Konflik keranjang bersama: tiap item punya nomor versi; mengubah item yang baru diubah orang lain ditolak (409) sampai dicek ulang |
| K22 | Pendapatan Lain: kategori Sewa dikenali lewat slug `sewa` (asumsi saya), karena itu kategori Sewa dikunci (tidak bisa diubah nama/dihapus) |
| K23 | Pendapatan Lain: kolom `tanggal` tetap ada di database (dipakai filter & laporan), diisi otomatis tanggal input; index menampilkan jam input dari created_at (asumsi saya) |
| K24 | Pendapatan Lain: Sewa lama yang belum punya periode/supplier/produk harus dilengkapi saat diedit (asumsi saya, konsisten dengan "sewa wajib lengkap") |
| K25 | Index master harga jual: folder = kata pertama nama produk (dipisah spasi); search atas mencari di semua produk dan mengabaikan folder; kotak "Cari folder" (muncul bila folder > 12) adalah tambahan saya (asumsi, perlu dikonfirmasi) |
| K26 | Export master harga (menggantikan print): hanya cetak/export "semua barang" atau satu folder, urut abjad nama produk, tabel datar tanpa baris pemisah huruf (agar bisa difilter/diurutkan di Excel), format mengikuti Excel Laporan Toko (kop, info, header berkelompok, baris total), hak akses sama seperti index master harga (asumsi saya, perlu dikonfirmasi) |

---

## TAHAP 1 — Pembulatan harga jual (didahulukan, paling berisiko) — SELESAI, menunggu uji user

- [x] 1.1 `PriceCalculator`: pembulatan ke atas kelipatan 100 di harga jual akhir per produk (juga untuk produk tanpa aturan); hasil `calculateItem()` kini punya `price_before_rounding` + `price_rounding`; tambah `roundPrice()`
- [x] 1.2 `CashierSaleService`: pembulatan total transaksi dihapus (rounding_amount = 0 untuk transaksi baru)
- [x] 1.3 `penjualan/print.blade.php` & `show.blade.php`: TIDAK perlu diubah, baris "Pembulatan" sudah hanya tampil jika > 0 (nota lama aman, nota baru tanpa baris itu)
- [x] 1.4 `outlet-prices/form.blade.php`: preview menampilkan "Harga Sebelum Pembulatan" + "Pembulatan (ke atas Rp100)" + Harga Netto POS; kolom "Harga Netto yang diinginkan" ikut dibulatkan ke atas (ada catatan jika target tidak bulat) dan perhitungan persen diskon toko disesuaikan
- [x] 1.5 Index master harga jual, kasir, price checker, retur, label: otomatis ikut karena semuanya memakai `calculateItem()` (tidak ada file lain yang diubah)
- [x] 1.6 Artisan `outlet-prices:round-report` (menggantikan rencana `round-all`): harga netto tidak disimpan di tabel aturan, tapi dihitung dari aturan + HPP terbaru, jadi SEMUA aturan lama otomatis terbulatkan begitu kode baru aktif; command hanya membaca & melaporkan sebelum→sesudah (`--outlet=`, `--detail`, `--csv=`, chunk)
- [~] 1.7 Uji: logika pembulatan + penyelesai target netto sudah disimulasikan (19.870→19.900, 4.510→4.600, 5.000→5.000, dengan penyesuaian nominal/persen); uji di aplikasi (PHP tidak tersedia di sini): form aturan harga, kasir, nota baru & lama, `php artisan outlet-prices:round-report`

## TAHAP 2 — Nota 68mm — SELESAI, menunggu uji user

- [x] 2.1 `penjualan/print.blade.php`: profil ukuran per kertas (array 58/68/80: area cetak, font, logo), tombol "Kertas 68mm", ukuran terakhir diingat per perangkat (localStorage) seperti sebelumnya
- [x] 2.2 `cashier/print-vouchers.blade.php` (opsi 68mm di pilihan ukuran) & `cashier/print-products.blade.php` (sebelumnya terkunci 80mm; kini ada pilihan 80/68/58mm, ukuran huruf & padding label menyesuaikan)
- [~] 2.3 Uji tampilan 58/68/80: perlu dites di printer user (area cetak 68mm diperkirakan 58mm; bisa disesuaikan di array `$paperProfiles`)

## TAHAP 3 — Set Stock (Stock Toko) — KODE SELESAI, menunggu uji user

Rancangan:
- Draft (keranjang) disimpan di database, SATU per toko (bersama, K12) — karena dipakai bersama, tampilkan siapa yang terakhir mengubah tiap item, kunci per baris (item yang sama tidak ditimpa diam-diam bila 2 orang scan bersamaan), dan opsi "muat ulang" daftar; otomatis tersimpan tiap scan/ubah qty → aman saat logout / perangkat mati / lupa simpan
- Scan ulang barang yang sudah ada di draft → qty sebelumnya terisi kembali (tidak reset, tidak menambah), fokus ke qty untuk cek ulang
- Simpan = 1 transaksi database; semua item atau tidak sama sekali; hitung ulang jumlah item tersimpan vs draft; kalau ada yang gagal tampil nama barangnya, draft tidak hilang
- Tombol anti klik ganda (token idempotensi), draft ditandai selesai setelah sukses

Tugas:
- [x] 3.1 Migration `set_stock_drafts` (unik per outlet berstatus aktif) + `set_stock_draft_items` (unik per draft+produk, kolom qty, jumlah scan, waktu scan, user terakhir mengubah)
- [x] 3.2 `OutletStockService::setInitialStock()`: batch otomatis (SKU/batch number generate) bila belum ada, menimpa bila sudah ada, catat StockAdjustment + StockMovement, HPP dari LatestHpp
- [x] 3.3 `SetStockController` + route + akses (superadmin, staff-outlet) + menu "Set Stock" di treeview Stock Toko
- [x] 3.4 Endpoint scan (cari persis by barcode, ringan) + autosave draft + simpan massal
- [x] 3.5 View: pilih toko di awal → halaman scan; kartu info produk (barcode, nama, HPP, diskon dll seperti master harga jual); input qty autofokus setelah scan; Enter = tambah ke draft, fokus balik ke scan
- [x] 3.6 Daftar draft di panel samping/tombol "Isi Keranjang (n)" yang bisa dibuka-tutup (tidak mengotori halaman); edit qty, hapus item, cari di keranjang
- [x] 3.7 Pulihkan draft otomatis saat halaman dibuka kembali
- [ ] 3.8 Uji (PHP tidak tersedia di sini, belum dijalankan; jalankan `php artisan migrate` dulu): scan ganda, 300+ item, logout lalu masuk lagi, simpan gagal sebagian, klik ganda, produk belum punya batch, produk sudah punya batch

## TAHAP 4 — Pendapatan Lain (ganti nama dari Other Income) — KODE SELESAI, menunggu uji user

- [x] 4.1 Label "Other Income" → "Pendapatan Lain": sidebar, judul/tombol index & form, kategori, export (judul laporan, nama sheet, nama file `Laporan-Pendapatan-Lain_...`), pesan error, teks info Listing di form produk & master harga jual, deskripsi activity log. Nama route & URL (`other-income*`) tidak diubah
- [x] 4.2 Migration `2026_10_27_140000_add_sewa_fields_to_other_incomes_table`: `sewa_mulai`, `sewa_selesai`, `supplier_id`, `product_id` (semua nullable; supplier/produk dihapus → NULL, nama produk tetap di `product_name_snapshot`)
- [x] 4.3 Form: pilih kategori Sewa → muncul Tanggal Dari–Sampai, Select2 Supplier, Select2 Produk (ajax `other-income.products.search`: hanya produk milik supplier terpilih, minimal 2 huruf, maks 20 hasil); ganti supplier mengosongkan produk; ganti ke kategori lain mengosongkan isian sewa
- [x] 4.4 Tanggal input disembunyikan: otomatis = waktu input saat simpan, TIDAK berubah saat edit (form menampilkan "Waktu Input" read-only); validasi server: sewa wajib lengkap, sampai ≥ dari, produk harus milik supplier (data lama yang tidak diubah tetap lolos); isian sewa dikosongkan server untuk kategori lain
- [x] 4.5 Index menampilkan periode sewa, supplier, produk di kolom Keterangan + kolom Tanggal kini tampil tanggal & jam input; Export Excel: kolom baru Sewa Dari, Sewa Sampai, Supplier, Produk Sewa (total Jumlah ikut digeser)
- [x] 4.5b Kategori Sewa dikunci (nama tidak bisa diubah, tidak bisa dihapus) karena form mengenalinya lewat slug `sewa`
- [ ] 4.6 Uji (PHP tidak tersedia di sini, belum dijalankan; jalankan `php artisan migrate` dulu): ganti kategori bolak-balik, tambah Sewa lengkap & tidak lengkap, edit data lama (Sewa lama yang belum punya periode/supplier/produk wajib dilengkapi saat diedit), produk dari supplier lain ditolak, export Excel (kolom & total), kategori lain tidak menyimpan isian sewa

## TAHAP 5 — Index master harga jual: card folder — KODE SELESAI, menunggu uji user

- [x] 5.1 Service `PriceFolderIndex`: kunci folder = kata pertama nama produk (`SUBSTRING_INDEX`, huruf besar), nama berawalan angka/simbol/kosong → folder "#/Lainnya" (K13); jumlah per outlet di-cache (10 menit) dan dibersihkan otomatis saat aturan harga dibuat/dihapus/dipulihkan/pindah outlet-produk (hook di `OutletPrice`) atau nama produk berubah/dihapus/dipulihkan (hook di `Product`); tombol Kembali yang hanya memperbarui updated_at tidak membersihkan cache
- [x] 5.2 Tampilan awal index (setelah outlet dipilih): card "All Product" lalu card folder (grid ringan, tanpa gambar, nama + jumlah produk); bila folder lebih dari 12 muncul kotak "Cari folder..." (disaring di browser)
- [x] 5.3 Klik card → daftar produk folder itu (tabel sama seperti sekarang, urut A-Z, 25 per halaman) dengan breadcrumb + tombol "Kembali ke folder"; "All Product" membuka seluruh produk outlet; setelah simpan/edit/hapus/Kembali, halaman kembali ke folder yang sama
- [x] 5.4 Search di atas tetap aktif dan mencari di SEMUA produk outlet (folder diabaikan saat ada kata pencarian)
- [ ] 5.5 Uji (PHP tidak tersedia di sini, belum dijalankan): nama berawalan angka/simbol masuk "#/Lainnya", huruf besar/kecil, ganti outlet, outlet dengan data besar (kecepatan card & isi folder), jumlah di card = jumlah baris di folder, ganti nama produk / tambah / hapus aturan harga → card ikut berubah, kembali ke folder yang sama setelah edit, tombol Cetak label terpilih tetap jalan di dalam folder

## TAHAP 6 — Export Excel master harga jual (sebelumnya Print) — KODE SELESAI, menunggu uji user

Catatan: awalnya tahap ini berupa halaman print A4; atas permintaan user diganti menjadi export Excel. Halaman print (route `outlet-prices.print`, view `outlet-prices/print/*`) dihapus.

- [x] 6.1 Tombol "Export Excel" di index (muncul setelah outlet dipilih, baik di tampilan folder maupun daftar produk) + popup: "Semua barang (outlet terpilih)" atau "Barang dalam folder" (pilih folder, bisa dicari); bila popup dibuka dari dalam folder, folder itu sudah terpilih; folder wajib dipilih sebelum tombol aktif; file langsung terunduh (tanpa tab baru)
- [x] 6.2 Route `outlet-prices.export` (`GET /outlet-prices/export`, throttle 30/menit) + `OutletPriceController::exportMaster` (validasi cakupan sama seperti print, akses sama dengan index, staff-outlet terkunci ke outlet sendiri)
- [x] 6.3 `app/Exports/OutletPriceExport.php` memakai kerangka Excel Laporan Toko (`BaseLaporanTokoExport`, `SheetWriter`, `Theme`, `ReportMeta`): kop perusahaan + logo, judul, info (Per Tanggal, Outlet, Cakupan, Total Produk, Waktu Cetak, Dicetak Oleh), header berkelompok (Produk · HPP Terbaru · Komponen Harga · Harga · Keterangan & Stok), zebra, filter + freeze pane, baris TOTAL PRODUK, A4 landscape muat 1 halaman lebar dengan judul berulang. Nama file `Laporan-Master-Harga-Jual-<outlet>[-Folder-<folder>]_<tanggal>.xlsx`
- [x] 6.4 Kolom (K14): Barcode, Nama Product, Satuan, HPP/Satuan Kecil, HPP/Satuan Besar, Pajak, Disc Reg, Disc 1, Disc 2, Disc Toko, Margin, Harga Jual, Penyesuaian, Catatan, Terakhir Update, Stok Outlet, Konversi Stok. Semua angka tetap angka (persen = format persen, nominal = Rupiah); komponen bernilai 0 dikosongkan; stok minus merah
- [x] 6.5 Query ringan: daftar id diurutkan di muka (`orderedPriceIds`), baris + HPP + stok dimuat per 200 id; perhitungan harga memakai `decoratePrices` yang sama dengan index
- [ ] 6.6 Uji (PHP tidak tersedia di sini, belum dijalankan): export semua barang & satu folder, folder "#/Lainnya", outlet besar (kecepatan & memori), harga/stok di Excel sama dengan index, persen vs nominal tampil benar, barcode tidak jadi notasi ilmiah, staff-outlet tidak bisa export outlet lain, buka di Excel + cetak (A4 landscape)

## TAHAP 7 — Rapikan index penjualan — KODE SELESAI, menunggu uji user

- [x] 7.1 Tinjau `penjualan/index.blade.php` + `getIndexData`: tiap baris memuat tabel rincian barang penuh di kolom "Detail" (baris sangat tinggi), tidak ada kolom total, tombol aksi tidak seragam, teks "___customer" tampil mentah
- [x] 7.2 Rapikan: judul halaman (content-header) + box primary; tombol header dirapikan urutannya; filter Akun Kasir & Tanggal dalam satu baris rapi (label di atas, otomatis turun baris di layar kecil); kolom baru "Total" (grand total, rata kanan); rincian barang disembunyikan dan dibuka lewat tombol "Detail" per baris (child row, tanpa request tambahan karena data sudah dikirim server); tombol aksi satu grup kecil dengan ikon (Detail, Show, Re-Print, Hapus); label "Retur" di kode invoice bila penjualan punya retur; teks DataTables berbahasa Indonesia; kolom kosong tampil "—"
- [ ] 7.3 Uji tampilan desktop & mobile (PHP tidak tersedia di sini, belum dijalankan): filter kasir/tanggal, pencarian awalan invoice/produk, urut tanggal/kode, tombol Detail buka-tutup (juga setelah pindah halaman/urut ulang), Show/Re-Print, Hapus (dialog alasan tetap jalan, tombol Hapus nonaktif saat diblokir), kasir & staff-outlet (tanpa tombol Hapus/Buat Penjualan), label Retur, tabel bisa digeser di layar kecil

## TAHAP 8 — Desain label harga (cetak label produk) — L1+L2+L3 dikerjakan, menunggu uji user

- [x] 8.1 `CashierPrintController::products`: tambah `print_discount_percent` (K16) dan baca parameter `mode` (`thermal`/`warna`, default thermal)
- [x] 8.2 `cashier/print-products.blade.php`: label dibuat ulang sesuai gambar (K15); strip nama outlet dihapus; barcode garis Code 128 (sama seperti voucher); tanggal cetak, qty, dan pilihan produk tetap ada
- [x] 8.3 Dropdown "Mode cetak" (Thermal / Warna) + pilihan kertas A4 (label 95 mm) di form cetak; ukuran font diskalakan per lebar kertas, ukuran harga mengecil otomatis untuk angka panjang
- [ ] 8.4 Uji di printer user: label 58/68/80 mm thermal (bar kode terbaca scanner?), mode warna A4, produk tanpa diskon, harga 7 digit ke atas, nama produk panjang (2 baris)

## TAHAP 9 — Laporan Toko: Daftar Barang Outlet (export Excel) — KODE SELESAI, menunggu uji user

- [x] 9.1 Kartu baru "Daftar Barang Outlet" di grup Stok pada Laporan Toko (`laporan/outlet`); modal: Outlet (WAJIB dipilih, tanpa "Semua outlet"; outlet tunggal terpilih otomatis) + Cakupan nama awal (folder = kata pertama nama barang, sama dengan folder Master Harga Jual; default "Semua barang")
- [x] 9.2 Route `laporan.outlet.product-list` (export, throttle 10/menit) dan `laporan.outlet.product-folders` (JSON daftar folder per outlet, memakai cache `PriceFolderIndex`); folder dimuat ulang saat outlet diganti; akses outlet lewat `OutletAccess` (staff-outlet terkunci ke outletnya)
- [x] 9.3 `app/Exports/OutletProductListExport.php` (kerangka Excel Laporan Toko): hanya kolom Barcode dan Nama Barang, urut A-Z, baris TOTAL BARANG; nama file `Laporan-Daftar-Barang-Toko-<outlet>[-Folder-<folder>]_<tanggal>.xlsx`
- [x] 9.4 Asumsi (perlu dikonfirmasi): "semua barang" outlet = produk yang punya aturan harga di Master Harga Jual outlet tsb
- [ ] 9.5 Uji (PHP tidak tersedia di sini, belum dijalankan): outlet belum dipilih (muncul pesan), ganti outlet (folder ikut berganti), folder "#/Lainnya", outlet dengan banyak barang, barcode tidak jadi notasi ilmiah, staff-outlet tidak bisa mengambil outlet lain, kartu lain di Laporan Toko tetap normal (filter tanggal/outlet/kasir/rafaksi)

---

## Pertanyaan yang masih terbuka

Tidak ada. Semua pertanyaan sudah dijawab dan masuk ke tabel keputusan (K11–K21). Tahap 1–2, 4, 5, 6 (export Excel), dan 8 menunggu uji user; Tahap 3 (Set Stock) kodenya selesai, K18–K21 adalah asumsi saya yang perlu dikonfirmasi; Tahap 4: K22–K24, Tahap 5: K25, dan Tahap 6: K26 adalah asumsi saya yang perlu dikonfirmasi.