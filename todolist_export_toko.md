# Todo List: Redesign Export Excel Laporan Toko

Tujuan: semua download Excel di **Laporan Toko** tampil rapi, konsisten, dan advance (kop, filter terbaca, format angka, rumus, warna status, siap difilter/pivot). Desain gunakan warna warna netral formal.

## Ruang lingkup (7 laporan)

| # | Laporan | Route export | Sumber kode saat ini |
|---|---------|--------------|----------------------|
| 1 | Penjualan Outlet | `laporan.outlet.penjualan` | `OutletPenjualanExport` + `exports/outlet/penjualan.blade.php` |
| 2 | Rafaksi Outlet | `laporan.outlet.rafaksi` | `OutletRafaksiExport` + `exports/outlet/rafaksi.blade.php` |
| 3 | Retur Penjualan Outlet | `laporan.outlet.retur` | `OutletReturExport` + `exports/outlet/retur.blade.php` |
| 4 | All Stock Outlet | `laporan.outlet.all-stock` | `OutletAllStockExport` + `exports/outlet/all-stock.blade.php` |
| 5 | Minimal Stock & Alokasi PO | `laporan.outlet.minimal-stock` | `OutletMinimalStockExport` + `exports/outlet/minimal-stock.blade.php` |
| 6 | PR (Permintaan ke Gudang) | `laporan.export.pr` | `LaporanPRExport` |
| 7 | Retur Toko ke Gudang | `laporan.retur-outlet` | `ReturOutletExport` |

Data lima laporan pertama dihitung di `OutletLaporanService` (tidak diubah, hanya cara menampilkannya).

## Temuan masalah

- [ ] Lima laporan pertama berupa tabel HTML polos (`FromView`): tanpa kop, warna, border, lebar kolom, freeze pane, atau filter.
- [ ] Filter yang dipakai (periode, outlet, kasir, rafaksi) tidak ditulis di sheet, padahal sudah dikirim ke view.
- [ ] Angka mentah tanpa format Rupiah/ribuan.
- [ ] Barcode panjang berisiko tampil sebagai notasi ilmiah (`8,99887E+12`).
- [ ] Penjualan memakai `rowspan` (merge sel) sehingga sulit di-filter, di-sort, dan di-pivot.
- [ ] Total berupa angka mentah, bukan rumus Excel.
- [ ] Rekap pembayaran di Penjualan hanya tabel kedua di bawah, tanpa pemisah.
- [ ] Minimal Stock: 17 kolom tanpa pengelompokan, status `out_of_stock` tidak berwarna.
- [ ] PR: tanpa style, "Kode PO" selalu `-`, "Keterangan" kosong, tanggal dan qty berupa teks, tanpa total.
- [ ] Retur Toko ke Gudang: sudah ada kop, tapi jenis retur hardcode "IN", warna dan lebar kolom manual, qty berupa teks, tanpa total.
- [ ] Ada 3 gaya berbeda (polos, setengah styled, tanpa style), tidak konsisten.
- [ ] Nama file tanpa periode, properti dokumen dan pengaturan cetak belum ada.

## A. Fondasi bersama (kerjakan pertama)

> Status: kode selesai, belum diuji di Excel/LibreOffice. Pengujian visual terjadi saat dipakai pertama kali di B1.

- [x] A1. Buat kelas dasar gaya export laporan toko (satu tema warna, font, border tipis, zebra row). — `app/Exports/LaporanToko/BaseLaporanTokoExport.php` + `SheetWriter.php` + `Theme.php`
- [x] A2. Kop laporan: logo, nama perusahaan, alamat, kontak (dari `settings.json`). — `SheetWriter::kop()` (logo dari settings, fallback `public/img/logo.jpeg`)
- [x] A3. Blok informasi laporan: judul, periode, outlet, kasir, periode rafaksi, waktu cetak, dicetak oleh. — `SheetWriter::info()` + `ReportMeta` (outlet/kasir/rafaksi dari filter)
- [x] A4. Helper format: Rupiah dan ribuan, tanggal asli (bukan teks), persen, barcode sebagai teks. — konstanta di `Theme::FMT_*`; tanggal asli, barcode/kode lewat opsi kolom `'text' => true`
- [x] A5. Standar sheet: freeze pane di bawah header, autofilter, lebar kolom otomatis dengan batas maksimum, nama sheet jelas. — `SheetWriter::header()` + `finish()` (lebar otomatis maks 40, kecuali diatur per kolom)
- [x] A6. Pengaturan cetak: landscape, fit to width, header tabel berulang tiap halaman. — di `SheetWriter::finish()`
- [x] A7. Baris total memakai rumus (`SUBTOTAL` / `SUM`) agar ikut berubah saat data difilter. — `SheetWriter::totalRow()` memakai `SUBTOTAL`
- [x] A8. Nama file memuat jenis laporan dan periode, contoh `Laporan-Penjualan-Toko_2026-10-01_sd_2026-10-13.xlsx`. — `ReportMeta::fileNameFor()`, sudah terpasang di 5 laporan outlet di `LaporanController`; PR dan Retur Toko menyusul di B6/B7
- [x] A9. Properti dokumen (judul, pembuat, deskripsi periode). — `ReportMeta::properties()`; terpasang otomatis lewat `BaseLaporanTokoExport`
- [x] A10. Tentukan tema warna (default: ungu mengikuti aplikasi; alternatif: biru tua formal). — dipilih biru tua formal + abu netral (sesuai arahan warna netral formal); ganti di `Theme.php` bila ingin ungu

## B. Per laporan

