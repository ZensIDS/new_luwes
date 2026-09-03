# Rencana Improvement Cashier, Stock Toko, dan Warehouse

## Tujuan

Membangun aplikasi kasir yang terhubung dengan warehouse untuk banyak outlet.
Setiap outlet memiliki stok, harga jual, transaksi, voucher, dan laporan yang
terpisah, sementara master barang dan aliran barang dari supplier/gudang tetap
terpusat.

Urutan pengerjaan mengikuti kebutuhan bisnis:

1. Validasi rumus harga, diskon, voucher, dan contoh invoice.
2. Menyelesaikan Stock Toko per outlet.
3. Menambahkan aturan Harga Jual dan master voucher.
4. Membangun halaman Penjualan/Cashier POS yang keyboard-first.
5. Menghubungkan laporan, retur, rekonsiliasi kas, dan operasi Local LAN/Online.

## Kondisi project saat ini

### Yang sudah tersedia

- Master `Product`, kategori, supplier, outlet, user, dan role.
- Stok warehouse pada `stocks`, termasuk batch/SKU, HPP, quantity reserved,
  quantity available, status, dan expiry.
- Alur warehouse `Pembelian -> publish -> Stock -> Request Order -> Picking
  List -> Delivery Order`.
- `OwnerStock` dan `StockMovement` sudah mulai dipakai ketika Delivery Order
  dikirim ke outlet.
- Stock opname, kartu stok, activity log, retur, dan export laporan warehouse.
- POS dasar berbasis React: scan barcode, pencarian produk, cart, hold melalui
  wishlist, customer, salesman, checkout, dan cetak penjualan.
- Model voucher sudah memiliki kode, tipe nominal/percentage, nilai, minimum
  pembelian, masa aktif, limit, dan relasi ke penjualan.

### Gap yang harus dibereskan

- `OwnerStockController` masih berisi debug `dd()`, sehingga halaman Stock Toko
  belum dapat dipakai.
- Struktur `owner_stocks` memakai kolom `hpp`, sedangkan model masih mengisi
  `harga_beli`; kontrak field ini harus disatukan.
- Penjualan POS saat ini mengurangi `stocks` warehouse secara langsung. POS
  harus mengurangi stok outlet (`owner_stocks`) dan membuat movement outlet.
- Pengecekan stok cart dan produk masih membaca stok warehouse, bukan stok outlet
  yang sedang dibuka.
- Harga saat ini terutama memakai `Product.harga_jual`; belum ada snapshot
  Harga Akhir, margin, Harga Aktif, Disc Brand, dan Disc Toko.
- Browser menghitung total transaksi. Server belum menjadi sumber perhitungan
  authoritative untuk diskon, voucher, stok, dan total pembayaran.
- Voucher yang ada di controller saat ini dipaksa menjadi nominal, limit diubah
  langsung menjadi `0` setelah dipakai, dan validasi tanggal/minimum pembelian/
  cakupan belum lengkap.
- Field `Penjualan.total` saat ini belum memisahkan dengan jelas grand total,
  uang diterima, dan kembalian.
- Shortcut keyboard, status transaksi, pembayaran, dan rekonsiliasi kas belum
  dirancang sebagai alur POS yang konsisten.

## Prinsip desain

1. **Warehouse dan outlet adalah pemilik stok yang berbeda.** `stocks` adalah
   stok warehouse; `owner_stocks` adalah stok outlet. Penjualan outlet tidak
   boleh langsung mengurangi stok warehouse.
2. **Semua transaksi membawa konteks outlet.** User kasir terikat outlet dan
   outlet pada invoice tidak boleh diubah dari browser tanpa otorisasi.
3. **HPP berasal dari batch yang dijual.** Saat barang datang dari warehouse,
   `OwnerStock` menyimpan referensi batch dan HPP. Jika satu penjualan mengambil
   beberapa batch, alokasi batch harus tersimpan.
4. **Harga dihitung di server.** Frontend hanya membantu preview; backend
   mengulang perhitungan dari master harga, stok, discount rule, voucher, dan
   cart yang tersimpan.
5. **Snapshot untuk audit.** Penjualan menyimpan harga, diskon, voucher, HPP,
   outlet, kasir, dan metode pembayaran pada saat checkout sehingga perubahan
   master data di kemudian hari tidak mengubah invoice lama.
