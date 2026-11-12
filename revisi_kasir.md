# TODOLIST REVISI KASIR (Laravel POS)

Aturan kerja:
- Dikerjakan bertahap. Tiap tahap selesai: todolist ini di-update, lalu hanya todolist + file yang berubah/dibutuhkan yang dikirim (tanpa zip, tanpa tempel di chat).
- Status: [ ] belum | [~] sedang | [x] selesai

Catatan: pengerjaan dilakukan BERTAHAP (satu tahap per giliran). Setiap tahap selesai, todolist ini di-update dan dikirim bersama file yang berubah saja.

Terakhir diupdate: Semua tahap (A, C, D, B, E, F, G) selesai. Tinggal pengujian manual.

---

## RINGKASAN STATUS

| Tahap | Isi                                                  | Status |
|-------|------------------------------------------------------|--------|
| 0     | Analisis, perapihan todolist, jawaban dimasukkan     | [x]    |
| A     | Tulisan selisih (+/-), hapus penjualan superadmin    | [x]    |
| C     | Keranjang: urutan scan terbaru, promo per item       | [x]    |
| D     | Cari Produk (F2) & Reprint Nota (F4)                 | [x]    |
| B     | Sembunyikan angka kas (kasir, tutup kasir, history)  | [x]    |
| E     | History Kasir: edit + batas 2x untuk kasir + tercatat| [x]    |
| F     | Master Kresek + pilih kresek di layar kasir          | [x]    |
| G     | Cashback (qty + nominal) terhitung bersama voucher   | [x]    |

Urutan dikerjakan: A -> C -> D -> B -> E -> F -> G (semua sudah selesai).

---

## KEPUTUSAN (dari jawaban)

- B1: Angka kas hanya terlihat oleh superadmin dan staff-outlet. Role lain (kasir, dan juga owner/admin) disembunyikan.
- B2: Angka selisih tetap tampil, hanya tulisan diganti dengan tanda. Contoh: "- Rp 10.000" atau "+ Rp 15.000".
- E1: Semua role boleh edit History Kasir. Batas maksimal 2x hanya berlaku untuk role kasir.
- E2: Field yang bisa diedit sama persis dengan form tutup kasir biasa (uang fisik, saldo besok, catatan).
- E3: Setelah sesi sudah diedit 2x, tombol Edit hilang untuk kasir.
- F1: Dibuat menu Master Kresek (kelola daftar kresek).
- F2: Di halaman kasir, kresek dipilih dengan centang. Boleh lebih dari satu, tiap kresek punya qty sendiri (jenis kresek bisa 5 atau lebih). Tampilan minimalis dan mudah dipakai.
- G1: Kasir input qty cashback + nominal. Dihitung di tutup kasir menyatu dengan voucher (qty dan total nominal selaras). Batas: tidak boleh melebihi total belanja.
- G2: Perilaku persis seperti voucher (mengurangi total yang dibayar).
- G3: Di nota tidak ada tulisan "Cashback"; digabung ke bagian voucher.

## ASUMSI (ikut default kecuali kamu koreksi)

1. Hitungan edit 2x: per sesi kasir, semua edit dihitung (edit_count). Role selain kasir tidak dibatasi, tapi tetap tercatat dan menambah hitungan.
2. Master Kresek: kolom nama, harga (boleh 0), status aktif. Tanpa stok. Kresek yang dipilih ikut masuk total, nota, dan laporan penjualan.
3. Owner dan admin juga tidak melihat angka kas (sesuai B1: hanya superadmin dan staff-outlet).

---

## TAHAP A - Perubahan kecil

A1. [x] Selisih tutup kasir: tulisan "Lebih / Kurang" diganti tanda, angka tetap tampil
    - Format: "+ Rp 15.000", "- Rp 10.000", "Rp 0" bila pas.
    - resources/views/outlets/show.blade.php (JS modal Tutup Kasir, sekitar baris 326)
    - resources/views/cashier/history.blade.php (kolom Selisih, sekitar baris 75)
    - Print tutup kasir (CashierSlipController) TIDAK diubah, tetap "Lebih +/Kurang -" seperti sebelumnya.
    - Hasil: modal tutup kasir menampilkan "+ Rp 15.000" / "- Rp 10.000" / "Pas (Rp 0)"; kolom Selisih di History Kasir menampilkan "+ Rp ..." / "- Rp ..." / "Pas".
