# TODOLIST — Revisi Set Stock, Pembulatan, Nota 68, Pendapatan Lain, Master Harga Jual, Index Penjualan

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
| K14 | Print master harga jual: kolom sama seperti tabel index saat ini (tanpa kolom Action) |

---

## TAHAP 1 — Pembulatan harga jual (didahulukan, paling berisiko) — SELESAI, menunggu uji user

- [x] 1.1 `PriceCalculator`: pembulatan ke atas kelipatan 100 di harga jual akhir per produk (juga untuk produk tanpa aturan); hasil `calculateItem()` kini punya `price_before_rounding` + `price_rounding`; tambah `roundPrice()`
- [x] 1.2 `CashierSaleService`: pembulatan total transaksi dihapus (rounding_amount = 0 untuk transaksi baru)
- [x] 1.3 `penjualan/print.blade.php` & `show.blade.php`: TIDAK perlu diubah, baris "Pembulatan" sudah hanya tampil jika > 0 (nota lama aman, nota baru tanpa baris itu)
- [x] 1.4 `outlet-prices/form.blade.php`: preview menampilkan "Harga Sebelum Pembulatan" + "Pembulatan (ke atas Rp100)" + Harga Netto POS; kolom "Harga Netto yang diinginkan" ikut dibulatkan ke atas (ada catatan jika target tidak bulat) dan perhitungan persen diskon toko disesuaikan
- [x] 1.5 Index master harga jual, kasir, price checker, retur, label: otomatis ikut karena semuanya memakai `calculateItem()` (tidak ada file lain yang diubah)
- [x] 1.6 Artisan `outlet-prices:round-report` (menggantikan rencana `round-all`): harga netto tidak disimpan di tabel aturan, tapi dihitung dari aturan + HPP terbaru, jadi SEMUA aturan lama otomatis terbulatkan begitu kode baru aktif; command hanya membaca & melaporkan sebelum→sesudah (`--outlet=`, `--detail`, `--csv=`, chunk)
- [~] 1.7 Uji: logika pembulatan + penyelesai target netto sudah disimulasikan (19.870→19.900, 4.510→4.600, 5.000→5.000, dengan penyesuaian nominal/persen); uji di aplikasi (PHP tidak tersedia di sini): form aturan harga, kasir, nota baru & lama, `php artisan outlet-prices:round-report`

## TAHAP 2 — Nota 68mm

- [ ] 2.1 `penjualan/print.blade.php`: konfigurasi ukuran per kertas (array 58/68/80: lebar area cetak, font, logo), tombol "Kertas 68mm", ukuran terakhir diingat per perangkat seperti sekarang
- [ ] 2.2 `cashier/print-vouchers.blade.php` & `print-products.blade.php`: dukung 68mm
- [ ] 2.3 Uji tampilan 58/68/80 (area cetak 68mm perlu dites di printer user)

## TAHAP 3 — Set Stock (Stock Toko)

Rancangan:
- Draft (keranjang) disimpan di database, SATU per toko (bersama, K12) — karena dipakai bersama, tampilkan siapa yang terakhir mengubah tiap item, kunci per baris (item yang sama tidak ditimpa diam-diam bila 2 orang scan bersamaan), dan opsi "muat ulang" daftar; otomatis tersimpan tiap scan/ubah qty → aman saat logout / perangkat mati / lupa simpan
- Scan ulang barang yang sudah ada di draft → qty sebelumnya terisi kembali (tidak reset, tidak menambah), fokus ke qty untuk cek ulang
- Simpan = 1 transaksi database; semua item atau tidak sama sekali; hitung ulang jumlah item tersimpan vs draft; kalau ada yang gagal tampil nama barangnya, draft tidak hilang
- Tombol anti klik ganda (token idempotensi), draft ditandai selesai setelah sukses

Tugas:
- [ ] 3.1 Migration `set_stock_drafts` (unik per outlet berstatus aktif) + `set_stock_draft_items` (unik per draft+produk, kolom qty, jumlah scan, waktu scan, user terakhir mengubah)
- [ ] 3.2 `OutletStockService::setInitialStock()`: batch otomatis (SKU/batch number generate) bila belum ada, menimpa bila sudah ada, catat StockAdjustment + StockMovement, HPP dari LatestHpp
- [ ] 3.3 `SetStockController` + route + akses (superadmin, staff-outlet) + menu "Set Stock" di treeview Stock Toko
- [ ] 3.4 Endpoint scan (cari persis by barcode, ringan) + autosave draft + simpan massal
- [ ] 3.5 View: pilih toko di awal → halaman scan; kartu info produk (barcode, nama, HPP, diskon dll seperti master harga jual); input qty autofokus setelah scan; Enter = tambah ke draft, fokus balik ke scan
- [ ] 3.6 Daftar draft di panel samping/tombol "Isi Keranjang (n)" yang bisa dibuka-tutup (tidak mengotori halaman); edit qty, hapus item, cari di keranjang
- [ ] 3.7 Pulihkan draft otomatis saat halaman dibuka kembali
- [ ] 3.8 Uji: scan ganda, 300+ item, logout lalu masuk lagi, simpan gagal sebagian, klik ganda, produk belum punya batch, produk sudah punya batch

## TAHAP 4 — Pendapatan Lain (ganti nama dari Other Income)

- [ ] 4.1 Ganti label "Other Income" → "Pendapatan Lain" (sidebar, judul, breadcrumb, export, pesan); nama route tidak diubah
- [ ] 4.2 Migration kolom nullable: `sewa_mulai`, `sewa_selesai`, `supplier_id`, `product_id`
- [ ] 4.3 Form: kategori Sewa memunculkan Tanggal Dari–Sampai, Select2 Supplier, Select2 Produk (ajax, terhubung supplier terpilih, minimal ketik beberapa karakter, maksimal 20 hasil)
- [ ] 4.4 Tanggal input disembunyikan, otomatis dari created at; validasi (sewa wajib lengkap, sampai ≥ dari)
- [ ] 4.5 Index/export Pendapatan Lain menampilkan info sewa
- [ ] 4.6 Uji: ganti kategori, edit data lama, produk tidak ikut supplier lain

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

---

## Pertanyaan yang masih terbuka

Tidak ada. Semua pertanyaan sudah dijawab dan masuk ke tabel keputusan (K11–K14). Menunggu aba-aba untuk mulai Tahap 1.