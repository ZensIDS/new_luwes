<?php

namespace App\Exports;

use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Exports\LaporanToko\Theme;
use App\Models\Outlet;
use App\Models\Product;
use App\Support\OutletAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Laporan Toko: Set Stock satu outlet, yang sudah / belum di-set dalam rentang tanggal.
 *
 * Parameter request: outlet_id (wajib), jenis = sudah | belum | semua (default semua),
 * tanggal_mulai, tanggal_selesai (default hari ini, WIB; hanya berpengaruh pada yang "sudah").
 *
 * - Sudah di-set : produk yang masuk set_stock_draft_items outlet ini pada rentang tanggal (waktu item, WIB).
 *                  Draft done = "Sudah di-set"; draft active (masih di keranjang) = "Di keranjang (belum disimpan)".
 *                  Bila satu produk muncul di beberapa draft, diambil yang terbaru.
 * - Belum di-set : produk yang harga jualnya sudah diatur di outlet (Master Harga Jual, aktif) dan tidak ada di
 *                  daftar "sudah" pada rentang tanggal itu. Qty ditulis "-".
 * Hanya produk yang harga jualnya sudah diatur.
 */
class SetStockExport extends BaseLaporanTokoExport
{
    private const TZ = 'Asia/Jakarta';

    private ?int $outletId = null;
    private string $jenis = 'semua';
    private string $namaAwal = '';
    private ?Carbon $start = null;
    private ?Carbon $end = null;
    private bool $resolved = false;
    private ?array $data = null;

    public function __construct(private readonly Request $request) {}

    /** Outlet wajib dipilih; staff-outlet otomatis terkunci ke outlet sendiri (aturan OutletAccess). */
    private function resolve(): void
    {
        if ($this->resolved) {
            return;
        }

        $this->outletId = OutletAccess::id($this->request, true);

        $jenis = (string) $this->request->input('jenis', 'semua');
        $this->jenis = in_array($jenis, ['sudah', 'belum', 'semua'], true) ? $jenis : 'semua';

        $this->namaAwal = trim((string) $this->request->input('nama_awal', ''));

        $today = now(self::TZ)->toDateString();
        $start = Carbon::parse($this->request->input('tanggal_mulai') ?: $today, self::TZ)->startOfDay();
        $end = Carbon::parse($this->request->input('tanggal_selesai') ?: $today, self::TZ)->endOfDay();
        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }
        $this->start = $start;
        $this->end = $end;