A2. [x] Tombol Hapus di Penjualan: sembunyikan, hanya superadmin yang bisa hapus
    - Kondisi sekarang: staff-outlet diblokir, sesi ditutup hanya owner/superadmin, role lain masih boleh.
    - Ubah di app/Http/Controllers/PenjualanController.php (kolom aksi + destroy()) dan app/Services/SaleCancellationService.php (blockReason).
    - Tombol disembunyikan (bukan disabled) untuk selain superadmin; destroy() tetap ditolak 403 di server.
    - Cek juga tombol hapus di resources/views/penjualan/show.blade.php dan _delete-script.
    - Hasil: variabel baru canDeleteSales (superadmin saja) dipakai di daftar penjualan, halaman show, dan halaman marketplace. Guard server ada di destroy() dan SaleCancellationService::blockReason() (dipakai juga oleh cancel()), jadi role lain tetap ditolak walau mengirim request manual. canManageSales tetap dipakai untuk tombol "Buat Penjualan Baru".
    - File berubah: app/Http/Controllers/PenjualanController.php, app/Services/SaleCancellationService.php, resources/views/penjualan/show.blade.php, resources/views/penjualan/marketplace.blade.php, resources/views/outlets/show.blade.php, resources/views/cashier/history.blade.php.
    - Tidak perlu migration dan tidak perlu build assets (hanya PHP/Blade).

## TAHAP C - Keranjang

C1. [x] List produk yang di-scan: urut terbaru di paling atas
    - Koreksi analisis: baris orderBy('created_at') yang tadi disebut ternyata untuk stok batch, bukan keranjang. Keranjang sebelumnya tidak punya urutan eksplisit (urutan masuk).
    - Hasil: CartController::index() mengurutkan hasil berdasarkan waktu scan terakhir (updated_at pivot user_cart), terbaru di atas, selisih sedetik diurutkan id pivot. Scan ulang produk yang sudah ada memindahkannya ke atas; ubah qty (+, -, ketik) TIDAK memindahkan baris (changeQty mematikan timestamp pivot).
    - Urutan dihitung di akhir, setelah promo dihitung, jadi hasil promo tidak berubah. Item diberi cart_seq (urutan masuk asli) agar alokasi voucher di layar kasir (Cart.jsx getVoucherBreakdown) tetap identik dengan hitungan server saat checkout.
    - Ctrl+Backspace/Delete (hapus item terbaru) disesuaikan: sekarang menghapus cart[0] (paling atas).
    - Navigasi keyboard (F5 fokus baris pertama, panah, Home/End) otomatis mengikuti: baris pertama = item terbaru.
C2. [x] Tampilkan promo per item di list keranjang
    - Data sudah dikirim server: cashier_promotions, cashier_promotion_discount, cashier_promotion_breakdown.
    - Hasil: di bawah nama produk muncul label hijau per promo, format "Nama Promo -Rp x" (CartTableBody.jsx). Tanpa rincian jumlah, tampil nama promo saja.
    - File berubah: app/Http/Controllers/CartController.php, resources/js/components/Cart.jsx, resources/js/components/CartTableBody.jsx.
    - PERLU build ulang assets setelah file dipasang (npm run build / npm run dev). Tanpa migration.
    - Catatan: transaksi hold mengirim keranjang sesuai urutan tampilan; saat dipulihkan urutan item bisa terbalik dari semula (hanya urutan tampilan, tidak mempengaruhi total).

## TAHAP D - Cari Produk (F2) & Reprint (F4)

