<?php

namespace App\Exports;

use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\Outlet;
use App\Models\OwnerStock;
use App\Models\Product;
use App\Services\PriceFolderIndex;
use App\Support\OutletAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Laporan Toko: Stok Toko Harian (cek stok tiap pagi).
 *
 * Kolom: Kode, Nama Barang, Stok Awal, S. Masuk, S. Keluar, Sisa.
 *   Stok Awal  = saldo semua pergerakan SEBELUM tanggal laporan (00:00).
 *   S. Masuk   = jumlah qty_in pada tanggal laporan (penerimaan, retur/pembatalan penjualan, koreksi tambah).
 *   S. Keluar  = jumlah qty_out pada tanggal laporan (penjualan, koreksi kurang, dll).
 *   Sisa       = Stok Awal + S. Masuk - S. Keluar.
 *
 * Sumber: tabel stock_movements per outlet (owner_id), sama dengan Kartu Stok Toko.
 * Pasangan "Pelunasan stok minus" (pindah saldo antar batch) tidak dihitung karena hanya
 * memindahkan saldo dan bukan barang masuk/keluar sungguhan.
 *
 * Bila tanggal laporan = hari ini, Sisa dibandingkan dengan stok sistem saat ini (owner_stocks);
 * barang yang tidak sama diberi warna kuning dan kolom Catatan.
 *
 * Parameter request: tanggal (Y-m-d, default hari ini), outlet_id (opsional; staff-outlet terkunci ke outletnya),
 * brand (opsional; NAMA DEPAN = kata pertama nama barang, sama dengan folder Master Harga Jual. Kosong = semua brand).
 */
class StokTokoHarianExport extends BaseLaporanTokoExport
{
    /** Awalan catatan pergerakan internal antar batch (lihat OutletStockService::settleDeficit). */
    private const INTERNAL_NOTE = 'Pelunasan stok minus%';

