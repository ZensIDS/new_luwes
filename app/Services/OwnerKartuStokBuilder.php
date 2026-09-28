<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\OutletPurchase;
use App\Models\OwnerStock;
use App\Models\Product;
use App\Models\RefundPembelian;
use App\Models\RefundPenjualan;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

/**
 * Versi toko dari KartuStokBuilder (gudang).
 *
 * Kartu stok dihitung per PRODUK (bukan seluruh tabel) dan running balance dihitung
 * per batch (owner_stocks) secara independen, sama seperti gudang menghitung per SKU.
 *
 * Stok fisik = owner_stocks.qty (sumber kebenaran). Saldo kartu = total Masuk - Keluar di
 * stock_movements. Selisih keduanya ditampilkan apa adanya.
 *
 * Query dibuat konstan (tidak bergantung jumlah baris): 1x batch, 1x movement,
 * 1x adjustment, 1x outlet, dan 1x supplier pembelian langsung.
 */
class OwnerKartuStokBuilder
{
    /**
     * @param  iterable<int>  $outletIds  outlet yang boleh diakses user
     * @param  int|null       $outletId   filter satu outlet (null = semua outlet yang boleh diakses)
     */
    public function build(Product $product, iterable $outletIds, ?int $outletId = null): array
    {
        $scope = $outletId ? [$outletId] : collect($outletIds)->values()->all();

        $outletNames = Outlet::whereIn('id', $scope)->pluck('name', 'id');

        // Semua batch produk ini di outlet terkait.
        $stocks = OwnerStock::with('stock.pembelian.supplier')
            ->whereIn('owner_id', $scope)
            ->where('product_id', $product->id)
            ->orderBy('owner_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // Supplier untuk batch hasil pembelian langsung outlet (1x query).
        $purchaseIds = $stocks
            ->where('source_type', OutletPurchase::class)
            ->pluck('source_id')
            ->filter()
            ->unique()
            ->values();

        $purchaseSuppliers = $purchaseIds->isNotEmpty()
            ? OutletPurchase::withTrashed()
                ->with('supplier:id,name')
                ->whereIn('id', $purchaseIds)
                ->get()
                ->mapWithKeys(fn ($purchase) => [$purchase->id => $purchase->supplier?->name])
            : collect();

        // Semua pergerakan produk ini di outlet terkait (1x query).
        $movements = StockMovement::whereIn('owner_id', $scope)
            ->where('product_id', $product->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get([
                'id', 'owner_id', 'owner_stock_id', 'type', 'reference_type',
                'reference_id', 'qty_in', 'qty_out', 'notes', 'created_at',
            ]);

        $stockIds = $stocks->pluck('id')->flip();

        // Movement yang batch-nya tidak ditemukan (batch dihapus / owner_stock_id kosong)
        // tetap ditampilkan, selisihnya ikut terlihat di ringkasan.
        [$assigned, $unassigned] = $movements->partition(
            fn ($movement) => $movement->owner_stock_id && $stockIds->has($movement->owner_stock_id)
        );

        $assignedByStock = $assigned->groupBy('owner_stock_id');
        $keteranganMap   = $this->buildKeteranganMap($movements);

        $rows      = collect();
        $breakdown = collect();

        foreach ($stocks as $stock) {
            $price   = (float) $stock->hpp;
            $outlet  = $outletNames[$stock->owner_id] ?? '-';
            $batch   = $this->batchLabel($stock);

            $running = $this->walk(
                $assignedByStock->get($stock->id, collect()),
                $rows,
                $outlet,
                $batch,
                $price,
                $keteranganMap
            );

            $qtyFisik = (int) $stock->qty;
            $supplier = $stock->stock?->pembelian?->supplier?->name
                ?? ($stock->source_type === OutletPurchase::class ? ($purchaseSuppliers[$stock->source_id] ?? null) : null)
                ?? '-';

            $breakdown->push([
                'owner_stock_id' => $stock->id,
                'outlet'         => $outlet,
                'batch'          => $batch,
                'serial_number'  => $stock->stock?->serial_number ?: '-',
                'expired_at'     => optional($stock->expired_at)->toDateString() ?: '-',
                'supplier'       => $supplier,
                'hpp'            => $price,
                'qty'            => $qtyFisik,            // stok fisik (owner_stocks.qty)
                'saldo_kartu'    => (int) $running,       // hasil jumlah Masuk - Keluar di log
                'selisih'        => $qtyFisik - (int) $running,
            ]);
        }

        // Movement tanpa batch: satu baris ringkasan per outlet supaya saldo tidak tercampur.
        foreach ($unassigned->groupBy('owner_id') as $ownerId => $group) {
            $outlet = $outletNames[$ownerId] ?? '-';

            $running = $this->walk(
                $group,
                $rows,
                $outlet,
                '(tanpa batch)',
                (float) ($product->harga_beli ?? 0),
                $keteranganMap
            );

            $breakdown->push([
                'owner_stock_id' => null,
                'outlet'         => $outlet,
                'batch'          => '(movement tanpa batch)',
                'serial_number'  => '-',
                'expired_at'     => '-',
                'supplier'       => '-',
                'hpp'            => 0.0,
                'qty'            => 0,
                'saldo_kartu'    => (int) $running,
                'selisih'        => 0 - (int) $running,
            ]);
        }

        $totalQty   = (int) $stocks->sum('qty');
        $totalSaldo = (int) $breakdown->sum('saldo_kartu');
        // Nilai persediaan = SUM(stok fisik x HPP) semua batch.
        $totalNilai = (float) $stocks->sum(fn ($stock) => (int) $stock->qty * (float) $stock->hpp);

        $suppliersDisplay = $breakdown
            ->pluck('supplier')
            ->filter(fn ($name) => $name && $name !== '-')
            ->unique()
            ->values()
            ->implode(', ');

        $transactions = $rows
            ->sortBy('sort_key')
            ->values()
            ->map(function ($row) {
                unset($row['sort_key']);

                return $row;
            });

        return [
            'product' => [
                'id'           => $product->id,
                'code'         => $product->code,
                'name'         => $product->name,
                'lokasi'       => $product->lokasi,
                'suppliers'    => $suppliersDisplay ?: '-',
                'satuan'       => $product->satuan,
                'satuan_besar' => $product->satuan_besar,
                'konversi_qty' => $product->konversi_qty,
            ],
            'transactions'    => $transactions->values(),
            'product_summary' => [
                'total_qty'         => $totalQty,
                'total_saldo_kartu' => $totalSaldo,
                'total_selisih'     => $totalQty - $totalSaldo,
                'total_nilai'       => $totalNilai,
                'batch_count'       => $stocks->count(),
                'breakdown'         => $breakdown->values(),
            ],
        ];
    }

    /**
     * Jalankan running balance untuk satu batch, masukkan barisnya ke $rows,
     * lalu kembalikan saldo akhir batch tersebut.
     */
    protected function walk(Collection $movements, Collection $rows, string $outlet, string $batch, float $price, array $keteranganMap): int
    {
        $running = 0;

        foreach ($movements as $movement) {
            $stokAwal  = $running;
            $masuk     = (int) ($movement->qty_in ?? 0);
            $keluar    = (int) ($movement->qty_out ?? 0);
            $stokAkhir = $stokAwal + $masuk - $keluar;

            $rows->push([
                'sort_key'   => $movement->created_at->format('Y-m-d H:i:s') . '-' . str_pad((string) $movement->id, 10, '0', STR_PAD_LEFT),
                'tanggal'    => $movement->created_at->format('Y-m-d H:i'),
                'outlet'     => $outlet,
                'batch'      => $batch,
                'type'       => $movement->type,
                'is_return'  => $this->isReturn($movement),
                'stok_awal'  => $stokAwal,
                'masuk'      => $masuk,
                'keluar'     => $keluar,
                'stok_akhir' => $stokAkhir,
                'harga'      => $price,
                'nilai'      => $stokAkhir * $price,
                'keterangan' => $keteranganMap[$movement->id] ?? '-',
            ]);

            $running = $stokAkhir;
        }

        return $running;
    }

    protected function batchLabel(OwnerStock $stock): string
    {
        return $stock->batch_number ?: ($stock->sku ?: ($stock->stock?->serial_number ?: '-'));
    }

    protected function isReturn($movement): bool
    {
        return in_array($movement->reference_type, [RefundPembelian::class, RefundPenjualan::class], true)
            || str_contains(mb_strtolower((string) $movement->notes), 'retur');
    }

    /**
     * Query StockAdjustment SEKALI SAJA (whereIn), lalu dipetakan per movement_id di memory.
     */
    protected function buildKeteranganMap(Collection $movements): array
    {
        $map = [];

        if ($movements->isEmpty()) {
            return $map;
        }

        $adjustmentIds = $movements
            ->where('reference_type', StockAdjustment::class)
            ->pluck('reference_id')
            ->unique()
            ->values();

        $adjustments = $adjustmentIds->isNotEmpty()
            ? StockAdjustment::whereIn('id', $adjustmentIds)->get(['id', 'keterangan', 'reason'])->keyBy('id')
            : collect();

        foreach ($movements as $movement) {
            $parts = [];

            $this->appendKeteranganPart($parts, $movement->notes);

            if ($movement->reference_type === StockAdjustment::class) {
                $adjustment = $adjustments->get($movement->reference_id);

                if ($adjustment) {
                    $this->appendKeteranganPart($parts, $adjustment->keterangan);
                    $this->appendKeteranganPart($parts, $adjustment->reason);
                }
            }

            $map[$movement->id] = ! empty($parts) ? implode(' | ', $parts) : '-';
        }

        return $map;
    }

    protected function appendKeteranganPart(array &$parts, ?string $value): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        $normalizedValue = mb_strtolower($value);

        foreach ($parts as $part) {
            $normalizedPart = mb_strtolower($part);

            if (
                $normalizedPart === $normalizedValue
                || str_contains($normalizedPart, $normalizedValue)
                || str_contains($normalizedValue, $normalizedPart)
            ) {
                return;
            }
        }

        $parts[] = $value;
    }
}