6. **Satu sumber movement.** Inbound, transfer, penjualan, retur, opname, dan
   adjustment harus menghasilkan `StockMovement` yang dapat direkonsiliasi ke
   saldo stok.
7. **Nilai uang konsisten.** Tetapkan satu format penyimpanan nominal (Rupiah
   dengan dua angka desimal atau integer Rupiah) sebelum migrasi, lalu gunakan
   Decimal/server-side calculation tanpa floating point JavaScript sebagai
   sumber akhir.

## Section 1 — Validasi contoh invoice dan rumus kasir

Status: **selesai**. Angka contoh dan aturan voucher sudah divalidasi untuk
implementasi awal.

Dokumen acuan: [table-cashier-penjualan.md](table-cashier-penjualan.md).

### Task

- [x] Buat contoh Google-Sheets-like untuk dua skenario persentase dan nominal.
- [x] Tunjukkan HPP, Disc Brand, Harga Akhir, margin, Harga Aktif, Disc Toko,
      subtotal, voucher, grand total, uang diterima, dan kembalian.
- [x] Pisahkan efek Disc Brand dari diskon yang terlihat oleh pelanggan.
- [x] Tetapkan urutan perhitungan dan aturan pembulatan awal.
- [x] Validasi angka dengan owner/finance; contoh pada dokumen acuan menjadi
      dasar implementasi.
- [x] Voucher boleh digabung dengan Disc Toko dan satu invoice boleh memakai
      beberapa voucher. Setiap voucher memiliki kode unik dan hanya dapat
      digunakan satu kali. Admin cukup mengisi jumlah voucher; backend membuat
      varian kode unik secara otomatis.
- [x] Voucher yang dipakai dicatat sebagai beban pada kasir yang menerapkannya
      dari halaman POS. Identitas kasir disimpan pada ledger redemption untuk
      laporan dan audit.

### Acceptance criteria

- Contoh persentase menghasilkan Grand Total Rp337.689 dan kembalian Rp62.311.
- Contoh nominal menghasilkan Grand Total Rp326.000 dan kembalian Rp24.000.
- Semua total dapat dihitung ulang hanya dari kolom input dan aturan yang
  tertulis.
- Keputusan final Section 1 dicatat sebelum migration/code POS dibuat.

## Section 2 — Stock Toko per outlet

Tujuan: menyediakan saldo stok, kartu stok, dan stock opname yang benar-benar
terpisah untuk setiap outlet.

Flow halaman yang digunakan:

```text
Stock Gudang (/stock)
        │ Delivery Order (Outbound)
        ▼
Stock Toko (/owner-stocks) ──► Penjualan POS (/penjualan/create)
        ▲
        └── Belanja Langsung Toko (/outlet-purchases/create), hanya jalur alternatif
```

`Master Harga Jual` (`/outlet-prices`) bukan halaman stok. Halaman tersebut
hanya mengatur formula harga POS per outlet/produk.

### 2.1 Model dan alur stok

- [x] Rapikan kontrak `owner_stocks`: `hpp` menjadi nama kanonik; field sumber
      dan index outlet ditambahkan.
- [x] Tetapkan identitas stok: `owner_id = outlet_id`, `product_id`, batch/SKU,
      expiry, HPP, dan quantity.
- [x] Tambahkan `OutletStockService`; service menangani lock, validasi saldo,
      update saldo, dan `StockMovement` dalam satu database transaction.
- [x] Pertahankan referensi asal warehouse (`stock_id`/batch) pada stok outlet.
- [x] Sediakan status movement yang jelas: transfer masuk, pembelian langsung,
      penjualan, retur pelanggan, retur ke gudang, opname, dan adjustment.
- [x] Pastikan soft delete tidak menghilangkan histori movement dan invoice.

### 2.2 Barang masuk ke outlet

- [x] **Dari Gudang:** gunakan Delivery Order sebagai alur utama outbound untuk
      menambah `OwnerStock`, dengan quantity yang benar-benar diterima outlet.
- [x] **Langsung Belanja:** tambahkan pembelian langsung di outlet sebagai jalur
      alternatif ketika outlet membeli dari supplier, bukan sebagai pengganti
      Delivery Order.
      Supplier wajib tercatat, bersama nomor nota/tanggal, item, quantity,
      nominal harga beli, pembayaran, dan user/outlet yang melakukan transaksi.