D1. [x] Cari Produk F2: minimal 3 karakter (sebelumnya 2) di resources/js/components/Cart.jsx (konstanta MIN_PRODUCT_SEARCH_LENGTH, dipakai di modal dan loadProducts; teks petunjuk ikut berubah)
D2. [x] Daftar hasil bisa di-scroll dan memuat semua produk hasil pencarian
    - Temuan: endpoint produk outlet (ProductController::index) selalu mengirim 10 baris per halaman (disengaja, supaya ringan), jadi sebelumnya hanya 10 hasil teratas yang terlihat.
    - Hasil: daftar hasil di modal punya area scroll sendiri (tinggi maks 55% layar). Saat scroll mendekati bawah (atau 10 baris pertama belum memenuhi area), halaman berikutnya dimuat otomatis dan ditambahkan, sampai semua hasil tampil ("Semua hasil sudah tampil (N produk)"). Beban server tetap 10 baris per request.
    - Pencarian baru membatalkan pemuatan halaman lama. Urutan hasil diberi tie-break id di ProductController agar antar halaman tidak ada baris ganda/terlewat.
    - Navigasi keyboard (Tab lalu Enter) tetap jalan; Tab ke baris terbawah memicu muat halaman berikutnya.
    - Permintaan tambahan: pencarian F2 dari AWAL nama. "mus" hanya menemukan nama yang diawali "mus" (mis. MUSTIKA AYU), bukan "HAIR MUSK". Kode/barcode juga dicocokkan dari awal. Pencarian brand dan model dihapus dari F2 (halaman Product biasa tidak berubah). Scan barcode eksak tidak terpengaruh.
    - File berubah: resources/js/components/Cart.jsx, app/Http/Controllers/ProductController.php.
D3. [x] Reprint Nota F4: hanya nota dari sesi kasir yang sedang buka hari itu, per akun kasir
    - Hasil: PenjualanController::reprintSearch() hanya menampilkan penjualan dari sesi kasir yang sedang buka milik akun yang login di outlet itu (cashier_session_id). Nota sesi yang sudah ditutup (kemarin) dan nota akun lain tidak muncul. Kriteria sesi sama dengan CashierSaleService.
    - Tanpa sesi terbuka: daftar kosong + pesan peringatan "Kasir belum dibuka..." di modal (ReprintModal.jsx).
    - Berlaku untuk semua role di layar kasir (F4). Tombol Re-Print di halaman Riwayat Penjualan tidak diubah (itu bukan F4).
    - File berubah: app/Http/Controllers/PenjualanController.php (sudah termasuk perubahan Tahap A), resources/js/components/ReprintModal.jsx.
    - Perlu build ulang assets (npm run build). Tanpa migration.

## TAHAP B - Sembunyikan angka kas

B1. [x] View kasir (resources/views/outlets/show.blade.php, header atas): S.Awal, P.Tunai, Non-Tunai, K.Penjualan, Laci disembunyikan untuk role selain superadmin/staff-outlet. Voucher dan BON tetap tampil.
B2. [x] Modal Tutup Kasir: P.Tunai, K.Masuk, baris "Uang hasil penjualan yang seharusnya ada", dan catatan saldo awal disembunyikan. BON, selisih (+/- Rp), total laci setelah tutup, dan input tetap tampil.
B3. [x] History Kasir (cashier/history.blade.php): kolom Penjualan tunai dan Kas seharusnya disembunyikan (colspan menyesuaikan). Selisih tetap tampil.
B4. [x] Aturan tampil: User::canSeeCashFigures() = superadmin atau staff-outlet. Pengecekan lewat Blade (@if), jadi angka tidak ikut terkirim ke browser.
B5. [x] Print Tutup Kasir: tidak disentuh (CashierSlipController tidak berubah).
    - Keputusan selisih: untuk role yang disembunyikan, angka "seharusnya" tidak ada di HTML. Selisih dihitung server lewat route baru GET /cashier-sessions/{id}/close-difference (CashierSessionController::closeDifference, hanya sesi open milik akun itu; respons hanya {difference}). Input uang fisik di-debounce 250 ms. Superadmin/staff-outlet tetap dihitung langsung di browser seperti sebelumnya.
    - Tambahan di luar daftar (agar angka tunai tidak bocor lewat tabel lain): tabel "Metode bayar" (cashier/_breakdown.blade.php, dipakai modal tutup kasir dan History) untuk role yang disembunyikan menampilkan "—" pada total metode tunai, baris "Total semua metode" dan catatan "Penjualan non-tunai" dihilangkan. Baris metode non-tunai dan voucher tetap tampil.
    - Tidak diubah (tidak ada di daftar): kolom Saldo awal di History Kasir. Bila ingin ikut disembunyikan, bilang saja.
    - Catatan: selisih yang tetap tampil secara matematis bisa dipakai menebak angka seharusnya (selisih + uang fisik). Ini konsekuensi keputusan B2.
    - File berubah: app/Models/User.php, app/Http/Controllers/CashierSessionController.php, routes/web.php, resources/views/outlets/show.blade.php, resources/views/cashier/history.blade.php, resources/views/cashier/_breakdown.blade.php.
    - Tanpa migration, tanpa build assets (PHP/Blade saja). Setelah dipasang jalankan php artisan route:clear (dan view:clear) bila route/view di-cache.

