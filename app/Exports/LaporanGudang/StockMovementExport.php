<?php

namespace App\Exports\LaporanGudang;

use App\Exports\LaporanToko\BaseLaporanTokoExport;
use App\Exports\LaporanToko\ReportMeta;
use App\Exports\LaporanToko\SheetWriter;
use App\Support\ReportQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Dasar laporan berbasis StockMovement (Barang Masuk, Barang Keluar, Aktivitas) yang datanya bisa besar.
 *  - Dibaca per potongan (chunkById) dan langsung ditulis ke sheet, tanpa menumpuk koleksi (A3).
 *  - Dibatasi MAX_ROWS baris; bila lebih, sheet memuat peringatan jelas (A7).
 *  - Urutan baris mengikuti id (sama dengan urutan waktu dibuat).
 */
abstract class StockMovementExport extends BaseLaporanTokoExport
{
    /** Batas aman baris data per file; naikkan hanya bila memori server mencukupi. */
    public const MAX_ROWS = 20000;

    private const CHUNK = 2000;

    public function __construct(protected readonly Request $request) {}

    /** Definisi kolom untuk SheetWriter::columns(). */
    abstract protected function columnDefs(): array;

    /** Query dasar (tanpa filter tanggal), mis. StockMovement::query()->where('qty_in', '>', 0). */
    abstract protected function baseQuery(): Builder;

    /** Relasi dokumen referensi yang dimuat per potongan, format ReportQuery::resolveReferences. */
    abstract protected function referenceRelations(): array;

    /** Satu baris: [nilai berurutan, opsi SheetWriter::row]. */
    abstract protected function movementRow(object $movement, array $refs): array;

    /** Baris total di akhir tabel. */
    abstract protected function addTotals(SheetWriter $w): void;

    protected function build(SheetWriter $w, Worksheet $sheet, ReportMeta $meta): void
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        $query = ReportQuery::betweenDates(
            $this->baseQuery(),
            'created_at',
            $meta->start->toDateString(),
            $meta->end->toDateString()
        );

        $total = (clone $query)->count();

        $w->columns($this->columnDefs())->kop()->info();

        if ($total > self::MAX_ROWS) {
            $w->notice(sprintf(
                'Data dipotong: menampilkan %s dari %s baris (urut dari yang paling awal). Persempit periode agar semua data tampil.',
                number_format(self::MAX_ROWS, 0, ',', '.'),
                number_format($total, 0, ',', '.')
            ));
        }

        $w->header();

        $written = 0;
        $query->with('product')->chunkById(self::CHUNK, function ($chunk) use ($w, &$written) {
            $refs = ReportQuery::resolveReferences($chunk, $this->referenceRelations());

            foreach ($chunk as $movement) {
                if ($written >= self::MAX_ROWS) {
                    return false;
                }

                [$values, $opts] = $this->movementRow($movement, $refs);
                $w->row($values, $opts);
                $written++;
            }

            return $written < self::MAX_ROWS;
        });

        $this->addTotals($w);
    }

    /** Batch/SKU dari catatan pergerakan ("... SKU: ABC123 ..."); stok movement belum punya kolom batch. */
    protected function batchFromNotes(?string $notes): ?string
    {
        return preg_match('/SKU:\s*([^\s,]+)/', (string) $notes, $m) ? $m[1] : null;
    }
}