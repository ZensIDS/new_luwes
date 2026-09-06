# Tabel Contoh Perhitungan Kasir Penjualan

Dokumen ini menjadi contoh perhitungan yang harus disepakati sebelum halaman
Harga Jual dan POS diimplementasikan. Semua angka menggunakan Rupiah (Rp),
tanpa pajak atau biaya layanan.

## Hasil pengecekan dua contoh yang sudah ada

Kedua contoh di bawah sudah konsisten secara aritmetika. `Total (referensi
harga/unit)` bukan total transaksi karena belum dikalikan quantity; angka yang
dipakai untuk pembayaran adalah subtotal pada tingkat transaksi.

| Contoh | Subtotal sebelum voucher | Voucher | Grand Total | Status |
|---|---:|---:|---:|---|
| Versi 1 — persentase | Rp375.210 | 10% = Rp37.521 | **Rp337.689** | Benar |
| Versi 2 — nominal | Rp376.000 | Rp50.000 | **Rp326.000** | Benar |

Kontrol silangnya juga benar:

- Versi 1: HPP efektif Rp318.000, sehingga margin sebelum voucher Rp57.210
  dan sesudah voucher Rp19.689.
- Versi 2: HPP efektif Rp318.000, sehingga margin sebelum voucher Rp58.000
  dan sesudah voucher Rp8.000.

Contoh tersebut sudah diuji melalui `PriceCalculatorTest`. Tiga pengujian
untuk harga persentase, harga nominal, dan voucher bertingkat berhasil.

## Urutan perhitungan

Harga dan diskon harus dihitung dalam urutan berikut. `Disc Brand` bukan
diskon yang diberikan lagi kepada pelanggan; nilainya mengubah dasar HPP agar
margin penjualan tidak berkurang karena diskon brand.

| Langkah | Rumus | Hasil |
|---|---|---|
| 1. HPP/Harga Beli | Diambil dari batch stok yang diterima outlet dari gudang | `HPP` |
| 2. Disc Brand | Persentase: `HPP × persentase`; nominal: nilai input | `Disc Brand` |
| 3. Harga Akhir | `HPP - Disc Brand` | Dasar perhitungan margin |
| 4. Margin | Persentase: `Harga Akhir × persentase`; nominal: nilai input | `Margin` |
| 5. Harga Aktif | `Harga Akhir + Margin` | Harga jual sebelum diskon toko |
| 6. Disc Toko | Persentase: `Harga Aktif × persentase`; nominal: nilai input | Diskon untuk pelanggan per item |
| 7. Harga Netto Item | `Harga Aktif - Disc Toko` | Harga setelah diskon toko |
| 8. Subtotal Item | `Qty × Harga Netto Item` | Nilai item pada transaksi |
| 9. Voucher Toko | Dihitung atas subtotal setelah `Disc Toko` | Pengurang total belanja |
| 10. Grand Total | `max(0, Subtotal Penjualan - Voucher)` | Total yang harus dibayar |

### Aturan pembulatan dan validasi

- Nilai persentase disimpan sebagai angka `0` sampai `100`; nominal disimpan
  dalam Rupiah. Tipe diskon wajib disimpan terpisah dari nilainya.
- Untuk implementasi awal, pembulatan dilakukan ke Rupiah terdekat pada
  diskon dan harga per unit, lalu subtotal dihitung dari harga unit yang sudah
  dibulatkan. Voucher dibulatkan ke Rupiah terdekat setelah basis voucher
  ditentukan.
- `Disc Brand`, `Margin`, dan `Disc Toko` tidak boleh menghasilkan harga
  negatif. `Disc Toko` tidak boleh lebih besar dari `Harga Aktif`.
- Voucher hanya dapat dipakai jika masih aktif, belum melewati limit, memenuhi
  minimum pembelian, dan kode/barcode-nya valid. Nilai voucher tidak boleh
  membuat Grand Total menjadi negatif.