- [x] Pembelian langsung membuat batch `OwnerStock` melalui dokumen penerimaan
      outlet, tanpa memalsukan transfer warehouse.
- [x] Hubungkan supplier dan nominal pembelian ke dokumen pembelian langsung
      dan HPP `OwnerStock`.
- [ ] Tambahkan pembatalan/retur pembelian langsung dengan audit trail.

### 2.3 Halaman Stock Toko

- [x] Ganti debug `dd()` pada `OwnerStockController` dengan halaman index yang
      hanya menampilkan outlet yang boleh diakses user.
- [x] Tabel per outlet: kode, produk, batch/SKU, expiry, HPP, quantity masuk,
      terjual, adjustment, dan saldo tersedia.
- [x] Filter/search nama/kode/batch dan outlet; filter kategori/status expiry
      masih menjadi follow-up kecil.
- [x] Detail kartu stok per produk/outlet dengan running balance dan referensi
      transaksi.
- [x] Gunakan FIFO per batch untuk penjualan bulk, dengan stok expired tidak
      dialokasikan otomatis.
- [ ] Tambahkan peringatan stok minimum per outlet; jangan memakai total
      warehouse sebagai saldo outlet.

### 2.4 Stock opname toko

- [x] Salin alur stock opname warehouse yang sudah ada, tetapi setiap sesi wajib
      memiliki `owner_id/outlet_id`.
- [x] Snapshot quantity sistem saat data opname dimuat; input quantity fisik; hitung
      selisih; minta alasan; dan simpan user pemeriksa/persetuju.
- [x] Posting selisih melalui `StockMovement` adjustment.
- [ ] Cegah dua sesi aktif untuk produk/batch/outlet yang sama.
- [ ] Sediakan export/import template dan laporan hasil opname per outlet.

### Acceptance criteria

- Penjualan di Outlet A tidak mengubah saldo `OwnerStock` Outlet B atau saldo
  warehouse selain melalui alur transfer yang sah.
- Saldo kartu stok sama dengan saldo `OwnerStock` setelah inbound, penjualan,
  retur, dan opname.
- HPP dan batch yang dijual dapat ditelusuri ke Delivery Order atau pembelian
  langsung.
- Kasir hanya melihat dan menjual stok outletnya sendiri.

## Section 3 — Harga Jual dan Voucher Toko

Section ini menjadi prerequisite bisnis untuk checkout POS, walaupun UI dapat
dikerjakan setelah fondasi Stock Toko stabil.

### 3.1 Master Harga

- [x] Buat model price list untuk `product_id` dan `outlet_id`, dengan fallback
      ke harga global bila rule outlet belum tersedia.
- [x] Simpan tanggal mulai berlaku, tanggal berakhir, status aktif, dan pembuat.
      Riwayat versi penuh masih menjadi follow-up karena master saat ini satu
      rule aktif per outlet/produk.
- [x] Field per item minimal: HPP source, Disc Brand type/value, Harga Akhir,
      margin type/value, Harga Aktif, Disc Toko type/value, dan harga netto.
- [x] Sediakan service kalkulator yang hasilnya sama dengan
      [contoh Section 1](table-cashier-penjualan.md).
- [x] Validasi bahwa Disc Brand mengubah dasar HPP, margin dihitung dari Harga
      Akhir, dan Disc Toko dikurangkan dari Harga Aktif.
- [ ] Sediakan import/export spreadsheet untuk perubahan harga massal dengan
      preview dan approval.
- [x] Simpan snapshot semua komponen harga di `PenjualanItem` saat checkout.

### 3.2 Voucher Toko

- [x] Pertahankan barcode/kode unik, tipe nominal/percentage, nilai, minimum
      pembelian, periode aktif, limit, product/brand scope, outlet scope, dan
      status.
- [x] Tambahkan ledger penggunaan voucher atau relasi redemption. Jangan hanya
      mengubah `limit` menjadi `0`, agar riwayat penggunaan, pembatalan, dan
      audit tetap tersedia.