## TAHAP E - History Kasir: edit + batas 2x untuk kasir + tercatat

E1. [x] Migration 2026_11_12_090000_add_edit_count_to_cashier_sessions_table: kolom edit_count (unsigned int, default 0). Model CashierSession: edit_count masuk fillable + cast integer.
E2. [x] Route PUT /cashier-sessions/{id}/close (cashier.update-close) -> CashierSessionController::update(). Hanya sesi closed. Aturan edit ada di CashierSession::canBeEditedBy(): kasir dan staff-outlet hanya sesinya sendiri; kasir ditolak (403) bila edit_count >= 2 (KASIR_EDIT_LIMIT); role lain tanpa batas. Dicek ulang setelah lockForUpdate supaya dua request bersamaan tidak lolos batas.
E3. [x] Form edit: uang fisik, saldo besok, catatan (sama dengan form tutup kasir). Yang dihitung ulang: closing_cash, cash_removed (= uang fisik), carry_over_cash, discrepancy, closing_note, edit_count + 1.
    - Kas seharusnya memakai angka yang tersimpan saat sesi ditutup (expected_cash), bukan hitungan ulang, supaya selisih tidak berubah karena penjualan yang dihapus setelah tutup. Bila kosong (sesi lama), baru memakai hitungan berjalan.
E4. [x] Pencatatan: satu activitylog (log_name 'Cashier') per edit, "Sesi kasir diedit (ke-n)", berisi edit_number, role editor, nilai lama -> baru (uang fisik, saldo besok, catatan, selisih), causer = pengedit. Log otomatis model dimatikan sementara saat edit supaya tidak dobel.
    - Catatan: tabel Activitylog di history.blade.php ternyata masih dikomentari (tidak tampil). Tidak saya aktifkan lagi karena memuat angka kas. Sebagai gantinya, riwayat edit tampil di bawah "Catatan" tiap sesi, hanya untuk superadmin/staff-outlet (aturan Tahap B).
E5. [x] UI History: tombol Edit (modal) di kolom Status sesi closed; hilang untuk kasir setelah 2x (juga untuk sesi kasir lain); label "Diedit n kali" tampil untuk semua. Kasir melihat info "Edit ke-n dari maksimal 2" dan konfirmasi khusus pada edit terakhir.
E6. [x] Angka di form edit mengikuti Tahap B: superadmin/staff-outlet melihat "seharusnya" dan selisih dihitung di browser; role lain tidak (selisih dihitung server lewat route close-difference yang kini juga menerima sesi closed yang boleh diedit pengguna itu).
    - File berubah/baru: database/migrations/2026_11_12_090000_add_edit_count_to_cashier_sessions_table.php (baru), app/Models/CashierSession.php, app/Http/Controllers/CashierSessionController.php, routes/web.php, resources/views/cashier/history.blade.php, resources/views/cashier/_edit-session-script.blade.php (baru).
    - PERLU php artisan migrate. Tanpa build assets (JS ada di Blade). Jalankan route:clear / view:clear bila di-cache.
    - Catatan: Spatie Activitylog dipakai lewat disableLogging()/enableLogging() di model; kalau versi paket kamu tidak punya method itu, kabari saya.

## TAHAP F - Master Kresek + pilih kresek di kasir