        $this->resolved = true;
    }

    private function jenisLabel(): string
    {
        return ['sudah' => 'Sudah di-set', 'belum' => 'Belum di-set', 'semua' => 'Semua (sudah + belum)'][$this->jenis];
    }

    /**
     * @return array{sudah: Collection, belum: Collection}  baris: [barcode, nama, kategori, qty|null, status, kode status]
     */
    private function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }
        $this->resolve();

        // Produk dengan harga jual yang sudah diatur (aturan harga aktif) di outlet ini, A-Z.
        $priced = Product::query()
            ->leftJoin('categories as c', 'c.id', '=', 'products.category_id')
            ->whereHas('outletPrices', fn ($price) => $price->where('outlet_id', $this->outletId)->currentlyActive())
            ->when($this->namaAwal !== '', fn ($query) => $query->where('products.name', 'like', addcslashes($this->namaAwal, '\\%_').'%'))
            ->orderBy('products.name')
            ->toBase()
            ->get(['products.id', 'products.code', 'products.name', 'c.name as category_name'])
            ->keyBy('id');

        // Rentang tanggal (WIB) dikonversi ke zona waktu aplikasi karena kolom timestamp disimpan di zona itu.
        $from = $this->start->copy()->setTimezone(config('app.timezone'))->toDateTimeString();
        $to = $this->end->copy()->setTimezone(config('app.timezone'))->toDateTimeString();

        $doneRows = DB::table('set_stock_draft_items as i')
            ->join('set_stock_drafts as d', 'd.id', '=', 'i.draft_id')
            ->where('d.outlet_id', $this->outletId)
            ->whereBetween('i.updated_at', [$from, $to])
            ->orderByDesc('i.updated_at')
            ->orderByDesc('i.id')
            ->get(['i.product_id', 'i.qty', 'd.status as draft_status'])
            ->unique('product_id')                                  // terbaru per produk
            ->filter(fn ($row) => $priced->has($row->product_id));   // hanya yang harga jualnya sudah diatur

        $sudah = $doneRows
            ->map(function ($row) use ($priced) {
                $product = $priced->get($row->product_id);
                $inBasket = $row->draft_status === 'active';

                return [
                    $product->code,
                    $product->name,
                    $product->category_name,
                    (int) $row->qty,
                    $inBasket ? 'Di keranjang (belum disimpan)' : 'Sudah di-set',
                    $inBasket ? 'keranjang' : 'sudah',
                ];
            })
            ->sortBy(fn ($row) => mb_strtolower((string) $row[1]))
            ->values();

        $doneIds = $doneRows->pluck('product_id')->flip();
        $belum = $priced
            ->reject(fn ($product) => $doneIds->has($product->id))
            ->map(fn ($product) => [$product->code, $product->name, $product->category_name, null, 'Belum di-set', 'belum'])
            ->values();

        return $this->data = ['sudah' => $sudah, 'belum' => $belum];
    }

    protected function makeMeta(): ReportMeta
    {
        $this->resolve();
        $data = $this->data();

        $outletName = Outlet::whereKey($this->outletId)->value('name') ?? '-';
        $label = ['sudah' => 'Sudah', 'belum' => 'Belum', 'semua' => 'Semua'][$this->jenis];
        $range = [$this->start, $this->end];

        $base = ReportMeta::fromRequest($this->request, 'LAPORAN SET STOCK', 'Set-Stock-'.$label, [], null, $range);

        $info = ['Outlet' => $outletName, 'Jenis' => $this->jenisLabel()];
        if ($this->namaAwal !== '') {
            $info['Nama diawali'] = $this->namaAwal;
        }
        if ($this->jenis !== 'belum') {
            $info['Sudah di-set'] = number_format($data['sudah']->count(), 0, ',', '.').' barang';
        }
        if ($this->jenis !== 'sudah') {
            $info['Belum di-set'] = number_format($data['belum']->count(), 0, ',', '.').' barang';
        }

        return new ReportMeta(
            $base->title,
            'Set-Stock-'.$label.'-'.preg_replace('/[^A-Za-z0-9]+/', '-', $outletName),
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
        return 'Set Stock';
    }

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        $data = $this->data();

        $w->columns([
            ['h' => 'Barcode', 'text' => true, 'align' => 'left', 'min' => 16],
            ['h' => 'Nama Barang', 'max' => 60],
            ['h' => 'Kategori', 'max' => 28],
            ['h' => 'Qty', 'fmt' => Theme::FMT_NUMBER, 'align' => 'right', 'min' => 8],
            ['h' => 'Status', 'align' => 'left', 'min' => 24],
        ])->kop()->info()->header();

        // Yang sudah di atas, yang belum di bawah.
        $rows = match ($this->jenis) {
            'sudah' => $data['sudah'],
            'belum' => $data['belum'],
            default => $data['sudah']->concat($data['belum']),
        };

        $statusStyle = [
            'sudah' => ['fill' => Theme::OK_BG, 'font' => Theme::OK_FG],
            'keranjang' => ['fill' => Theme::INFO_BG, 'font' => Theme::INFO_FG],
            'belum' => ['fill' => Theme::WARN_BG, 'font' => Theme::WARN_FG],
        ];

        foreach ($rows as $row) {
            [$barcode, $name, $category, $qty, $status, $code] = $row;
            $w->row([
                $barcode ?: null,
                $name ?: null,
                $category ?: null,
                $qty === null ? '-' : $qty,   // belum di-set: qty "-"
                $status,
            ], ['cells' => [5 => $statusStyle[$code]]]);
        }

        // Tanpa data: tidak ada baris total supaya pesan "Tidak ada data" tampil.
        if ($rows->isNotEmpty()) {
            $calc = [2 => 'count'];
            $fmt = [2 => '#,##0" barang"'];
            if ($this->jenis !== 'belum') {
                $calc[4] = 'sum';
                $fmt[4] = Theme::FMT_NUMBER;
            }
            $w->totalRow('TOTAL', $calc, ['grand' => true, 'fmt' => $fmt]);
        }
    }
}