- Backend harus menghitung ulang seluruh nilai dari item dan konfigurasi harga.
  Nilai total dari browser hanya dipakai sebagai tampilan dan tidak dipercaya.

## Gambaran alur harga dan promo

Alur dasar yang dipakai dua contoh di bawah ini adalah:

    HPP batch
      -> Disc Brand
      -> Harga Akhir
      -> Margin
      -> Harga Aktif
      -> Disc Toko
      -> Harga Netto Item
      -> Subtotal Penjualan
      -> Voucher
      -> Grand Total

Perluasan promo menambahkan dua lapisan sebelum Grand Total:

    Harga Netto Item
      -> Promo item: flash sale atau harga bertingkat
      -> Subtotal item promo
      -> Promo keranjang: bundling atau minimal belanja
      -> Voucher yang eligible
      -> Grand Total

Promo item dan promo keranjang tidak boleh mengurangi basis yang sama dua kali.
Setiap potongan harus menyimpan `source`, produk/baris yang terkena, basis,
prioritas, dan nominal aktualnya agar kasir serta laporan dapat melihat asal
potongan.

## Versi 1 — seluruh diskon dan voucher menggunakan persentase

Contoh ini memakai `Disc Brand` dan `Disc Toko` persentase, margin persentase,
serta voucher persentase.

### Detail harga per item

| Produk | Qty | HPP | Disc Brand | Harga Akhir | Margin | Harga Aktif | Disc Toko | Harga Netto | Subtotal |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Produk A | 2 | Rp100.000 | 10% = Rp10.000 | Rp90.000 | 25% = Rp22.500 | Rp112.500 | 5% = Rp5.625 | Rp106.875 | Rp213.750 |
| Produk B | 3 | Rp50.000 | 8% = Rp4.000 | Rp46.000 | 30% = Rp13.800 | Rp59.800 | 10% = Rp5.980 | Rp53.820 | Rp161.460 |
| **Total (referensi harga/unit)** | **5** |  |  |  |  | **Rp172.300** | **Rp11.605** |  | **Rp375.210** |

`Rp172.300` adalah penjumlahan Harga Aktif per unit (`Rp112.500 +
Rp59.800`) dan `Rp11.605` adalah penjumlahan Disc Toko per unit
(`Rp5.625 + Rp5.980`). Nilai yang dipakai untuk total transaksi tetap
memperhitungkan quantity: Harga Aktif Rp404.400, Disc Toko Rp29.190, dan
subtotal Rp375.210.

Perhitungan Produk A:

    Disc Brand        = Rp100.000 × 10% = Rp10.000
    Harga Akhir       = Rp100.000 - Rp10.000 = Rp90.000
    Margin            = Rp90.000 × 25% = Rp22.500
    Harga Aktif       = Rp90.000 + Rp22.500 = Rp112.500
    Disc Toko         = Rp112.500 × 5% = Rp5.625
    Harga Netto       = Rp112.500 - Rp5.625 = Rp106.875
    Subtotal Produk A = 2 × Rp106.875 = Rp213.750

Perhitungan Produk B:

    Disc Brand        = Rp50.000 × 8% = Rp4.000
    Harga Akhir       = Rp50.000 - Rp4.000 = Rp46.000
    Margin            = Rp46.000 × 30% = Rp13.800
    Harga Aktif       = Rp46.000 + Rp13.800 = Rp59.800
    Disc Toko         = Rp59.800 × 10% = Rp5.980
    Harga Netto       = Rp59.800 - Rp5.980 = Rp53.820
    Subtotal Produk B = 3 × Rp53.820 = Rp161.460

### Voucher dan total pembayaran

Voucher yang dipindai: `V-HEMAT10`, tipe `percentage`, nilai `10%`, minimum
pembelian Rp300.000.