F1. [x] Master Kresek: migration kreseks (nama, harga decimal boleh 0, is_active, soft delete), model Kresek, KresekController (CRUD), views kreseks/index-create-edit, menu "Master Kresek" di sidebar. Akses CRUD: superadmin dan staff-outlet (sama dengan Metode Pembayaran), dikunci di route (role:superadmin|staff-outlet). Master global, tidak per outlet, tanpa stok.
    - Seeder: database/seeders/KresekSeeder.php (Kantong plastik No 1 Rp 100, No 2 Rp 300, No 3 Rp 500, No 4 Rp 600, No 5 Rp 1.200, No 6 Rp 1.700). Aman dijalankan berulang (dicocokkan lewat nama), sudah dipanggil dari DatabaseSeeder. Jalankan: php artisan db:seed --class=KresekSeeder
F2. [x] Layar kasir: tombol "Kresek (F9)" di samping Grand Total + shortcut F9 membuka pop up kecil (KresekModal.jsx): daftar kresek aktif, centang + qty (default 1 saat dicentang), boleh lebih dari satu. Keyboard: panah pindah baris, Spasi atau angka 1-9 centang, + / - ubah qty, Enter/Esc tutup. Ringkasan "Kresek (n pcs): +Rp x" muncul di bawah subtotal; Grand Total dan pop up Pembayaran ikut menghitung kresek.
F3. [x] Penyimpanan: tabel penjualan_kreseks (penjualan_id, kresek_id, nama + harga disalin, qty, subtotal, soft delete) + kolom penjualans.kresek_total. CashierSaleService: harga selalu dari master (bukan dari client), kresek nonaktif/terhapus ditolak, ditambahkan SETELAH promo dan voucher (tidak ikut dasar voucher/promo): grand_total = (subtotal - voucher) + kresek. Tanpa potong stok. Biaya metode pembayaran (%) dihitung dari grand total termasuk kresek.
    - Karena masuk grand_total, kresek otomatis ikut Penjualan tunai / kas seharusnya di tutup kasir dan angka omzet berbasis grand_total.
F4. [x] Nota (penjualan/print.blade.php): kresek tampil sebagai baris item dan ikut Subtotal (Subtotal - Diskon - Voucher = TOTAL tetap cocok), termasuk cetak teks QZ Tray. Halaman detail (show + _detail) menampilkan baris kresek dan "Kresek : +Rp". Export laporan penjualan (laporan-penjualan, -kasir, -supplier): kolom baru "Kresek", Grand Total sudah termasuk kresek.
F5. [x] Hapus/batal penjualan (SaleCancellationService): baris kresek ikut di-soft-delete (tanpa stok yang dikembalikan); laporan membaca lewat penjualan induk sehingga tidak ikut terhitung.
    - File berubah/baru: database/migrations/2026_11_12_100000_create_kreseks_table.php, database/migrations/2026_11_12_100100_create_penjualan_kreseks_table.php, database/seeders/KresekSeeder.php, database/seeders/DatabaseSeeder.php, app/Models/Kresek.php, app/Models/PenjualanKresek.php, app/Models/Penjualan.php, app/Http/Controllers/KresekController.php, app/Http/Controllers/PenjualanController.php, app/Services/CashierSaleService.php, app/Services/SaleCancellationService.php, routes/web.php, resources/views/layouts/sidebar.blade.php, resources/views/kreseks/{index,create,edit}.blade.php, resources/views/penjualan/{print,show,_detail}.blade.php, resources/views/exports/laporan-penjualan{,-kasir,-supplier}.blade.php, resources/js/components/{Cart,CartTable,KresekModal}.jsx.
    - PERLU php artisan migrate, php artisan db:seed --class=KresekSeeder, dan build ulang assets (npm run build). route:clear / view:clear bila di-cache.
    - Belum disentuh (bukan bagian daftar): laporan toko berbasis item (OutletLaporanService / Laporan Toko Excel) tidak memuat kresek; retur penjualan tidak menangani kresek (kresek tidak bisa diretur); transaksi hold / wishlist tidak membawa pilihan kresek.

## TAHAP G - Cashback (qty + nominal)