### B1. Penjualan Outlet
- [x] Hilangkan `rowspan`; ulangi invoice, outlet, kasir, jenis di setiap baris (atau beri pemisah antar invoice).
- [x] Subtotal per invoice.
- [x] Sheet kedua "Rekap": total per metode pembayaran, bon, total voucher, setoran akhir, jumlah transaksi.
- [x] Format Rupiah untuk harga satuan, rafaksi, subtotal.
- [x] Beri warna berbeda untuk baris "Pengganti Retur".
- Revisi B1: item produk yang sama dalam satu invoice (beda batch / stok minus) digabung jadi 1 baris; qty dan subtotal dijumlah, harga satuan dan rafaksi per unit dirata-rata tertimbang qty.
- Catatan B1: kode selesai, belum diuji di Excel (lihat C). Kelas: `OutletPenjualanExport` (pembungkus), `LaporanToko/OutletPenjualanSheet`, `LaporanToko/OutletPenjualanRekapSheet`. View `exports/outlet/penjualan.blade.php` sudah tidak dipakai export ini.

### B2. Rafaksi Outlet
- [x] Kelompokkan per periode rafaksi.
- [x] Tambah persentase diskon.
- [x] Baris rekap (qty, total diskon, total penjualan) dengan rumus.
- Catatan B2: `OutletRafaksiExport`. Data dari service sudah digabung per produk, jadi pengelompokan memakai kolom Keterangan Rafaksi (nama rafaksi); periode ditampilkan bila nama cocok dengan tepat satu rafaksi. Bila satu rafaksi dipilih, periode laporan mengikuti tanggal rafaksi itu (sama seperti service).

### B3. Retur Penjualan Outlet
- [x] Bedakan barang retur dan barang pengganti dengan warna.
- [x] Warna untuk selisih (positif dan negatif).
- [x] Baris rekap dengan rumus.
- Catatan B3: `OutletReturExport`. Barang retur merah muda, pengganti biru muda, selisih hijau (positif) / merah (negatif).

### B4. All Stock Outlet
- [x] Kelompokkan per outlet dengan subtotal persediaan per outlet.
- [x] Total persediaan keseluruhan (sebelum dan setelah pajak).
- [x] Format Rupiah untuk HPP, pajak, HPP + pajak, persediaan.
- Catatan B4: `OutletAllStockExport` (satu kelas, tanpa pembungkus). Kolom HPP + Pajak dan kedua persediaan memakai rumus per baris. Nama file memakai tanggal posisi (bukan rentang periode).

### B5. Minimal Stock & Alokasi PO
- [x] Kelompokkan kolom: Produk, Penjualan, PO, Stok, Saran.
- [x] Warna status: `out_of_stock` merah, stok kritis kuning, normal hijau.
- [x] Tandai baris dengan perhitungan manual (belum 3 bulan).
- [x] Total produk, jumlah out of stock, total saran PO.
- Catatan B5: `OutletMinimalStockExport`. Status "Kritis" = stok masih di atas minimum tapi ≤ 120% minimum (konstanta `CRITICAL_RATIO`, bisa diubah). Baris manual ditandai biru muda di kolom Min Stock Hasil Hitung dan Status Perhitungan. Urutan kolom disusun ulang sesuai kelompok.

### B6. PR (Permintaan ke Gudang)
- [x] Terapkan gaya baru.
- [x] Tanggal jadi tanggal asli; qty jadi angka, konversi dus dipisah ke kolom sendiri.
- [x] Cek apakah "Kode PO" dan "Keterangan" bisa diisi dari data yang ada.
- [x] Baris total qty.
- Catatan B6: `LaporanPRExport`. Kolom "Kode PO" dihapus karena tabel PR dan PO tidak punya relasi di database (tidak ada data untuk diisi). "Keterangan" diisi dari catatan item, catatan PR, dan catatan verifikasi. Konversi dus jadi kolom sendiri, status berwarna.

### B7. Retur Toko ke Gudang
- [x] Terapkan gaya baru, buang warna dan lebar kolom hardcode.
- [x] Hilangkan nilai "IN" yang hardcode.
- [x] Qty jadi angka; total qty dengan rumus.
- Catatan B7: `ReturOutletExport` (kini menerima `Request`, bukan `$mulai,$selesai,$settings`). Kolom "Jenis Retur" (selalu IN) dihapus; ditambah kolom "Penyelesaian" (Barang/Uang) dari data item; Keterangan kosong diganti itu.

## C. Pengujian dan penutup

- [ ] C1. Uji tiap laporan untuk satu outlet dan semua outlet.
- [ ] C2. Uji dengan data besar (ribuan baris) dan pastikan tidak berat atau timeout.
- [ ] C3. Uji sebagai role superadmin, owner, dan staff-outlet (staff-outlet hanya outlet sendiri).
- [ ] C4. Buka hasil di Excel dan LibreOffice: tampilan, rumus, filter, freeze pane.
- [ ] C5. Pastikan barcode tidak berubah jadi notasi ilmiah.
- [ ] C6. Samakan tampilan PDF dengan Excel jika diperlukan (opsional, tahap terpisah).

## Urutan pengerjaan yang disarankan

1. Fondasi (A)
2. Penjualan (B1), laporan paling sering dipakai
3. All Stock (B4) dan Minimal Stock (B5)
4. Rafaksi (B2) dan Retur (B3)
5. PR (B6) dan Retur Toko ke Gudang (B7)
6. Pengujian (C)

## Note
- Pastikan kirim file yang dibutuhkan saja (jangan zip & jangan di chat)
- Pastikan selalu update todolist setelah selesai