    private ?int $outletId = null;
    private ?Carbon $date = null;
    private ?string $brand = null;
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
        // Brand = kata pertama nama barang (huruf besar), persis kunci folder Master Harga Jual.
        $brand = PriceFolderIndex::normalize((string) $this->request->input('brand', ''));
        $brand = preg_split('/\s+/', $brand, -1, PREG_SPLIT_NO_EMPTY)[0] ?? '';
        $this->brand = $brand !== '' ? $brand : null;
        $this->resolved = true;
    }

    public function fileName(): string
    {
        $this->resolve();
        $outlet = $this->outletId
            ? (string) Outlet::whereKey($this->outletId)->value('name')
            : 'Semua-Outlet';
        $slug = trim((string) preg_replace('/[^A-Za-z0-9\-]+/', '-', $outlet), '-') ?: 'Outlet';

        $brandSlug = $this->brand
            ? '-'.trim((string) preg_replace('/[^A-Za-z0-9\-]+/', '-', $this->brand), '-')
            : '';

        return sprintf('Laporan-Stok-Toko-Harian-%s%s_%s.xlsx', $slug, $brandSlug, $this->date->format('Y-m-d'));
    }

    /**
     * @return array{outlets: array<int,array{name:string,rows:array<int,array<string,mixed>>}>, count:int, mismatch:int, check:bool}
     */
    private function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }
        $this->resolve();

        $start = $this->date->copy()->startOfDay()->toDateTimeString();
        $end = $this->date->copy()->endOfDay()->toDateTimeString();
        $check = $this->date->isToday();

        $outletNames = $this->outletId
            ? Outlet::whereKey($this->outletId)->pluck('name', 'id')
            : Outlet::query()->whereIn('id', OutletAccess::outlets()->pluck('id'))->orderBy('name')->pluck('name', 'id');
        $outletIds = $outletNames->keys()->map(fn ($id) => (int) $id)->all();

        if (! $outletIds) {
            return $this->data = ['outlets' => [], 'count' => 0, 'mismatch' => 0, 'check' => $check];
        }

        // Filter brand: hanya produk yang kata pertama namanya sama persis dengan brand yang dipilih.
        $brandProducts = $this->brand
            ? Product::withTrashed()->select('products.id')->whereRaw(PriceFolderIndex::keySql().' = ?', [$this->brand])
            : null;

        // Satu query agregat: awal / masuk / keluar per outlet + produk.
        $movements = DB::table('stock_movements')
            ->whereIn('owner_id', $outletIds)
            ->when($brandProducts, fn ($query) => $query->whereIn('product_id', $brandProducts))
            ->where('created_at', '<=', $end)
            ->where(function ($query) {
                $query->whereNull('notes')->orWhere('notes', 'not like', self::INTERNAL_NOTE);
            })
            ->groupBy('owner_id', 'product_id')
            ->selectRaw(
                'owner_id, product_id,
                 SUM(CASE WHEN created_at < ? THEN qty_in - qty_out ELSE 0 END) AS awal,
                 SUM(CASE WHEN created_at >= ? THEN qty_in ELSE 0 END) AS masuk,
                 SUM(CASE WHEN created_at >= ? THEN qty_out ELSE 0 END) AS keluar',
                [$start, $start, $start]
            )
            ->get();

        $lines = [];
        foreach ($movements as $m) {
            $lines[$m->owner_id.'-'.$m->product_id] = [
                'outlet_id' => (int) $m->owner_id,
                'product_id' => (int) $m->product_id,
                'awal' => (int) $m->awal,
                'masuk' => (int) $m->masuk,
                'keluar' => (int) $m->keluar,
            ];
        }

        // Hari ini: bandingkan dengan stok sistem sekarang (termasuk saldo batch MINUS yang negatif).
        $physical = collect();
        if ($check) {
            $physical = OwnerStock::query()
                ->whereIn('owner_id', $outletIds)
                ->when($brandProducts, fn ($query) => $query->whereIn('product_id', $brandProducts))
                ->groupBy('owner_id', 'product_id')
                ->selectRaw('owner_id, product_id, SUM(qty) AS qty')
                ->get()
                ->mapWithKeys(fn ($row) => [$row->owner_id.'-'.$row->product_id => [
                    'outlet_id' => (int) $row->owner_id,
                    'product_id' => (int) $row->product_id,
                    'qty' => (int) $row->qty,
                ]]);

            // Barang yang ada di stok sistem tetapi belum punya riwayat pergerakan sama sekali.
            foreach ($physical as $key => $row) {
                if (! isset($lines[$key]) && $row['qty'] !== 0) {
                    $lines[$key] = [
                        'outlet_id' => $row['outlet_id'],
                        'product_id' => $row['product_id'],
                        'awal' => 0,
                        'masuk' => 0,
                        'keluar' => 0,
                    ];
                }
            }
        }

        $products = Product::withTrashed()
            ->whereIn('id', collect($lines)->pluck('product_id')->unique()->all())
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        $outlets = [];
        $count = 0;
        $mismatch = 0;

        foreach ($lines as $key => $line) {
            $sisa = $line['awal'] + $line['masuk'] - $line['keluar'];
            $note = null;

            if ($check) {
                $system = (int) ($physical[$key]['qty'] ?? 0);
                if ($system !== $sisa) {
                    $note = sprintf('Beda dengan stok sistem saat ini: %s (selisih %s)', number_format($system, 0, ',', '.'), number_format($system - $sisa, 0, ',', '.'));
                    $mismatch++;
                }
            }

            // Barang yang tidak bergerak dan saldonya nol tidak perlu tampil.
            if ($line['awal'] === 0 && $line['masuk'] === 0 && $line['keluar'] === 0 && $sisa === 0 && $note === null) {
                continue;
            }

            $product = $products[$line['product_id']] ?? null;

            $outlets[$line['outlet_id']]['rows'][] = [
                'code' => (string) ($product?->code ?? '-'),
                'name' => (string) ($product?->name ?? '(produk #'.$line['product_id'].' tidak ditemukan)'),
                'awal' => $line['awal'],
                'masuk' => $line['masuk'],
                'keluar' => $line['keluar'],
                'sisa' => $sisa,
                'note' => $note,
            ];
            $count++;
        }

        $ordered = [];
        foreach ($outletNames as $id => $name) {
            if (empty($outlets[$id]['rows'])) {
                continue;
            }
            $rows = $outlets[$id]['rows'];
            usort($rows, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']) ?: strnatcasecmp($a['code'], $b['code']));
            $ordered[$id] = ['name' => (string) $name, 'rows' => $rows];
        }

        return $this->data = ['outlets' => $ordered, 'count' => $count, 'mismatch' => $mismatch, 'check' => $check];
    }

    protected function makeMeta(): ReportMeta
    {
        $this->resolve();
        $data = $this->data();
        $range = [$this->date->copy()->startOfDay(), $this->date->copy()->endOfDay()];

        $base = ReportMeta::fromRequest($this->request, 'LAPORAN STOK TOKO HARIAN', 'Stok-Toko-Harian', ['outlet'], null, $range);

        $info = $base->info + [
            'Brand' => $this->brand ?? 'Semua Brand',
            'Jumlah Barang' => number_format($data['count'], 0, ',', '.').' barang',
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
        return 'Stok Harian';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $data = $this->data();
        $num = Theme::FMT_NUMBER;
        $showNote = $data['check'] && $data['mismatch'] > 0;

        $cols = [
            ['h' => 'Kode', 'max' => 22],
            ['h' => 'Nama Barang', 'max' => 55],
            ['h' => 'Stok Awal', 'fmt' => $num],
            ['h' => 'S. Masuk', 'fmt' => $num],
            ['h' => 'S. Keluar', 'fmt' => $num],
            ['h' => 'Sisa', 'fmt' => $num],
        ];
        if ($showNote) {
            $cols[] = ['h' => 'Catatan', 'wrap' => true, 'w' => 42];
        }

        $w->columns($cols)->kop()->info();

        if ($showNote) {
            $w->notice(sprintf(
                '%s barang punya Sisa berbeda dengan stok sistem saat ini (baris kuning). Cek penyebabnya sebelum menyesuaikan stok.',
                number_format($data['mismatch'], 0, ',', '.')
            ));
        } elseif (! $data['outlets']) {
            $w->notice($this->brand
                ? "Tidak ada barang dengan nama depan \"{$this->brand}\" yang bergerak / berstok pada tanggal ini. Pilih brand dari saran yang muncul saat mengetik."
                : 'Tidak ada pergerakan stok pada tanggal ini.');
        }

        $w->header();

        $perOutlet = ! $this->outletId;

        foreach ($data['outlets'] as $outlet) {
            if ($perOutlet) {
                $w->section($outlet['name']);
            }

            foreach ($outlet['rows'] as $row) {
                $values = [$row['code'], $row['name'], $row['awal'], $row['masuk'], $row['keluar'], $row['sisa']];
                if ($showNote) {
                    $values[] = $row['note'] ?? '';
                }

                $opts = $row['note'] !== null ? ['fill' => Theme::WARN_BG, 'font' => Theme::WARN_FG] : [];
                if ($row['sisa'] < 0) {
                    $opts['cells'][6] = ['fill' => Theme::DANGER_BG, 'font' => Theme::DANGER_FG, 'bold' => true];
                }

                $w->row($values, $opts);
            }
        }

        if ($data['outlets']) {
            $w->totalRow('TOTAL', [3 => 'sum', 4 => 'sum', 5 => 'sum', 6 => 'sum'], ['grand' => true]);
        }
    }
}