<?php

namespace App\Http\Controllers;

use App\Models\CashierDrawerEntry;
use App\Models\CashierSession;
use App\Support\ReceiptHeader;
use Illuminate\Http\Request;

/**
 * Struk kasir selain penjualan: (1) BON / uang keluar, (2) tutup kasir.
 * Bentuk header & footer sama dengan struk penjualan, isinya disusun di sini lalu
 * dirender satu view (cashier.print-slip) baik untuk cetak browser maupun QZ Tray.
 */
class CashierSlipController extends Controller
{
    public function bon(Request $request, CashierDrawerEntry $entry)
    {
        abort_unless($entry->type === 'bon', 404);
        $session = $entry->session()->with(['outlet', 'cashier', 'shifts'])->firstOrFail();
        $this->ensureAccess($session);

        $shift = $session->shifts
            ->filter(fn ($s) => $s->started_at && $s->started_at->lte($entry->recorded_at) && (! $s->ended_at || $s->ended_at->gte($entry->recorded_at)))
            ->last() ?? $session->shifts->last();

        // Urutan BON & akumulasi sampai BON ini, supaya hasil reprint selalu sama.
        $bons = $session->drawerEntries()->where('type', 'bon')
            ->where(fn ($q) => $q->where('recorded_at', '<', $entry->recorded_at)
                ->orWhere(fn ($q2) => $q2->where('recorded_at', $entry->recorded_at)->where('id', '<=', $entry->id)))
            ->orderBy('recorded_at')->orderBy('id')->get();

        // Semua nama kasir (shift) yang terdaftar di kassa/sesi ini, bukan hanya yang sedang aktif.
        $names = $session->shifts->pluck('name')->filter()->unique()->implode(', ');

        $meta = [
            ['No', 'BON-' . str_pad((string) $entry->id, 5, '0', STR_PAD_LEFT)],
            ['Tgl', $entry->recorded_at?->format('d/m/Y H:i') ?? '-'],
            ['Kassa', (string) ($session->cashier?->name ?? '-')],
            ['Kasir', $names !== '' ? $names : '-'],
            ['BON oleh', (string) ($shift?->name ?? '-')],
            ['Buka kasir', $session->opened_at?->format('d/m/Y H:i') ?? '-'],
        ];

        $sections = [[
            'title' => null,
            'rows' => array_values(array_filter([
                ['NOMINAL BON', 'Rp ' . $this->n($entry->amount), 1],
                ['BON ke', (string) $bons->count(), 0],
                ['Total BON sesi', 'Rp ' . $this->n($bons->sum('amount')), 0],
            ])),
            'lines' => filled($entry->note) ? ['Ket: ' . trim((string) $entry->note)] : [],
        ]];

        return $this->render($request, $session, [
            'title' => 'BON / UANG KELUAR',
            'docTitle' => 'BON-' . str_pad((string) $entry->id, 5, '0', STR_PAD_LEFT),
            'meta' => $meta,
            'sections' => $sections,
        ], route('outlet.show', $session->outlet_id));
    }

