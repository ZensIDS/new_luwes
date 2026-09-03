# Tabel Contoh Perhitungan Kasir Penjualan

Dokumen ini menjadi contoh perhitungan yang harus disepakati sebelum halaman
Harga Jual dan POS diimplementasikan. Semua angka menggunakan Rupiah (Rp),
tanpa pajak atau biaya layanan.

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
