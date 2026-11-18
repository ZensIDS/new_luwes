<?php

namespace App\Exports;

use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\CashierDrawerEntry;
use App\Models\CashierSession;
use App\Models\Outlet;
use App\Support\OutletAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Laporan Toko: Penjualan Kassa harian (satu baris per sesi kassa).
 *
 * Kolom: Nama Kassa (akun kassa), Nama Kasir (semua shift di sesi itu, satu per baris), Saldo Awal,
 * Penjualan Tunai, Penjualan per metode bayar non-tunai (kolom dinamis), Total Non Tunai, Total Pendapatan
 * (tunai + non-tunai, semua metode), Bon, Setoran (setoran tutup kasir), Setoran Total (Bon + Setoran).
 *
 * Parameter request: tanggal (Y-m-d, default hari ini), outlet_id (opsional; staff-outlet terkunci ke outletnya).
 * Sesi dipilih menurut tanggal buka. Sesi belum tutup tetap tampil (tanda "BELUM TUTUP", setoran "-").
 * Penjualan memakai CashierSession::breakdownFor() (paid, belum dihapus; sama dengan Tutup Kasir).
 */
class KassaHarianExport extends BaseLaporanTokoExport
{
    private ?int $outletId = null;
    private ?Carbon $date = null;
    private bool $resolved = false;
    private ?array $data = null;

    public function __construct(private readonly Request $request) {}

    private function resolve(): void
    {
        if ($this->resolved) {
            return;
        }

        $this->outletId = OutletAccess::id($this->request, false);
        $this->date = Carbon::parse($this->request->input('tanggal') ?: now()->toDateString())->startOfDay();
        $this->resolved = true;
    }

    public function fileName(): string
    {
        $this->resolve();
        $outlet = $this->outletId
            ? (string) Outlet::whereKey($this->outletId)->value('name')
            : 'Semua-Outlet';
        $slug = trim((string) preg_replace('/[^A-Za-z0-9\-]+/', '-', $outlet), '-') ?: 'Outlet';

        return sprintf('Laporan-Kassa-Harian-%s_%s.xlsx', $slug, $this->date->format('Y-m-d'));
    }