G1. [x] Migration 2026_11_12_130000_add_cashback_to_penjualans_table: penjualans.cashback_qty (unsigned int, default 0) dan cashback_total (decimal 15,2, default 0). Model Penjualan: fillable + cast, plus accessor voucher_cashback_total (= voucher_total + cashback_total) yang dipakai semua tampilan "Voucher".
G2. [x] Layar kasir: tombol "Cashback (Alt+C)" di samping tombol Kresek + shortcut Alt+C (Option+C di Mac) membuka pop up kecil CashbackModal.jsx: input Qty (lembar) dan Total nominal. Enter = simpan, Esc = tutup, tombol "Hapus cashback". Nominal dibatasi maksimal total belanja (subtotal setelah promo - voucher); pesan error bila melebihi. Nominal kosong = tanpa cashback. Ringkasan "Cashback (n lbr): -Rp x" tampil di bawah subtotal; Grand Total dan pop up Pembayaran ikut berkurang. Keranjang kosong: pop up tidak dibuka. Bila isi keranjang berubah sehingga batas turun, nominal otomatis dibatasi (angka yang tampil = angka yang dikirim).
    - Shortcut F10 sudah dipakai Bayar, jadi dipilih Alt+C (tidak bentrok dengan shortcut lain).
G3. [x] CashierSaleService: cashback_qty + cashback_total dari client divalidasi di server (qty minimal 1 bila ada nominal, nominal tidak boleh melebihi subtotal - voucher, bila melebihi checkout ditolak dengan pesan jelas). Perilaku persis voucher: grand_total = (subtotal - voucher - cashback) + kresek. Cashback dihitung setelah promo + voucher dan sebelum kresek (kresek tetap tidak ikut dasar potongan). Biaya metode bayar (%) dihitung dari grand total seperti biasa. Validasi request ada di PenjualanController::store.
G4. [x] Tutup kasir: CashierSession::breakdownFor menambahkan cashback ke voucher: voucher_count = jumlah voucher + qty cashback, voucher_total = nominal voucher + cashback (kunci cashback_count/cashback_total tetap tersedia terpisah, tidak ditampilkan). Tanpa query tambahan (SUM dimasukkan ke query metode bayar yang sudah ada). Otomatis berlaku di: header kasir (Voucher n x / Rp), modal Tutup Kasir dan History Kasir (_breakdown.blade.php, tanpa perubahan file), serta Print Tutup Kasir (baris "Voucher n lbr", format tidak berubah).
    - Kas tunai tidak perlu diubah: penjualan tunai memakai grand_total yang sudah berkurang cashback.
G5. [x] Nota (penjualan/print.blade.php): cashback digabung ke baris Voucher (tampilan HTML dan cetak teks QZ Tray), ikut "Anda hemat", tanpa tulisan "Cashback". Detail penjualan (show + _detail) dan export laporan penjualan (laporan-penjualan, -kasir, -supplier) memakai voucher + cashback di kolom Voucher. Laporan Toko (OutletLaporanService::voucherAllocation) memperlakukan cashback seperti voucher tanpa kode: dibagi proporsional ke item, masuk "Total Voucher".
G6. [x] Hapus/batal penjualan: tidak perlu perubahan kode. Cashback tersimpan di baris penjualan itu sendiri, jadi ikut keluar dari tutup kasir / laporan begitu penjualan di-soft-delete (query breakdown dan kas membuang penjualan terhapus). Tidak ada stok atau voucher yang perlu dikembalikan.
    - File berubah/baru: database/migrations/2026_11_12_130000_add_cashback_to_penjualans_table.php (baru), app/Models/Penjualan.php, app/Models/CashierSession.php, app/Services/CashierSaleService.php, app/Services/OutletLaporanService.php, app/Http/Controllers/PenjualanController.php, resources/views/penjualan/{print,show,_detail}.blade.php, resources/views/exports/laporan-penjualan{,-kasir,-supplier}.blade.php, resources/js/components/{Cart,CartTable,CashbackModal (baru)}.jsx.
    - PERLU php artisan migrate dan build ulang assets (npm run build). route:clear / view:clear bila di-cache.
    - Belum disentuh: transaksi hold / wishlist tidak membawa cashback (sama seperti kresek); retur penjualan tidak menghitung ulang cashback.

---

## CATATAN ANALISIS

