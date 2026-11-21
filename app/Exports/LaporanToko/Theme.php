<?php

namespace App\Exports\LaporanToko;

/**
 * Satu-satunya tempat tema Excel Laporan Toko (A1, A10).
 * Ganti warna di sini, semua laporan ikut berubah.
 *
 * Tema aktif: biru tua formal + abu netral.
 * Alternatif (ungu mengikuti aplikasi): ganti PRIMARY/SECONDARY/TOTAL_BG
 * mis. PRIMARY = '4B2E83', SECONDARY = 'E4DCF1', TOTAL_BG = 'EDE7F6'.
 */
final class Theme
{
    // Warna (hex tanpa #)
    public const PRIMARY    = '1F3A5F'; // header tabel, garis kop, judul
    public const ON_PRIMARY = 'FFFFFF';
    public const SECONDARY  = 'D9E1EC'; // header kelompok, baris section/subtotal
    public const TOTAL_BG   = 'E6EBF2'; // baris total
    public const ZEBRA      = 'F5F7FA';
    public const BORDER     = 'C5CDD8';
    public const MUTED      = '5B6573'; // label info, teks sekunder
    public const TEXT       = '1F2933';

    // Warna status (latar + teks)
    public const DANGER_BG  = 'F8D7DA';
    public const DANGER_FG  = '9B1C1C';
    public const WARN_BG    = 'FFF3CD';
    public const WARN_FG    = '7A5B00';
    public const OK_BG      = 'DDEFE3';
    public const OK_FG      = '1E5A34';
    public const INFO_BG    = 'E3EAF7'; // mis. "Pengganti Retur"
    public const INFO_FG    = '1F3A5F';

    public const FONT      = 'Calibri';
    public const FONT_SIZE = 10;

    // ── Pola toko (todolist_font_laporan_toko, B1) ─────────────────────────
    // Hanya dipakai bila export mengaktifkan tokoLayout(); nilai di atas tidak diubah.
    public const TOKO_FONT_SIZE     = 18;   // K2/K9: semua bagian sama, termasuk kop dan judul
    public const TOKO_ZOOM          = 85;   // K6
    public const TOKO_WIDTH_FACTOR  = 1.15; // faktor lebar kolom (kunci setelah uji Excel, A3/A4)
    public const TOKO_COL_MAX       = 30;   // batas lebar kolom otomatis (teks lebih panjang di-wrap, K10)
    // Tinggi baris tetap (pt) untuk baris non-data; baris data dibiarkan menyesuaikan isi.
    public const TOKO_ROW_KOP       = 30;
    public const TOKO_ROW_TITLE     = 30;
    public const TOKO_ROW_GROUP     = 30;
    public const TOKO_ROW_HEADER    = 46;
    public const TOKO_ROW_NOTICE    = 30;
    public const TOKO_ROW_SPACER    = 8;
    public const TOKO_FREEZE_MAX    = 200;  // K6: bila area beku (kop+info+header) lebih tinggi dari ini (pt), bekukan hanya baris header

    // Format angka (A4). Tampilan titik/koma mengikuti pengaturan region Excel pemakai.
    public const FMT_RUPIAH   = '"Rp" #,##0;[Red]-"Rp" #,##0';
    public const FMT_NUMBER   = '#,##0';
    public const FMT_DECIMAL  = '#,##0.00';
    public const FMT_PERCENT  = '0.0%';
    public const FMT_DATE     = 'dd/mm/yyyy';
    public const FMT_DATETIME = 'dd/mm/yyyy hh:mm';
    public const FMT_TEXT     = '@';
}