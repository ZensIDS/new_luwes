<?php

namespace App\Exports\LaporanToko;

use App\Models\Outlet;
use App\Models\Promotion;
use App\Models\User;
use App\Support\OutletAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Metadata satu laporan: judul, periode, filter yang dipakai, pencetak,
 * pengaturan perusahaan (kop), nama file, dan properti dokumen (A2, A3, A8, A9).
 */
final class ReportMeta
{
    /**
     * @param array<string,string> $info Baris info tambahan (label => nilai), tampil setelah "Periode".
     * @param array<string,mixed>  $settings Isi settings.json.
     */
    public function __construct(
        public readonly string $title,
        public readonly string $fileLabel,
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly array $info,
        public readonly array $settings,
        public readonly string $printedBy,
        public readonly Carbon $printedAt,
        public readonly ?Carbon $snapshot = null,
    ) {}

    /**
     * @param string   $title     Judul di sheet, mis. "LAPORAN PENJUALAN OUTLET".
     * @param string   $fileLabel Bagian nama file, mis. "Penjualan-Toko".
     * @param string[] $show      Filter yang ditampilkan: 'outlet', 'kasir', 'rafaksi'.
     * @param ?array   $range     [Carbon awal, Carbon akhir] untuk menimpa periode dari request (mis. periode rafaksi terpilih).
     * @param ?Carbon  $snapshot  Isi untuk laporan posisi (stok) per satu tanggal: info "Per Tanggal" menggantikan "Periode".
     */
    public static function fromRequest(Request $request, string $title, string $fileLabel, array $show = [], ?Carbon $snapshot = null, ?array $range = null): self
    {
        [$start, $end] = $range ?? self::dateRange($request);

        $info = [];
        if (in_array('outlet', $show, true)) {
            $info['Outlet'] = self::outletLabel($request);
        }
        if (in_array('kasir', $show, true)) {
            $info['Kasir'] = self::cashierLabel($request);
        }
        if (in_array('rafaksi', $show, true)) {
            $info['Rafaksi'] = self::promotionLabel($request);
        }

        return new self(
            $title,
            $fileLabel,
            $start,
            $end,
            $info,
            self::settings(),
            (string) ($request->user()?->name ?? '-'),
            now(),
            $snapshot,
        );
    }

    /** Nama file tanpa membuat objek lengkap, dipakai controller. */
    public static function fileNameFor(Request $request, string $fileLabel, ?Carbon $snapshot = null, ?array $range = null): string
    {
        [$start, $end] = $range ?? self::dateRange($request);

        return self::buildFileName($fileLabel, $start, $end, $snapshot);
    }

    public function fileName(): string
    {
        return self::buildFileName($this->fileLabel, $this->start, $this->end, $this->snapshot);
    }

    public function snapshotLabel(): ?string
    {
        return $this->snapshot?->copy()->locale('id')->isoFormat('DD MMMM YYYY');
    }

    public function periodLabel(): string
    {
        $format = fn (Carbon $d) => $d->copy()->locale('id')->isoFormat('DD MMMM YYYY');

        return $this->start->isSameDay($this->end)
            ? $format($this->start)
            : $format($this->start).' s/d '.$format($this->end);
    }

    public function printedAtLabel(): string
    {
        return $this->printedAt->copy()->locale('id')->isoFormat('DD MMMM YYYY HH:mm');
    }

    /** Properti dokumen untuk WithProperties (A9). */
    public function properties(): array
    {
        return [
            'creator'     => $this->printedBy !== '-' ? $this->printedBy : (string) config('app.name'),
            'lastModifiedBy' => $this->printedBy,
            'title'       => ucwords(strtolower($this->title)),
            'subject'     => 'Laporan Toko',
            'description' => $this->snapshot ? 'Posisi per '.$this->snapshotLabel() : 'Periode '.$this->periodLabel(),
            'company'     => (string) ($this->settings['name'] ?? config('app.name')),
            'category'    => 'Laporan',
        ];
    }

    // ── internal ────────────────────────────────────────────────────────────

    private static function buildFileName(string $fileLabel, Carbon $start, Carbon $end, ?Carbon $snapshot = null): string
    {
        $label = trim((string) preg_replace('/[^A-Za-z0-9\-]+/', '-', $fileLabel), '-');

        if ($snapshot) {
            return sprintf('Laporan-%s_%s.xlsx', $label, $snapshot->format('Y-m-d'));
        }

        return sprintf('Laporan-%s_%s_sd_%s.xlsx', $label, $start->format('Y-m-d'), $end->format('Y-m-d'));
    }

    /**
     * Sama dengan service rafaksi: bila satu rafaksi dipilih, periodenya memakai tanggal rafaksi itu.
     * Mengembalikan null bila tidak ada rafaksi terpilih atau rafaksi tidak punya tanggal.
     */
    public static function promotionRange(Request $request): ?array
    {
        if (! $request->filled('promotion_id')) {
            return null;
        }

        $promo = Promotion::find((int) $request->input('promotion_id'));
        if (! $promo) {
            return null;
        }

        [$start, $end] = self::dateRange($request);

        return [
            $promo->start_at?->copy()->startOfDay() ?? $start,
            $promo->end_at?->copy()->endOfDay() ?? $end,
        ];
    }

    /** Default sama dengan OutletLaporanService::dateRange (awal bulan s/d hari ini). */
    private static function dateRange(Request $request): array
    {
        $start = $request->filled('tanggal_mulai') ? Carbon::parse($request->input('tanggal_mulai')) : now()->startOfMonth();
        $end   = $request->filled('tanggal_selesai') ? Carbon::parse($request->input('tanggal_selesai')) : now();

        return [$start->startOfDay(), $end->endOfDay()];
    }

    private static function settings(): array
    {
        $disk = Storage::disk('public');

        return $disk->exists('settings.json')
            ? (json_decode($disk->get('settings.json') ?: '{}', true) ?? [])
            : [];
    }

    private static function outletLabel(Request $request): string
    {
        // Sama dengan service: staff-outlet/kasir otomatis terkunci ke outlet sendiri.
        $id = OutletAccess::id($request, false);

        return $id ? (Outlet::find($id)?->name ?? '-') : 'Semua Outlet';
    }

    private static function cashierLabel(Request $request): string
    {
        return $request->filled('kasir_id')
            ? (User::find((int) $request->input('kasir_id'))?->name ?? '-')
            : 'Semua Kasir';
    }

    private static function promotionLabel(Request $request): string
    {
        if (! $request->filled('promotion_id')) {
            return 'Semua Rafaksi';
        }

        $promo = Promotion::withTrashed()->find((int) $request->input('promotion_id'));
        if (! $promo) {
            return '-';
        }

        $range = implode(' s/d ', array_filter([
            $promo->start_at?->format('d/m/Y'),
            $promo->end_at?->format('d/m/Y'),
        ]));

        return $range !== '' ? "{$promo->name} ({$range})" : (string) $promo->name;
    }
}