    /**
     * @return array{rows: array<int,array<string,mixed>>, methods: string[], open: int}
     */
    private function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }
        $this->resolve();

        $sessions = CashierSession::query()
            ->with(['outlet:id,name', 'cashier:id,name,role', 'shifts'])
            ->whereBetween('opened_at', [$this->date->copy()->startOfDay(), $this->date->copy()->endOfDay()])
            // Hanya sesi milik akun ber-role kasir (siapa pun yang melihat laporan); superadmin/owner tidak masuk.
            ->whereHas('cashier', fn ($query) => $query->where('role', 'kasir'))
            ->when($this->outletId, fn ($query) => $query->where('outlet_id', $this->outletId))
            ->orderBy('outlet_id')
            ->orderBy('opened_at')
            ->get();

        $ids = $sessions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $breakdowns = CashierSession::breakdownFor($ids);

        // BON / kas masuk per sesi: satu query (soft delete otomatis dibuang oleh model).
        $drawer = [];
        if ($ids) {
            $entries = CashierDrawerEntry::query()
                ->whereIn('cashier_session_id', $ids)
                ->whereIn('type', ['bon', 'cash_out', 'cash_in'])
                ->selectRaw('cashier_session_id, type, SUM(amount) as total')
                ->groupBy('cashier_session_id', 'type')
                ->get();

            foreach ($entries as $entry) {
                $key = (int) $entry->cashier_session_id;
                $field = $entry->type === 'cash_in' ? 'cash_in' : 'bon';
                $drawer[$key][$field] = ($drawer[$key][$field] ?? 0.0) + (float) $entry->total;
            }
        }

        // Retur penjualan di sesi ini: uang kembali ke pembeli MENGURANGI pendapatan (tunai);
        // selisih yang dibayar pembeli untuk barang pengganti lebih mahal MENAMBAH pendapatan sesuai metode bayarnya.
        $refunds = [];
        if ($ids) {
            foreach (\App\Models\RefundPenjualan::query()->whereIn('cashier_session_id', $ids)->get(['cashier_session_id', 'refund_amount', 'difference', 'payment_method_name']) as $refund) {
                $key = (int) $refund->cashier_session_id;
                $refunds[$key]['refund'] = ($refunds[$key]['refund'] ?? 0.0) + (float) $refund->refund_amount;
                if ((float) $refund->difference > 0) {
                    $method = trim((string) $refund->payment_method_name) ?: 'Tunai';
                    $refunds[$key]['extra'][$method] = ($refunds[$key]['extra'][$method] ?? 0.0) + (float) $refund->difference;
                }
            }
        }

        $methods = [];
        $rows = [];
        $open = 0;

        foreach ($sessions as $session) {
            $id = (int) $session->id;
            $b = $breakdowns[$id] ?? CashierSession::emptyBreakdown();
            $closed = $session->status === 'closed';
            $open += $closed ? 0 : 1;

            $tunai = 0.0;
            $nonCash = [];
            foreach ($b['payments'] as $payment) {
                if ($payment['is_cash']) {
                    $tunai += (float) $payment['total'];
                } else {
                    $nonCash[$payment['name']] = ($nonCash[$payment['name']] ?? 0.0) + (float) $payment['total'];
                    $methods[$payment['name']] = true;
                }
            }

            $tunai -= (float) ($refunds[$id]['refund'] ?? 0.0);
            foreach (($refunds[$id]['extra'] ?? []) as $method => $amount) {
                if (CashierSession::isCashPaymentName($method)) {
                    $tunai += $amount;
                } else {
                    $nonCash[$method] = ($nonCash[$method] ?? 0.0) + $amount;
                    $methods[$method] = true;
                }
            }

            $bon = (float) ($drawer[$id]['bon'] ?? 0.0);

            $setoranTutup = $closed ? (float) ($session->cash_removed ?? $session->closing_cash ?? 0) : null;
            $kasir = $session->shifts->pluck('name')->filter()->implode("\n");
            // Nama kassa = nama akun (user) yang membuka sesi; Nama Kasir = nama kasir di tiap shift.
            $kassaName = $session->cashier?->name ?? '-';

            $rows[] = [
                'outlet' => $session->outlet?->name ?? '-',
                'kassa' => $kassaName,
                'kasir' => $kasir !== '' ? $kasir : $kassaName,
                'closed' => $closed,
                'opening' => (float) $session->opening_cash,
                'tunai' => $tunai,
                'non_cash' => $nonCash,
                'total_non_cash' => array_sum($nonCash),
                'total_income' => $tunai + array_sum($nonCash),
                'bon' => $bon,
                'setoran' => $setoranTutup,
                'total_setoran' => $bon + (float) ($setoranTutup ?? 0),
            ];
        }

        $methods = array_keys($methods);
        sort($methods, SORT_NATURAL | SORT_FLAG_CASE);

        return $this->data = ['rows' => $rows, 'methods' => $methods, 'open' => $open];
    }

    protected function makeMeta(): ReportMeta
    {
        $this->resolve();
        $data = $this->data();
        $range = [$this->date->copy()->startOfDay(), $this->date->copy()->endOfDay()];

        $base = ReportMeta::fromRequest($this->request, 'LAPORAN PENJUALAN KASSA HARIAN', 'Kassa-Harian', ['outlet'], null, $range);

        $info = $base->info + [
            'Jumlah Sesi' => number_format(count($data['rows']), 0, ',', '.').' sesi',
            'Belum Tutup' => number_format($data['open'], 0, ',', '.').' sesi',
        ];

        return new ReportMeta(
            $base->title,
            $base->fileLabel,
            $base->start,
            $base->end,
            $info,
            $base->settings,
            $base->printedBy,
            $base->printedAt,
            $base->snapshot,
        );
    }

    protected function sheetName(): string
    {
        return 'Kassa Harian';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $data = $this->data();
        $methods = $data['methods'];
        $rp = Theme::FMT_RUPIAH;

        $cols = [
            ['h' => 'Nama Kassa', 'max' => 30],
            ['h' => 'Nama Kasir', 'wrap' => true, 'w' => 30],
            ['h' => 'Saldo Awal', 'fmt' => $rp],
            ['h' => 'Penjualan Tunai', 'fmt' => $rp],
        ];
        foreach ($methods as $method) {
            $cols[] = ['h' => $method, 'fmt' => $rp];
        }
        $cols = array_merge($cols, [
            ['h' => 'Total Non Tunai', 'fmt' => $rp],
            ['h' => 'Total Pendapatan', 'fmt' => $rp],
            ['h' => 'Bon', 'fmt' => $rp],
            ['h' => 'Setoran', 'fmt' => $rp],
            ['h' => 'Setoran Total', 'fmt' => $rp],
        ]);

        $w->columns($cols)->kop()->info()->header();

        $lastCol = count($cols);
        $outletNow = null;
        $perOutlet = ! $this->outletId;

        foreach ($data['rows'] as $row) {
            if ($perOutlet && $row['outlet'] !== $outletNow) {
                $outletNow = $row['outlet'];
                $w->section($outletNow);
            }

            $values = [
                $row['kassa'].($row['closed'] ? '' : ' (BELUM TUTUP)'),
                $row['kasir'],
                $row['opening'],
                $row['tunai'],
            ];
            foreach ($methods as $method) {
                $values[] = (float) ($row['non_cash'][$method] ?? 0.0);
            }
            array_push($values, $row['total_non_cash'], $row['total_income'], $row['bon'], $row['setoran'] ?? '-', $row['total_setoran']);

            $w->row($values, $row['closed'] ? [] : ['fill' => Theme::WARN_BG, 'font' => Theme::WARN_FG]);
        }

        if ($data['rows']) {
            $calc = [];
            for ($c = 3; $c <= $lastCol; $c++) {
                $calc[$c] = 'sum';
            }
            $w->totalRow('TOTAL', $calc, ['grand' => true]);
        }
    }
}