- [x] Stackability: voucher dapat digabung dengan Disc Toko dan beberapa
      voucher dapat dipakai dalam satu invoice. `max_discount_amount` tetap
      tersedia sebagai batas opsional untuk voucher persentase.
- [x] Penanggung voucher untuk laporan awal adalah kasir yang menerapkan
      voucher di POS; redemption menyimpan `cashier_id`, outlet, invoice, kode,
      tipe, nilai, dan nominal potongan.
- [x] Saat scan, tampilkan nama, nilai, syarat, periode, dan potongan yang
      benar-benar akan diterapkan.
- [x] Saat checkout, lock/redemption voucher dilakukan bersama penyimpanan
      invoice agar dua kasir tidak memakai voucher yang sama.
- [x] Simpan snapshot kode dan nilai voucher di penjualan melalui
      `voucher_redemptions`.

### Acceptance criteria

- Harga dari master menghasilkan angka yang sama dengan dua versi contoh.
- Voucher yang expired, tidak memenuhi minimum pembelian, di luar outlet, atau
  sudah habis limit ditolak server.
- Perubahan master harga/voucher tidak mengubah invoice lama.

## Section 4 — Halaman Penjualan / Cashier POS

Tujuan: alur kasir cepat dengan keyboard sebagai jalur utama dan mouse sebagai
opsi tambahan.

### 4.1 Layout dan shortcut keyboard

Shortcut berikut menjadi baseline POS dan tetap dapat diuji ulang dengan kasir di
lapangan:

| Shortcut | Aksi |
|---|---|
| `Enter` | Submit barcode/search dan tambah item |
| `F2` | Fokus pencarian produk |
| `F3` | Fokus scan barcode |
| `F4` | Fokus customer |
| `F5` | Fokus tabel item dan pilih baris pertama |
| `F6` | Fokus Disc Toko/manual adjustment sesuai hak akses |
| `F7` | Fokus metode pembayaran |
| `F8` | Fokus scan/input voucher |
| `F9` | Buka pembayaran |
| `F10` | Konfirmasi checkout |
| `Esc` | Tutup dialog/batalkan input yang belum disimpan |
| `+` / `-` | Tambah/kurangi quantity item terpilih |
| `Delete` | Hapus item terpilih setelah konfirmasi ringan |
| `Ctrl+Backspace` / `Ctrl+Delete` | Hapus item terakhir |
| `Tab` / `Shift+Tab` | Pindah baris item berikutnya/sebelumnya saat baris cart aktif |
| `↑` / `↓` atau `Alt+↑` / `Alt+↓` | Pindah dan pilih baris item |
| `Home` / `End` | Pilih baris item pertama/terakhir |
| `Ctrl+H` | Hold transaksi |
| `Ctrl+L` | Buka transaksi hold |
| `Ctrl+P` | Cetak ulang invoice terakhir sesuai izin |

Task:

- [x] Autofocus scanner saat halaman dibuka dan setelah item berhasil masuk.
- [x] Pastikan barcode scanner yang mengirim `Enter` tidak memicu submit
      halaman yang salah.
- [x] Sediakan navigasi item, edit quantity, hapus baris, hold, recall, voucher,
      payment, dan print tanpa berpindah ke mouse.
- [x] Tampilkan feedback visual untuk item berhasil, stok kurang,
      barcode tidak dikenal, dan voucher ditolak.
- [x] Dukung barang serialized dengan pemilihan serial dan quantity selalu satu.
- [x] Jangan mengandalkan `console.log` atau state browser sebagai catatan
      transaksi.

### 4.2 Alur checkout

- [x] Pilih outlet dari route/session yang sudah diotorisasi.
- [x] Cari/scan produk dari `OwnerStock` outlet aktif.
- [x] Simpan cart sementara dengan owner/outlet dan batch yang sesuai.
- [x] Preview HPP, harga, Disc Toko, voucher, subtotal, grand total, dan
      kembalian memakai service kalkulasi yang sama dengan backend.
- [x] Saat process, buka database transaction dan lock stok yang digunakan.
- [x] Validasi ulang saldo outlet, harga aktif, discount permission, voucher,
      customer, dan metode pembayaran.
- [x] Alokasikan batch outlet, kurangi `OwnerStock`, buat `Penjualan` dan
      `PenjualanItem` snapshot, buat `StockMovement`, lalu commit.
