<?php

namespace App\Exports;

use App\Exports\LaporanToko\OtherIncomeRekapSheet;
use App\Exports\LaporanToko\OtherIncomeSheet;
use App\Exports\LaporanToko\ReportMeta;
use App\Models\Outlet;
use App\Models\OtherIncomeCategory;
use App\Support\OtherIncomeFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** E1 + E2: Export Pendapatan Lain (detail + rekap per kategori), membawa filter halaman daftar. */
class OtherIncomeExport implements WithMultipleSheets
{
    private ?Builder $query = null;
    private ?ReportMeta $base = null;
    private array $info = [];

    public function __construct(private readonly Request $request) {}

    /** Nama file: Laporan-Pendapatan-Lain_<mulai>_sd_<selesai>.xlsx */
    public function fileName(): string
    {
        $this->prepare();

        return $this->base->fileName();
    }

    public function sheets(): array
    {
        $this->prepare();

        return [
            new OtherIncomeSheet($this->metaFor('LAPORAN PENDAPATAN LAIN'), $this->query),
            new OtherIncomeRekapSheet($this->metaFor('REKAP PENDAPATAN LAIN PER KATEGORI'), $this->recap()),
        ];
    }

    private function prepare(): void
    {
        if ($this->query !== null) {
            return;
        }

        $filter = OtherIncomeFilter::fromRequest($this->request); // validasi sama dengan halaman daftar
        $query = $filter->query();

        // Tanggal kosong = tanpa batas; periode untuk kop dan nama file diambil dari data.
        $min = $filter->from ?? (clone $query)->min('tanggal');
        $max = $filter->to ?? (clone $query)->max('tanggal');
        $start = Carbon::parse($min ?? $max ?? now())->startOfDay();
        $end = Carbon::parse($max ?? $min ?? now())->endOfDay();
        if ($end->lt($start)) {
            $end = $start->copy()->endOfDay();
        }

        $info = [
            'Kategori' => $filter->categoryId ? $this->categoryName($filter->categoryId) : 'Semua Kategori',
            'Outlet' => $this->outletLabel($filter),
        ];
        if ($filter->from === null || $filter->to === null) {
            $info['Catatan'] = 'Tanggal tidak dibatasi, periode mengikuti data yang ada';
        }

        $this->query = $query;
        $this->info = $info;
        $this->base = ReportMeta::fromRequest($this->request, 'LAPORAN PENDAPATAN LAIN', 'Pendapatan-Lain', [], null, [$start, $end]);
    }

    /** ReportMeta per sheet: sama persis, hanya judul dan baris filter yang berbeda. */
    private function metaFor(string $title): ReportMeta
    {
        $b = $this->base;

        return new ReportMeta($title, $b->fileLabel, $b->start, $b->end, $this->info, $b->settings, $b->printedBy, $b->printedAt, $b->snapshot);
    }

    /** E2: rekap dihitung dengan GROUP BY di database. */
    private function recap(): array
    {
        $rows = (clone $this->query)
            ->selectRaw('other_income_category_id, COUNT(*) as transaksi, COALESCE(SUM(jumlah), 0) as total')
            ->groupBy('other_income_category_id')
            ->orderByDesc('total')
            ->get();

        $names = OtherIncomeCategory::withTrashed()
            ->whereIn('id', $rows->pluck('other_income_category_id'))
            ->get()
            ->mapWithKeys(fn ($c) => [$c->id => $c->name.($c->trashed() ? ' (dihapus)' : '')]);

        return $rows->map(fn ($r) => [
            'name' => $names[$r->other_income_category_id] ?? '-',
            'count' => (int) $r->transaksi,
            'total' => (float) $r->total,
        ])->all();
    }

    private function categoryName(int $id): string
    {
        $category = OtherIncomeCategory::withTrashed()->find($id);

        return $category ? $category->name.($category->trashed() ? ' (dihapus)' : '') : '-';
    }

    private function outletLabel(OtherIncomeFilter $filter): string
    {
        if ($filter->scoped) {
            return Outlet::find($this->request->user()->outlet_id)?->name ?? '-';
        }
        if ($filter->outlet === 'umum') {
            return 'Umum (tanpa outlet)';
        }
        if ($filter->outlet !== null && ctype_digit($filter->outlet)) {
            return Outlet::find((int) $filter->outlet)?->name ?? '-';
        }

        return 'Semua Outlet';
    }
}