# TODOLIST — Revisi Set Stock, Pembulatan, Nota 68, Pendapatan Lain, Master Harga Jual, Index Penjualan, Desain Label Harga

[x] Note
    [x] Error saat penerimaan barang (before delivery completed)
    [x] Check all search with front key
    [x] Check all pagination page & datatable query method
    [x] Check bug on penjualan
    [x] Check sidebar active bug
    [x] Check Bug on request-order
    [x] Check Bug on picking-list
    [x] Update database GLAD2GLOW
    [x] Update database all product lama yang belum ada harga margin dll
    [x] Update Aturan Min Stock
[x] Master Harga
    [x] Tambah data terakhir di check
    [x] Tambah note di master
    [x] Check print label
    [x] Auto isi disc toko sesuai harga yang di inginkan
    [x] Bisa tambah barang sekalian, bisa edit barang sekalian, setting harga sekalian tanpa bolak balik
[x] Belanja Langsung Toko
    [x] tidak langsung masuk stock toko
    [x] Tambahkan check product seperti di PO
    [x] tambahkan autosave system seperti di PO
[x] Voucher
    [x] Check Desain voucher
    [x] opsi rafaksi tanpa batas kuota
[x]Product
    [x] barcode tidak menunggu save sudah alert jika duplicate
    [x] harga beli agar 0 di depan tidak ikut dan parsing rupiah
    [x] nama barang uppercase
    [x] dibagi harga satuan besar (khusus untuk daftar product di halaman master harga)

[x] Pendapatan lainnya
    [x] listing -> product baru -> nominal (setting)
    [x] sewa

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
| K14 | Print master harga jual: kolom sama seperti tabel index saat ini (tanpa kolom Action) |
| K15 | Desain label harga mengikuti gambar referensi: nama produk, badge HARGA PROMO, harga jual besar, Harga Normal dicoret, lingkaran DISKON %, barcode + angka kode |
| K16 | Persen diskon = (harga coret − harga jual) ÷ harga coret × 100, dibulatkan ke bilangan bulat; bila 0% atau tidak ada harga coret, badge/lingkaran/harga normal tidak ditampilkan |
| K17 | Label punya opsi "Mode cetak": Thermal (hitam putih, default) atau Warna (pink); layout satu template, beda hanya variabel warna. Ukuran kertas 58/68/80 mm + A4 (label 95 mm) untuk printer warna |
| K18 | Set Stock "menimpa": seluruh qty ditaruh di batch yang jadi HPP terbaru (sama dengan LatestHpp), batch lain produk itu di toko yang sama dijadikan 0 (tercatat adjustment + movement); HPP batch lama tidak diubah; batch MINUS tidak disentuh (ditutup lewat Stok Minus) |
| K19 | Produk belum punya batch: dibuat batch baru bernomor otomatis `SET-YYYYMMDD-{id produk}` dengan HPP = HPP terbaru (K7) |
| K20 | Simpan Set Stock = satu transaksi; bila ada satu item gagal, SEMUA dibatalkan, daftar item gagal ditampilkan, keranjang tetap utuh |
| K21 | Konflik keranjang bersama: tiap item punya nomor versi; mengubah item yang baru diubah orang lain ditolak (409) sampai dicek ulang |

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

## TAHAP 4 — Pendapatan Lain (ganti nama dari Other Income)

- [x] 4.1 Ganti label "Other Income" → "Pendapatan Lain" (sidebar, judul, breadcrumb, export, pesan); nama route tidak diubah
- [x] 4.2 Migration kolom nullable: `sewa_mulai`, `sewa_selesai`, `supplier_id`, `product_id`
- [x] 4.3 Form: kategori Sewa memunculkan Tanggal Dari–Sampai, Select2 Supplier, Select2 Produk (ajax, terhubung supplier terpilih, minimal ketik beberapa karakter, maksimal 20 hasil)
- [x] 4.4 Tanggal input disembunyikan, otomatis dari created at; validasi (sewa wajib lengkap, sampai ≥ dari)
- [x] 4.5 Index/export Pendapatan Lain menampilkan info sewa
- [x] 4.6 Uji: ganti kategori, edit data lama, produk tidak ikut supplier lain

## TAHAP 5 — Index master harga jual: card folder

- [ ] 5.1 Query folder: kata pertama nama produk (`SUBSTRING_INDEX`) + jumlah, per outlet, di-cache dan dibersihkan saat aturan harga berubah; awalan angka/simbol masuk folder "#/Lainnya" (K13)
- [ ] 5.2 Tampilan awal: card "All Product" lalu card folder (desain modern, ringan, tanpa gambar berat)
- [ ] 5.3 Klik card → list produk folder tersebut (tampilan tabel sama seperti sekarang), breadcrumb + tombol kembali
- [ ] 5.4 Search di atas tetap aktif (cari di semua produk)
- [ ] 5.5 Uji: nama berawalan angka/simbol, huruf besar/kecil, outlet berbeda, data besar

## TAHAP 6 — Print master harga jual

- [ ] 6.1 Tombol Print di index + popup modern: "Semua barang (outlet terpilih)" atau "Barang dalam folder" (pilih folder)
- [ ] 6.2 Route + halaman print: kolom sama seperti tabel index (Barcode, Nama Product, HPP, diskon-diskon, Harga Jual, Penyesuaian, Catatan, Terakhir Update, Stock Outlet; tanpa Action), pemisah per nama awal, desain rapi, siap cetak A4 landscape (K14)
- [ ] 6.3 Query ringan (per folder/chunk, tanpa memuat semua sekaligus)
- [ ] 6.4 Uji: outlet besar, folder kosong, halaman terpotong

## TAHAP 7 — Rapikan index penjualan

- [ ] 7.1 Tinjau `penjualan/index.blade.php` + `getIndexData` (tombol, filter, tabel)
- [ ] 7.2 Rapikan layout header/tombol, filter, dan tabel (kolom, spasi, status, responsif)
- [ ] 7.3 Uji tampilan desktop & mobile

## TAHAP 8 — Desain label harga (cetak label produk) — L1+L2+L3 dikerjakan, menunggu uji user

- [x] 8.1 `CashierPrintController::products`: tambah `print_discount_percent` (K16) dan baca parameter `mode` (`thermal`/`warna`, default thermal)
- [x] 8.2 `cashier/print-products.blade.php`: label dibuat ulang sesuai gambar (K15); strip nama outlet dihapus; barcode garis Code 128 (sama seperti voucher); tanggal cetak, qty, dan pilihan produk tetap ada
- [x] 8.3 Dropdown "Mode cetak" (Thermal / Warna) + pilihan kertas A4 (label 95 mm) di form cetak; ukuran font diskalakan per lebar kertas, ukuran harga mengecil otomatis untuk angka panjang
- [ ] 8.4 Uji di printer user: label 58/68/80 mm thermal (bar kode terbaca scanner?), mode warna A4, produk tanpa diskon, harga 7 digit ke atas, nama produk panjang (2 baris)

---

## Pertanyaan yang masih terbuka

Tidak ada. Semua pertanyaan sudah dijawab dan masuk ke tabel keputusan (K11–K21). Tahap 1–2 dan 8 menunggu uji user; Tahap 3 (Set Stock) kodenya selesai, K18–K21 adalah asumsi saya yang perlu dikonfirmasi.