| Komponen | Rumus | Nilai |
|---|---|---:|
| Total Harga Aktif | `(2 × Rp112.500) + (3 × Rp59.800)` | Rp404.400 |
| Total Disc Toko | `(2 × Rp5.625) + (3 × Rp5.980)` | -Rp29.190 |
| Subtotal Penjualan | `Rp404.400 - Rp29.190` | Rp375.210 |
| Voucher `V-HEMAT10` | `Rp375.210 × 10%` | -Rp37.521 |
| **Grand Total** | `Rp375.210 - Rp37.521` | **Rp337.689** |
| Uang Diterima |  | Rp400.000 |
| **Kembalian** | `Rp400.000 - Rp337.689` | **Rp62.311** |

Kontrol margin untuk laporan:

    HPP efektif setelah Disc Brand  = (2 × Rp90.000) + (3 × Rp46.000) = Rp318.000
    Margin terealisasi sebelum voucher = Rp375.210 - Rp318.000 = Rp57.210
    Margin terealisasi setelah voucher  = Rp337.689 - Rp318.000 = Rp19.689

Voucher mengurangi penerimaan dari pelanggan, sehingga secara default juga
mengurangi margin terealisasi. Jika suatu voucher dibiayai brand, sumber dana
tersebut perlu dicatat sebagai aturan akuntansi terpisah.

## Versi 2 — seluruh diskon dan voucher menggunakan nominal

Contoh ini memakai nominal Rupiah untuk `Disc Brand`, margin, `Disc Toko`, dan
voucher.

### Detail harga per item

| Produk | Qty | HPP | Disc Brand | Harga Akhir | Margin | Harga Aktif | Disc Toko | Harga Netto | Subtotal |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Produk A | 2 | Rp100.000 | Rp10.000 | Rp90.000 | Rp25.000 | Rp115.000 | Rp5.000 | Rp110.000 | Rp220.000 |
| Produk B | 3 | Rp50.000 | Rp4.000 | Rp46.000 | Rp14.000 | Rp60.000 | Rp8.000 | Rp52.000 | Rp156.000 |
| **Total (referensi harga/unit)** | **5** |  |  |  |  | **Rp175.000** | **Rp13.000** |  | **Rp376.000** |

`Rp175.000` adalah penjumlahan Harga Aktif per unit (`Rp115.000 +
Rp60.000`) dan `Rp13.000` adalah penjumlahan Disc Toko per unit
(`Rp5.000 + Rp8.000`). Nilai yang dipakai untuk total transaksi tetap
memperhitungkan quantity: Harga Aktif Rp410.000, Disc Toko Rp34.000, dan
subtotal Rp376.000.

Perhitungan Produk A:

    Disc Brand        = Rp10.000
    Harga Akhir       = Rp100.000 - Rp10.000 = Rp90.000
    Margin            = Rp25.000
    Harga Aktif       = Rp90.000 + Rp25.000 = Rp115.000
    Disc Toko         = Rp5.000
    Harga Netto       = Rp115.000 - Rp5.000 = Rp110.000
    Subtotal Produk A = 2 × Rp110.000 = Rp220.000

Perhitungan Produk B:

    Disc Brand        = Rp4.000
    Harga Akhir       = Rp50.000 - Rp4.000 = Rp46.000
    Margin            = Rp14.000
    Harga Aktif       = Rp46.000 + Rp14.000 = Rp60.000
    Disc Toko         = Rp8.000
    Harga Netto       = Rp60.000 - Rp8.000 = Rp52.000
    Subtotal Produk B = 3 × Rp52.000 = Rp156.000

### Voucher dan total pembayaran

Voucher yang dipindai: `V-RP50000`, tipe `nominal`, nilai Rp50.000, minimum
pembelian Rp300.000.

| Komponen | Rumus | Nilai |
|---|---|---:|
| Total Harga Aktif | `(2 × Rp115.000) + (3 × Rp60.000)` | Rp410.000 |
| Total Disc Toko | `(2 × Rp5.000) + (3 × Rp8.000)` | -Rp34.000 |
| Subtotal Penjualan | `Rp410.000 - Rp34.000` | Rp376.000 |
| Voucher `V-RP50000` | Nilai nominal voucher | -Rp50.000 |
| **Grand Total** | `Rp376.000 - Rp50.000` | **Rp326.000** |
| Uang Diterima |  | Rp350.000 |
| **Kembalian** | `Rp350.000 - Rp326.000` | **Rp24.000** |