- Archive.zip hanya berisi bagian kasir (app, resources, routes, database); cukup untuk semua tahap.
- Sudah ada dan tidak dibuat ulang: breakdown voucher per sesi, activitylog sesi kasir, reprint per kasir, validasi blok hapus penjualan.
- Perubahan di resources/js/ (Cart, CartTableBody, ReprintModal, panel kresek) butuh build ulang assets setelah file dipasang.

## LOG PENGERJAAN

- Tahap G: selesai. Cashback (qty + nominal) lewat Alt+C di kasir, dihitung seperti voucher (kurangi total), batas tidak melebihi total belanja (dicek di browser dan server), digabung ke voucher di tutup kasir, nota, detail, dan laporan tanpa tulisan "Cashback". Belum diuji (tanpa PHP; JSX sudah lolos cek sintaks): mohon migrate + build, lalu cek: Alt+C dengan keranjang kosong dan berisi, nominal melebihi batas, Grand Total dan modal Bayar, transaksi dengan voucher + cashback + kresek sekaligus, nota (tampilan dan cetak: baris Voucher = voucher + cashback), detail penjualan, tutup kasir (jumlah lembar dan nominal voucher + cashback, uang tunai seharusnya ikut turun), Print Tutup Kasir, hapus penjualan oleh superadmin lalu cek tutup kasir kembali.
- Tahap F: selesai. Master Kresek + seeder 6 kantong plastik, pilih kresek F9 di kasir, tersimpan per penjualan, ikut total/nota/laporan penjualan/batal. Belum diuji (tanpa PHP; JSX sudah lolos cek sintaks): mohon migrate + seed + build, lalu cek: CRUD Master Kresek, F9 (centang beberapa, ubah qty), Grand Total dan modal Bayar, nota (tampilan dan cetak), detail penjualan, hapus penjualan oleh superadmin, tutup kasir (penjualan tunai ikut kresek), dan kresek dinonaktifkan saat transaksi berjalan.
- Tahap E: selesai. Edit History Kasir dengan batas 2x untuk kasir, tercatat di activitylog, angka mengikuti aturan Tahap B. Belum diuji (tanpa PHP): mohon jalankan migrate lalu cek: edit sebagai kasir (edit 1, edit 2, tombol hilang di ke-3), edit sebagai owner/admin/superadmin (tanpa batas, hitungan tetap naik), selisih di modal, dan riwayat edit di bawah Catatan.
- Tahap B: selesai. Angka kas disembunyikan untuk selain superadmin/staff-outlet (header kasir, modal tutup kasir, History, tabel metode bayar); selisih tetap tampil, dihitung server untuk role tersembunyi. Belum diuji (tanpa PHP di sini): mohon cek dengan akun kasir, owner, staff-outlet, superadmin: header kasir, modal tutup kasir (ketik uang fisik, selisih muncul), History Kasir, dan Print Tutup Kasir tetap normal.
- Tahap D (revisi): pencarian F2 diubah jadi dari awal nama (permintaan tambahan), brand/model tidak ikut dicari di F2.
- Tahap D: selesai. F2 minimal 3 karakter + scroll memuat semua hasil bertahap; F4 hanya nota sesi kasir terbuka milik akun. Belum diuji (tanpa PHP/Node di sini): mohon cek cari produk 2 vs 3 karakter, hasil lebih dari 10 produk (scroll ke bawah), F4 dengan sesi buka dan tanpa sesi, F4 setelah tutup kasir.
- Tahap C: selesai. Keranjang terbaru di atas + label promo per item. Belum diuji (tanpa PHP/Node build di sini): mohon cek scan produk baru, scan ulang produk yang sama, ubah qty, produk dengan promo, voucher, Ctrl+Backspace.
- Tahap A: selesai. Selisih tampil dengan tanda +/- dan Rp; tombol Hapus penjualan hanya untuk superadmin (UI + server). Belum diuji di aplikasi (lingkungan ini tanpa PHP), mohon dicek manual: tutup kasir, History Kasir, hapus penjualan dengan akun superadmin dan non-superadmin.
- Tahap 0: analisis selesai, todolist dirapikan jadi tahap A-G. Jawaban pertanyaan dimasukkan jadi keputusan; sisa tiga asumsi default di atas.