- [x] Jika salah satu langkah gagal, seluruh checkout rollback dan cart tetap
      aman untuk dicoba lagi.
- [x] Setelah sukses, kosongkan cart dan tampilkan invoice; POS menyediakan
      fokus barcode saat alur kembali dibuka.

### 4.3 Pembayaran, kas, dan invoice

- [x] Pisahkan `subtotal`, total diskon item, total voucher, `grand_total`,
      `paid_amount`, dan `change_amount`.
- [x] Sediakan payment method yang tersedia di master, termasuk tunai sebagai
      fallback.
- [ ] Dukung split payment bila diperlukan.
- [ ] Catat kas/outlet, kasir, waktu buka/tutup shift, dan nomor transaksi.
- [ ] Tambahkan tutup kas: expected cash, counted cash, selisih, alasan, dan
      approval.
- [x] Invoice menampilkan produk, qty, harga netto, diskon, voucher, total,
      pembayaran, kembalian, outlet, kasir, dan kode voucher.
- [ ] Pertahankan retur penjualan dengan referensi invoice dan pengembalian
      quantity ke outlet stock melalui movement.

### 4.4 Laporan POS

- [ ] Penjualan per outlet, kasir, shift, produk, brand, tanggal, metode bayar.
- [ ] Pemakaian voucher dan nilai diskon brand/store/voucher secara terpisah.
- [ ] Penjualan, HPP efektif, margin sebelum voucher, margin setelah voucher.
- [ ] Rekonsiliasi `PenjualanItem` dengan kartu stok outlet.
- [ ] Export laporan dengan snapshot data transaksi, bukan harga master terbaru.

## Section 5 — Local LAN dan Online

### Tahap awal yang direkomendasikan

- [ ] Jalankan satu server aplikasi/database pada jaringan outlet; terminal
      kasir mengakses server melalui LAN.
- [ ] Jadikan service kalkulasi, stock lock, dan checkout idempotent agar aman
      saat dua terminal menjual barang yang sama.
- [ ] Tambahkan health check untuk koneksi server, database, queue (bila
      dipakai), dan printer.
- [ ] Pastikan deployment online memakai HTTPS, backup database, role access,
      audit log, dan monitoring.

### Offline penuh (fase lanjutan)

- [ ] Jangan menganggap Local LAN otomatis berarti offline. Offline memerlukan
      local database, antrean transaksi, sinkronisasi conflict, dan strategi
      nomor invoice.
- [ ] Rancang offline hanya setelah alur online/LAN stabil dan aturan konflik
      stok/voucher disetujui.

## Urutan deliverable

| Tahap | Deliverable | Status |
|---|---|---|
| 1 | Tabel kalkulasi dan contoh invoice pada `docs/table-cashier-penjualan.md` | Selesai |
| 2 | Persetujuan rumus, pembulatan, voucher, dan margin | Selesai |
| 3 | Perbaikan model/service Stock Toko dan pembelian langsung | Selesai |
| 4 | Halaman list, kartu stok, dan opname per outlet | Selesai |
| 5 | Master Harga Jual dan voucher dengan snapshot/ledger | Selesai untuk fondasi awal |
| 6 | POS checkout server-authoritative dan keyboard-first | Selesai untuk fondasi awal |
| 7 | Pembayaran, shift kas, invoice, retur, dan laporan | Sebagian; follow-up |
| 8 | Hardening Local LAN, online deployment, backup, dan monitoring | Tidak dikerjakan sesuai instruksi |

## Definition of done untuk fase POS pertama

- Kasir dapat scan barcode, mengubah quantity, memakai beberapa voucher, membayar,
  dan mencetak invoice tanpa mouse.
- Harga pada invoice cocok dengan rumus di Section 1.
- Stok yang berkurang adalah stok outlet yang benar dan kartu stok mencatat
  movement yang sama.
- Dua kasir tidak dapat menjual quantity outlet yang sama melebihi saldo.
- Voucher tidak dapat digunakan di luar syarat atau dipakai dua kali secara
  bersamaan.
- Invoice lama tetap sama walaupun master harga, HPP, atau voucher diubah.
- Feature test mencakup kalkulasi dua versi, voucher, concurrency stok,
  pembelian langsung, transfer gudang, opname, retur, permission, dan checkout.