Kontrol margin untuk laporan:

    HPP efektif setelah Disc Brand  = (2 × Rp90.000) + (3 × Rp46.000) = Rp318.000
    Margin terealisasi sebelum voucher = Rp376.000 - Rp318.000 = Rp58.000
    Margin terealisasi setelah voucher  = Rp326.000 - Rp318.000 = Rp8.000

## Kasus promo tambahan — usulan sebelum implementasi

Bagian ini menerjemahkan pembicaraan tentang flash sale, bundling, harga
bertingkat, dan event menjadi contoh angka. Semua ini masih aturan bisnis yang
perlu disepakati; belum mengubah kode.

### 1. Flash sale berdasarkan waktu

Produk Minyak Sanco memiliki Harga Netto Item reguler Rp20.000. Flash sale
aktif pukul 09.00–12.00 pada 9 September 2026 dengan diskon 15% dan maksimum
100 unit.

| Komponen | Perhitungan | Nilai |
|---|---|---:|
| Qty | 2 pcs | 2 |
| Harga reguler | 2 × Rp20.000 | Rp40.000 |
| Diskon flash sale | 2 × (Rp20.000 × 15%) | -Rp6.000 |
| **Subtotal item promo** | `Rp40.000 - Rp6.000` | **Rp34.000** |

Harga promo per unit adalah Rp17.000. Di luar periode 09.00–12.00, atau
setelah kuota 100 unit habis, harga kembali ke Rp20.000. Waktu yang dipakai
harus waktu server/outlet yang disepakati, bukan waktu dari browser kasir.

### 2. Flash sale berdasarkan quantity

Harga Netto Item reguler Minyak Sanco adalah Rp20.000. Promo memberi harga
Rp17.000 untuk maksimal 10 pcs per transaksi.

| Bagian quantity | Harga per unit | Subtotal |
|---|---:|---:|
| 10 pcs yang memenuhi promo | Rp17.000 | Rp170.000 |
| 2 pcs sisanya | Rp20.000 | Rp40.000 |
| **Total 12 pcs** |  | **Rp210.000** |

Tanpa promo, 12 pcs bernilai Rp240.000, jadi penghematannya Rp30.000. Sistem
harus memilih salah satu aturan berikut sebelum implementasi:

1. `max_qty` berlaku per transaksi; quantity di atas batas tetap memakai harga
   reguler seperti contoh.
2. `max_qty` berlaku sebagai batas pembelian; kasir mendapat alert dan harus
   mengurangi quantity agar checkout dapat dilanjutkan.

Usulan default: gunakan pilihan pertama agar quantity tambahan tetap dapat
   dijual dengan harga reguler dan perhitungan mudah diaudit.

### 3. Bundling beli quantity tertentu

Aturan: beli 2 pcs Minyak Sanco dalam satu bundle mendapat potongan
Rp35.000. Harga reguler adalah Rp20.000 per pcs.

| Qty | Perhitungan | Total |
|---:|---|---:|
| 2 | `Rp40.000 - Rp35.000 potongan` | Rp5.000 |
| 4 | `Rp80.000 - 2 × Rp35.000 potongan` | Rp10.000 |
| 5 | `Rp100.000 - 2 × Rp35.000 potongan` | Rp30.000 |

Untuk bundle lintas produk, misalnya Produk B dan Produk C, aturan perlu
menyimpan quantity minimum setiap produk. Baris yang sudah dipakai untuk satu
bundle tidak boleh dipakai lagi untuk bundle lain atau voucher produk yang
overlap, kecuali aturan promo memang menyatakan boleh.

### 4. Harga bertingkat pcs/karton dengan konfirmasi