    public function close(Request $request, CashierSession $cashierSession)
    {
        abort_unless($cashierSession->status === 'closed', 404, 'Sesi kasir belum ditutup.');
        $this->ensureAccess($cashierSession);
        $session = $cashierSession->load(['outlet', 'cashier', 'shifts', 'drawerEntries']);

        $breakdown = CashierSession::breakdownFor([(int) $session->id])[(int) $session->id] ?? CashierSession::emptyBreakdown();
        $names = $session->shifts->pluck('name')->filter()->unique()->implode(', ');
        $diff = (float) $session->discrepancy;

        $meta = [
            ['Kassa', (string) ($session->cashier?->name ?? '-')],
            ['Kasir', $names !== '' ? $names : '-'],
            ['Buka', $session->opened_at?->format('d/m/Y H:i') ?? '-'],
            ['Tutup', $session->closed_at?->format('d/m/Y H:i') ?? '-'],
        ];

        // Urutan slip tutup kasir (tanpa judul "PENJUALAN" dan tanpa "RINCIAN BON"):
        //   BON, ATM/QRIS, Voucher, Uang fisik
        //   -----
        //   TUNAI: TOTAL, Tunai Komputer
        //   -----
        //   Selisih
        // ATM/QRIS = SEMUA penjualan non-tunai (ATM, QRIS, transfer, dll) digabung jadi satu baris.
        // TOTAL = jumlah baris di atas garis: BON + ATM/QRIS + Voucher + Uang fisik.
        // Tunai Komputer = uang tunai yang seharusnya ada menurut sistem (expected_cash), rumusnya tidak berubah.
        $nonCashCount = (int) collect($breakdown['payments'])
            ->reject(fn ($p) => (bool) ($p['is_cash'] ?? CashierSession::isCashPaymentName($p['name'] ?? '')))
            ->sum('count');
        $nonCashTotal = (float) $breakdown['non_cash_total'];
        $hasVoucher = (int) $breakdown['voucher_count'] > 0;
        $voucherTotal = $hasVoucher ? (float) $breakdown['voucher_total'] : 0.0;
        $bonTotal = (float) $session->cash_out;
        $closingCash = (float) $session->closing_cash;

        $topRows = [
            ['BON', $this->n($bonTotal), 0],
            ['ATM/QRIS (' . $nonCashCount . ')', $this->n($nonCashTotal), 0],
        ];
        if ($hasVoucher) {
            $topRows[] = ['Voucher ' . $breakdown['voucher_count'] . ' lbr', $this->n($voucherTotal), 0];
        }
        $topRows[] = ['Uang fisik', 'Rp ' . $this->n($closingCash), 1];

        $cashRows = [
            ['TOTAL', 'Rp ' . $this->n($bonTotal + $nonCashTotal + $voucherTotal + $closingCash), 1],
        ];
        if ((float) $session->cash_in > 0) {
            $cashRows[] = ['Kas masuk', $this->n($session->cash_in), 0];
        }
        $cashRows[] = ['Tunai Komputer', 'Rp ' . $this->n($session->expected_cash), 1];

        $diffRows = [
            ['Selisih', $diff == 0 ? 'Pas' : (($diff > 0 ? '+' : '-') . $this->n(abs($diff))), 1],
        ];

        $sections = [
            ['title' => null, 'rows' => $topRows, 'lines' => []],
            ['title' => 'TUNAI', 'rows' => $cashRows, 'lines' => []],
            ['title' => null, 'rows' => $diffRows, 'lines' => []],
        ];
        if (filled($session->closing_note)) {
            $sections[] = ['title' => null, 'rows' => [], 'lines' => ['Catatan: ' . trim((string) $session->closing_note)]];
        }

        return $this->render($request, $session, [
            'title' => 'TUTUP KASIR',
            'docTitle' => 'Tutup kasir ' . $session->closed_at?->format('d-m-Y H:i'),
            'meta' => $meta,
            'sections' => $sections,
        ], route('penjualan.index'));
    }

    /** Siapkan data umum (header outlet, footer, kembali ke mana) lalu tampilkan view. */
    private function render(Request $request, CashierSession $session, array $slip, string $defaultBack)
    {
        $outlet = $session->outlet;
        $footerHtml = (string) ($outlet?->footer ?? '');
        $footerLines = [];
        if ($footerHtml !== '') {
            $text = html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/(p|div|li)>/i', "\n", $footerHtml)));
            $footerLines = array_values(array_filter(array_map('trim', explode("\n", $text)), 'strlen'));
        }
        if (! $footerLines) {
            $footerLines = ['Terima kasih'];
        }

        $slip['store'] = ReceiptHeader::STORE;
        $slip['header'] = ReceiptHeader::LINES;
        $slip['footerLines'] = $footerLines;
        $slip['footerHtml'] = $footerHtml;
        $slip['printedAt'] = now()->format('d/m/Y H:i:s');

        $isReprint = $request->boolean('reprint');
        if ($isReprint) {
            $slip['meta'][] = ['Cetak', 'Reprint'];
        }

        // Setelah auto print kembali ke: history (bila dari reprint di history) atau halaman default.
        $back = $request->input('from') === 'history' ? route('cashier.history') : $defaultBack;

        // Toast "berhasil disimpan" dari redirect sebelumnya jangan hilang saat melewati halaman cetak ini.
        if ($request->boolean('auto')) {
            $request->session()->reflash();
        }

        return view('cashier.print-slip', [
            'slip' => $slip,
            'isReprint' => $isReprint,
            'back' => $back,
        ]);
    }

    private function ensureAccess(CashierSession $session): void
    {
        $user = auth()->user();
        if (in_array($user?->role, ['staff-outlet', 'kasir'], true)) {
            abort_unless((int) $session->outlet_id === (int) $user->outlet_id, 403);
        }
    }

    private function n($value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }
}