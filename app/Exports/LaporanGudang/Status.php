<?php

namespace App\Exports\LaporanGudang;

use App\Exports\LaporanToko\Theme;

/**
 * A5: warna status bersama. Pakai sebagai opsi sel di SheetWriter::row():
 *   ['cells' => [14 => Status::cell('Selesai')]]
 */
final class Status
{
    private const TONES = [
        'ok' => ['selesai', 'complete', 'completed', 'approved', 'disetujui', 'disetujui owner', 'diterima', 'terkirim', 'aman', 'belum expired', 'fast moving'],
        'warn' => ['draft', 'pending', 'proses', 'kritis', 'menunggu', 'menunggu acc owner', 'retur', 'slow moving'],
        'info' => ['divalidasi', 'validated', 'partial', 'sebagian', 'dikirim', 'medium moving'],
        'danger' => ['habis', 'expired', 'ditolak', 'ditolak owner', 'rejected', 'batal', 'cancelled'],
    ];

    private const COLORS = [
        'ok' => [Theme::OK_BG, Theme::OK_FG],
        'warn' => [Theme::WARN_BG, Theme::WARN_FG],
        'info' => [Theme::INFO_BG, Theme::INFO_FG],
        'danger' => [Theme::DANGER_BG, Theme::DANGER_FG],
    ];

    /** Opsi sel (fill + font + bold) untuk teks status, atau null bila tidak dikenali. */
    public static function cell(?string $text): ?array
    {
        $tone = self::tone($text);

        return $tone ? ['fill' => self::COLORS[$tone][0], 'font' => self::COLORS[$tone][1], 'bold' => true] : null;
    }

    public static function tone(?string $text): ?string
    {
        $key = mb_strtolower(trim((string) $text));

        foreach (self::TONES as $tone => $words) {
            if (in_array($key, $words, true)) {
                return $tone;
            }
        }

        return null;
    }
}