Produk A memiliki harga pcs Rp20.000 dan harga karton (12 pcs) Rp210.000.
Ketika quantity mencapai 12 pcs, halaman pesanan menampilkan konfirmasi:

    "Quantity 12 pcs memenuhi harga khusus 1 karton Rp210.000.
     Gunakan harga karton? [Gunakan] [Tetap harga pcs]"

| Pilihan kasir | Perhitungan 12 pcs | Total | Penghematan |
|---|---|---:|---:|
| Tetap harga pcs | `12 × Rp20.000` | Rp240.000 | Rp0 |
| Gunakan harga karton | `1 × Rp210.000` | **Rp210.000** | **Rp30.000** |

Konfirmasi harus terekam pada transaksi. Untuk 24 pcs, sistem dapat menawarkan
`2 karton = Rp420.000` dan untuk quantity sisa di luar kelipatan karton dapat
menggunakan harga pcs. Harga karton tidak boleh diterapkan diam-diam karena
perubahan satuan dapat memengaruhi stok, margin, dan ekspektasi pelanggan.

### 5. Minimal belanja

Aturan event: belanja minimal Rp100.000 mendapat potongan Rp10.000.

| Kondisi | Basis | Potongan | Total setelah promo |
|---|---:|---:|---:|
| Total belanja Rp99.000 | Rp99.000 | Rp0 | Rp99.000 |
| Total belanja Rp100.000 | Rp100.000 | -Rp10.000 | **Rp90.000** |

Minimum belanja dihitung setelah promo item (flash sale/harga bertingkat) dan
sebelum voucher keranjang. Dengan begitu pelanggan tidak kehilangan kelayakan
karena nominal voucher yang baru diterapkan.

### 6. Beli X gratis Y

Aturan event: beli 2 pcs Minyak Sanco, gratis 1 pcs. Harga reguler adalah
Rp20.000 per pcs.

| Qty diambil dari stok | Qty dibayar | Perhitungan | Total |
|---:|---:|---|---:|
| 3 pcs | 2 pcs | `2 × Rp20.000`, 1 pcs gratis | **Rp40.000** |
| 6 pcs | 4 pcs | `4 × Rp20.000`, 2 pcs gratis | **Rp80.000** |

Barang gratis tetap mengurangi stok dan HPP. Karena itu POS perlu menyimpan
quantity yang diambil, quantity yang dibayar, serta nominal diskon gratisnya;
jangan hanya menyimpan quantity dibayar agar kartu stok dan margin tetap benar.

## Perbaikan voucher dan kombinasi promo

### Contoh gabungan promo flash sale dan voucher

Contoh berikut menunjukkan bahwa voucher dihitung setelah harga flash sale,
bukan dari harga reguler. Promo flash sale aktif pukul 09.00–12.00.

#### 1. Flash sale berdasarkan waktu + voucher toko

Minyak Sanco mendapat flash sale 15% untuk 2 pcs. Produk B tidak mengikuti
flash sale. Voucher `TOKO-NATAL` bernilai Rp15.000 dengan minimum pembelian
Rp100.000.

| Komponen | Perhitungan | Harga reguler | Potongan promo/voucher | Nilai setelah potongan |
|---|---|---:|---:|---:|
| Minyak Sanco, 2 pcs | `2 × Rp20.000` | Rp40.000 | Flash sale 15% = -Rp6.000 | Rp34.000 |
| Produk B, 2 pcs | `2 × Rp35.000` | Rp70.000 |  | Rp70.000 |
| **Subtotal setelah flash sale** | `Rp34.000 + Rp70.000` | **Rp110.000** |  | **Rp104.000** |
| Voucher `TOKO-NATAL` | minimum terpenuhi |  | -Rp15.000 | -Rp15.000 |
| **Grand Total** | `Rp104.000 - Rp15.000` |  |  | **Rp89.000** |

Total penghematan adalah Rp21.000: Rp6.000 dari flash sale dan Rp15.000 dari
voucher. Jika transaksi dibuat di luar periode flash sale, basis voucher menjadi
Rp110.000 dan Grand Total menjadi Rp95.000.

