<?php

namespace App\Exports\LaporanToko;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** B1 sheet 2: rekap per metode pembayaran, bon, voucher, setoran akhir. */
class OutletPenjualanRekapSheet extends BaseLaporanTokoExport
{
    public function __construct(private readonly Request $request, private readonly array $data) {}

    protected function makeMeta(): ReportMeta
    {
        return ReportMeta::fromRequest($this->request, 'REKAP PENJUALAN OUTLET', 'Penjualan-Toko', ['outlet', 'kasir']);
    }

    protected function sheetName(): string
    {
        return 'Rekap';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $summary = $this->data['summary'] ?? [];

        $w->columns([
            ['h' => 'Keterangan', 'w' => 36],
            ['h' => 'Nilai', 'fmt' => Theme::FMT_RUPIAH, 'w' => 24],
        ])->kop()->info()->header();

        $w->section('Penjualan per Metode Pembayaran');
        $first = null;
        foreach (($this->data['paymentMethods'] ?? []) as $method => $amount) {
            $r = $w->row([$method, (float) $amount]);
            $first ??= $r;
        }
        $w->totalRow('Total Penjualan', $first === null ? [] : [2 => 'sum'], [
            'from' => $first ?? 0, 'to' => $first === null ? -1 : $w->currentRow() - 1, 'sub' => true,
        ]);

        $w->section('Lainnya');
        $w->row(['Bon', (float) ($summary['bon'] ?? 0)]);
        $w->row(['Total Voucher', (float) ($summary['total_voucher'] ?? 0)]);
        $w->row(['Setoran Akhir', (float) ($summary['setoran_akhir'] ?? 0)]);
        $w->row(['Jumlah Transaksi', (int) ($summary['jumlah_transaksi'] ?? 0)], [
            'fmt' => [2 => Theme::FMT_NUMBER],
        ]);
    }
}