#### 2. Flash sale berdasarkan quantity + dua voucher

Minyak Sanco memiliki harga reguler Rp20.000. Maksimal 10 pcs mendapat harga
flash sale Rp17.000; 2 pcs berikutnya kembali ke harga reguler. Setelah itu,
voucher marketplace 10% diterapkan lebih dahulu, lalu voucher toko nominal
Rp15.000. Voucher toko memiliki minimum pembelian Rp180.000.

| Komponen | Perhitungan | Nilai |
|---|---|---:|
| 10 pcs flash sale | `10 × Rp17.000` | Rp170.000 |
| 2 pcs harga reguler | `2 × Rp20.000` | Rp40.000 |
| **Subtotal setelah flash sale** | `Rp170.000 + Rp40.000` | **Rp210.000** |
| Voucher marketplace `TOPED-9.9` | `Rp210.000 × 10%` | -Rp21.000 |
| Sisa basis setelah voucher marketplace | `Rp210.000 - Rp21.000` | Rp189.000 |
| Voucher toko `TOKO-NATAL` | minimum Rp180.000 terpenuhi | -Rp15.000 |
| **Grand Total** | `Rp210.000 - Rp21.000 - Rp15.000` | **Rp174.000** |

Tanpa flash sale dan voucher, 12 pcs bernilai Rp240.000. Pada contoh ini,
flash sale menghemat Rp30.000 dan dua voucher menghemat Rp36.000, sehingga
total penghematan adalah Rp66.000.

### Voucher marketplace + voucher toko

Contoh dari pembicaraan: total belanja Rp100.000 mendapat voucher marketplace
Rp10.000 dan voucher toko Rp15.000.

| Komponen | Perhitungan | Nilai |
|---|---|---:|
| Subtotal setelah promo item |  | Rp100.000 |
| Voucher marketplace `TOPED-9.9` | nominal | -Rp10.000 |
| Voucher toko `TOKO-NATAL` | nominal | -Rp15.000 |
| **Grand Total** | `Rp100.000 - Rp10.000 - Rp15.000` | **Rp75.000** |

Contoh ini menghasilkan Rp75.000 karena kedua voucher nominal. Untuk voucher
persentase, urutan harus ditentukan oleh sistem dan ditampilkan kepada kasir;
misalnya 10% lalu 15% menghasilkan `Rp100.000 - Rp10.000 - Rp13.500 =
Rp76.500`, bukan Rp75.000.

### Voucher produk + voucher bundling

Contoh lain: Produk A mendapat voucher Rp15.000, sedangkan Produk B dan C yang
memenuhi bundling mendapat voucher Rp20.000.

| Kelompok item | Nilai awal | Voucher/promo | Nilai setelah potongan |
|---|---:|---:|---:|
| Produk A | Rp30.000 | -Rp15.000 | Rp15.000 |
| Produk B + C | Rp70.000 | -Rp20.000 | Rp50.000 |
| **Total** | **Rp100.000** | **-Rp35.000** | **Rp65.000** |

Aturan validasinya:

- Voucher produk hanya memakai subtotal produk yang ditentukan.
- Voucher bundling hanya aktif jika seluruh syarat bundle terpenuhi.
- Basis dua promo tidak boleh overlap. Jika overlap memang diizinkan, harus ada
  `priority` atau pilihan `best discount`; backend tidak boleh bergantung pada
  urutan kode yang dipindai.
- Nominal setiap potongan, basisnya, dan sisa basis setelah potongan dicatat
  pada detail transaksi.

### Event sebagai wadah promo

Event seperti `Natal 2026` dapat menjadi wadah untuk beberapa aturan sekaligus:

| Pengaturan event | Contoh |
|---|---|
| Nama dan periode | Natal 2026, 1–31 Desember 2026 |
| Outlet/channel | Outlet A dan penjualan marketplace |
| Produk yang ikut | Minyak Sanco, Produk B, Produk C |
| Kuota | 100 bundle atau 500 unit flash sale |
| Aturan | flash sale, bundling, minimal belanja, beli X gratis Y |
| Kombinasi | dapat digabung dengan voucher toko atau tidak |
| Prioritas | urutan penerapan jika beberapa aturan aktif |

Event sebaiknya hanya menjadi pengelompokan dan periode. Perhitungan tetap
dilakukan oleh aturan promo di dalamnya, sehingga satu event dapat memiliki
flash sale berbasis waktu, bundling, dan minimal belanja tanpa membuat satu
rumus besar yang sulit diaudit.

### Perbedaan voucher dan promo

| Aspek | Voucher | Flash sale / bundling |
|---|---|---|
| Cara aktif | Kasir memilih atau scan/input kode | Kasir memilih kode setelah produk yang sesuai ada di keranjang |
| Contoh | `TOKO-NATAL`, `TOPED-9.9` | `FLASH-9.9-2026`, `BUNDLE-9.9-A-C` |
| Batas penggunaan | kode, limit, redemption | kuota, periode, quantity, produk yang dipilih |
| Audit | voucher dan redemption | rule/event dan promo application |
| Tampilan kasir | kode, syarat, nominal potongan | pilihan yang cocok dengan item, syarat, dan detail potongan |

Secara perhitungan, keduanya dapat menggunakan mekanisme `discount
application` yang sama, tetapi sumbernya harus dibedakan. Voucher tetap
memiliki `voucher_redemptions`; promo menyimpan rule/event yang memicunya.

### Alert yang perlu muncul di halaman pesanan

- `Flash sale berakhir pukul 12.00` dan sisa kuota jika ada.
- `Tambah 1 pcs Produk C untuk mendapatkan bundle`.
- `Quantity sudah memenuhi harga 1 karton`; tampilkan dialog konfirmasi.
- `Voucher TOKO-NATAL aktif`, minimum belanja, basis yang memenuhi, dan nominal
  potongannya.
- Jika dua promo bertabrakan, jelaskan promo yang dipilih dan promo yang tidak
  dipakai beserta alasannya.

## Bentuk data yang dibutuhkan POS

Setiap baris penjualan sebaiknya menyimpan snapshot perhitungan pada saat
checkout, bukan hanya `product_id` dan harga akhir. Minimal:

| Kelompok | Field contoh | Keterangan |
|---|---|---|
| Stok | `owner_stock_id`, `stock_id`, `hpp` | Batch stok outlet dan HPP dari gudang |
| Harga | `harga_akhir`, `margin_type`, `margin_value`, `harga_aktif` | Dasar harga sebelum diskon toko |
| Diskon item | `disc_brand_type`, `disc_brand_value`, `disc_brand_amount`, `disc_toko_type`, `disc_toko_value`, `disc_toko_amount` | Audit dan laporan margin |
| Penjualan | `qty`, `price`, `subtotal` | `price` sebaiknya adalah Harga Netto yang benar-benar dijual |
| Voucher | `voucher_id`, `voucher_code`, `voucher_type`, `voucher_value`, `voucher_amount` | Nilai voucher pada transaksi, bukan nilai voucher terkini |
| Event/promo | `event_id`, `promotion_id`, `promotion_type`, `source`, `start_at`, `end_at` | Wadah event dan jenis aturan: flash sale, bundle, tier, minimal belanja, atau beli X gratis Y |
| Target promo | `product_ids`, `outlet_ids`, `min_qty`, `max_qty`, `min_purchase`, `quota` | Syarat dan cakupan promo; bundle perlu menyimpan kebutuhan tiap produk |
| Penerapan promo | `promotion_application_id`, `line_id`, `basis_amount`, `amount`, `priority`, `confirmation` | Snapshot promo yang benar-benar dipakai dan baris yang terkena |
| Pembayaran | `grand_total`, `paid_amount`, `change_amount`, `payment_method_id` | Dibutuhkan untuk proses kasir dan tutup kas |

Jika satu produk mengambil stok dari beberapa batch dengan HPP berbeda, POS
harus memecahnya menjadi beberapa baris internal atau menyimpan alokasi batch
agar HPP dan kartu stok tetap dapat direkonsiliasi.

## Keputusan Section 1

1. Voucher boleh digabung dengan `Disc Toko`.
2. Satu transaksi boleh memakai lebih dari satu voucher. Setiap voucher
   memiliki kode unik dan hanya boleh diredeem satu kali.
3. Saat admin membuat banyak voucher, admin hanya mengisi jumlah. Backend
   membuat varian kode unik, misalnya `PROMO-001`, `PROMO-002`, dan seterusnya.
4. Batas maksimum nominal untuk voucher persentase tersedia melalui field
   `max_discount_amount` dan bersifat opsional.
5. Voucher yang digunakan dicatat sebagai beban pada kasir yang menerapkannya
   dari halaman POS. Data redemption menyimpan kasir, outlet, invoice, kode,
   dan nominal potongan untuk laporan margin.
6. Voucher dapat berlaku global atau dibatasi ke satu produk melalui
   `product_id`, dan dapat dibatasi ke outlet melalui `outlet_id`.

## Keputusan yang masih perlu disepakati untuk promo

1. `max_qty` flash sale berlaku sebagai kuota harga promo dengan sisa quantity
   memakai harga reguler (usulan default), atau sebagai batas quantity transaksi.
2. Jika flash sale dan harga bertingkat sama-sama aktif, apakah salah satu
   dipilih berdasarkan prioritas atau keduanya boleh ditumpuk.
3. Jika dua promo menargetkan baris yang sama, gunakan `priority` tetap atau
   otomatis memilih potongan terbesar.
4. Apakah bundling boleh digabung dengan voucher toko, dan apakah baris bundle
   boleh menerima voucher produk sekaligus.
5. Minimum belanja dihitung setelah promo item dan sebelum voucher (usulan
   default pada contoh), atau memakai subtotal sebelum promo.
6. Sumber dana voucher perlu dibedakan, minimal `marketplace`, `toko`, dan
   `brand`, agar beban diskon dan margin dapat dilaporkan dengan benar.

## Cara mencoba data demo 9.9

Migration dan data demo dibuat oleh `DemoDataSeeder`. Untuk instalasi yang
belum memiliki tabel promo, jalankan:

    php artisan migrate
    php artisan db:seed --class=DemoDataSeeder

Login kasir demo: `demo.kasir1@example.test` dengan password `password`, lalu
buka `Kasir POS` pada `Outlet Demo 1`. Data promo aktif pada 1–9 September 2026
(timezone `Asia/Jakarta`).

Voucher yang dapat discan pada POS:

| Kode | Jenis | Syarat |
|---|---|---|
| `TOPED-9.9` | 10% maksimal Rp50.000 | Minimum Rp100.000 |
| `TOKO-9.9` | Potongan Rp15.000 | Minimum Rp100.000 |
| `PRODUK-A-9.9` | Potongan Rp15.000 | Khusus Produk Demo A |

Promo yang dapat dipilih setelah item yang sesuai ada di keranjang:

- `FLASH-9.9-2026`: Produk Demo A dan B, diskon 9,9%, maksimal 10 pcs per
  produk per transaksi.
- `BUNDLE-9.9-C-2PCS`: 2 Produk Demo C, potongan bundle Rp165.000.
- `BUNDLE-9.9-A-C`: 1 Produk Demo A + 1 Produk Demo C, potongan bundle Rp185.000.

Menu pengaturan promo tersedia di `POS & Harga > Flash Sale & Bundle` untuk
membuat atau mengubah produk, periode, nilai diskon, quantity, prioritas, dan
potongan bundle. Nilai bundling adalah nominal potongan per bundle, bukan
harga akhir yang harus dibayar pelanggan.
