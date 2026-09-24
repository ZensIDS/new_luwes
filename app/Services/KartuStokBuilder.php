<?php

namespace App\Services;

use App\Models\Pembelian;
use App\Models\Product;
use App\Models\RefundPembelian;
use App\Models\RefundPembelianItem;
use App\Models\Stock;
use App\Models\StockAdjustment;
use App\Models\StockMovement;

/**
 * Satu-satunya tempat kartu stok per produk dihitung. Dipakai oleh halaman Kartu Stok
 * (JSON), export Excel, dan export PDF supaya angkanya selalu sama.
 *
 * Stok fisik = stocks.qty (sumber kebenaran). Saldo kartu = total Masuk - Keluar di
 * stock_movements. Selisih keduanya ditampilkan apa adanya.
 */
class KartuStokBuilder
{
    public function build(Product $product): array
    {
        // Semua batch/SKU produk ini. Stok fisik = stocks.qty (sumber kebenaran yang sama
        // dengan menu Stok & Produk). Batch tanpa SKU tetap ikut dihitung.
        $stocks = Stock::with('pembelian.supplier')
            ->where('product_id', $product->id)
            ->orderBy('sku')
            ->orderBy('id')
            ->get();

        // Kartu ini adalah kartu stok gudang. Pergerakan owner stock outlet
        // dicatat pada tabel yang sama, tetapi tidak boleh mengubah saldo gudang.
        $movements = StockMovement::where('product_id', $product->id)
            ->whereNull('owner_id')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        [$movementsByStock, $unassigned] = $this->assignMovementsToStocks($movements, $stocks);

        $allTransactions = collect();
        $breakdown       = collect();

        // Running balance dihitung per batch/SKU secara independen
        foreach ($stocks as $stock) {
            $stockMovements = $movementsByStock[$stock->id] ?? collect();
            $keteranganMap  = $this->buildKeteranganMap($stockMovements, $stock);

            $runningStock = 0;
            $currentPrice = $stock->harga_beli;

            foreach ($stockMovements as $movement) {
                $stokAwal  = $runningStock;
                $masuk     = $movement->qty_in ?? 0;
                $keluar    = $movement->qty_out ?? 0;
                $stokAkhir = $stokAwal + $masuk - $keluar;

                $allTransactions->push([
                    'sort_key'   => $movement->created_at->format('Y-m-d H:i:s') . '-' . str_pad($movement->id, 10, '0', STR_PAD_LEFT),
                    'tanggal'    => $movement->created_at->format('Y-m-d'),
                    'sku'        => $stock->sku ?: '-',
                    'stok_awal'  => $stokAwal,
                    'masuk'      => $masuk,
                    'keluar'     => $keluar,
                    'stok_akhir' => $stokAkhir,
                    'harga'      => $currentPrice,
                    'nilai'      => $stokAkhir * $currentPrice,
                    'keterangan' => $keteranganMap[$movement->id] ?? '-',
                ]);

                $runningStock = $stokAkhir;
            }

            $qtyFisik = (int) $stock->qty;

            $breakdown->push([
                'stock_id'     => $stock->id,
                'sku'          => $stock->sku ?: '-',
                'supplier'     => $stock->pembelian?->supplier?->name ?? '-',
                'status'       => $stock->status,
                'qty'          => $qtyFisik,                    // stok fisik (stocks.qty)
                'qty_reserved' => (int) $stock->qty_reserved,   // sudah termasuk di qty
                'saldo_kartu'  => (int) $runningStock,          // hasil jumlah Masuk - Keluar di log
                'selisih'      => $qtyFisik - (int) $runningStock,
            ]);
        }

        // Movement lama yang tidak bisa dipetakan ke batch manapun: tetap ditampilkan
        // (tidak dibuang), dan selisihnya ikut terlihat di ringkasan.
        if ($unassigned->isNotEmpty()) {
            $keteranganMap = $this->buildKeteranganMap($unassigned, null);
            $runningStock  = 0;
            $currentPrice  = $product->harga_beli;

            foreach ($unassigned as $movement) {
                $stokAwal  = $runningStock;
                $masuk     = $movement->qty_in ?? 0;
                $keluar    = $movement->qty_out ?? 0;
                $stokAkhir = $stokAwal + $masuk - $keluar;

                $allTransactions->push([
                    'sort_key'   => $movement->created_at->format('Y-m-d H:i:s') . '-' . str_pad($movement->id, 10, '0', STR_PAD_LEFT),
                    'tanggal'    => $movement->created_at->format('Y-m-d'),
                    'sku'        => '(tanpa SKU)',
                    'stok_awal'  => $stokAwal,
                    'masuk'      => $masuk,
                    'keluar'     => $keluar,
                    'stok_akhir' => $stokAkhir,
                    'harga'      => $currentPrice,
                    'nilai'      => $stokAkhir * $currentPrice,
                    'keterangan' => $keteranganMap[$movement->id] ?? '-',
                ]);

                $runningStock = $stokAkhir;
            }

            $breakdown->push([
                'stock_id'     => null,
                'sku'          => '(movement tanpa SKU)',
                'supplier'     => '-',
                'status'       => null,
                'qty'          => 0,
                'qty_reserved' => 0,
                'saldo_kartu'  => (int) $runningStock,
                'selisih'      => 0 - (int) $runningStock,
            ]);
        }

        $totalQty      = (int) $stocks->sum('qty');
        $totalReserved = (int) $stocks->sum('qty_reserved');
        $totalSaldo    = (int) $breakdown->sum('saldo_kartu');
        // Nilai persediaan = SUM(stok fisik x harga beli) semua batch (bukan cuma baris terakhir kartu)
        $totalNilai    = (float) $stocks->sum(fn ($s) => (int) $s->qty * (float) $s->harga_beli);

        $suppliersDisplay = $breakdown
            ->pluck('supplier')
            ->filter(fn($s) => $s && $s !== '-')
            ->unique()
            ->values()
            ->implode(', ');

        $result = $allTransactions
            ->sortBy('sort_key')
            ->values()
            ->map(function ($t) {
                unset($t['sort_key']);
                return $t;
            });

        return [
            'product' => [
                'id'           => $product->id,
                'name'         => $product->name,
                'code'         => $product->code,
                'lokasi'       => $product->lokasi,
                'suppliers'    => $suppliersDisplay ?: '-',
                'konversi_qty' => $product->konversi_qty,
                'satuan_besar' => $product->satuan_besar,
                'satuan'       => $product->satuan,
            ],
            'transactions' => $result->values(),
            'product_summary' => [
                'total_qty'         => $totalQty,
                'total_reserved'    => $totalReserved,
                'total_saldo_kartu' => $totalSaldo,
                'total_selisih'     => $totalQty - $totalSaldo,
                'total_nilai'       => $totalNilai,
                'breakdown'         => $breakdown->values(),
            ],
        ];
    }


    /**
     * Petakan tiap StockMovement ke TEPAT SATU batch (Stock).
     *
     * Urutan pencocokan:
     *  1. "SKU: <sku>" di kolom notes (SKU terpanjang dicek dulu dan harus berakhir tepat
     *     di batas kata, jadi "SKU: AB-10" tidak salah dikira "AB-1").
     *  2. Movement lama tanpa SKU di notes tapi ber-referensi ke Pembelian yang hanya
     *     punya 1 batch untuk produk ini.
     *  3. Sisanya -> $unassigned.
     *
     * @return array{0: array<int, \Illuminate\Support\Collection>, 1: \Illuminate\Support\Collection}
     */
    protected function assignMovementsToStocks($movements, $stocks): array
    {
        $byStock    = [];
        $unassigned = collect();

        $skuIndex = $stocks
            ->filter(fn ($s) => filled($s->sku))
            ->sortByDesc(fn ($s) => mb_strlen($s->sku))
            ->values();

        $stocksByPembelian = $stocks->filter(fn ($s) => $s->pembelian_id)->groupBy('pembelian_id');

        foreach ($movements as $movement) {
            $matched = null;
            $notes   = (string) $movement->notes;

            if ($notes !== '') {
                foreach ($skuIndex as $candidate) {
                    $pattern = '/SKU:\s*' . preg_quote($candidate->sku, '/') . '(?=\s+-\s|,|\s*$)/u';
                    if (preg_match($pattern, $notes)) {
                        $matched = $candidate;
                        break;
                    }
                }
            }

            if (! $matched
                && $movement->reference_type === Pembelian::class
                && $movement->reference_id
            ) {
                $candidates = $stocksByPembelian->get($movement->reference_id, collect());
                if ($candidates->count() === 1) {
                    $matched = $candidates->first();
                }
            }

            if ($matched) {
                $byStock[$matched->id] = ($byStock[$matched->id] ?? collect())->push($movement);
            } else {
                $unassigned->push($movement);
            }
        }

        return [$byStock, $unassigned];
    }

    /**
     * Query StockAdjustment & RefundPembelianItem SEKALI SAJA (pakai whereIn),
     * lalu hasilnya dipetakan per movement_id di memory.
     * $stock boleh null (untuk movement yang tidak terpetakan ke batch manapun).
     */
    protected function buildKeteranganMap($movements, ?Stock $stock): array
    {
        $map = [];

        if ($movements->isEmpty()) {
            return $map;
        }

        // Kelompokkan reference_id per tipe, supaya bisa 1x query per tipe (bukan per baris)
        $adjustmentIds = $movements
            ->where('reference_type', StockAdjustment::class)
            ->pluck('reference_id')
            ->unique()
            ->values();

        $refundMovementIds = $movements
            ->where('reference_type', RefundPembelian::class)
            ->pluck('reference_id')
            ->unique()
            ->values();

        $productId = $movements->first()->product_id;

        // 1x query untuk semua StockAdjustment terkait
        $adjustments = $adjustmentIds->isNotEmpty()
            ? StockAdjustment::whereIn('id', $adjustmentIds)
                ->when($stock, fn ($q) => $q->where('stock_id', $stock->id))
                ->get()
                ->keyBy('id')
            : collect();

        // 1x query untuk semua RefundPembelianItem terkait
        $refundItems = $refundMovementIds->isNotEmpty()
            ? RefundPembelianItem::whereIn('refund_pembelian_id', $refundMovementIds)
                ->where('product_id', $productId)
                ->when($stock, function ($query) use ($stock) {
                    $query->where(function ($q) use ($stock) {
                        $q->where('stock_id', $stock->id)
                            ->orWhere('sku', $stock->sku);
                    });
                })
                ->orderByDesc('id')
                ->get()
                ->groupBy('refund_pembelian_id') // ambil yang 'latest' per refund_pembelian_id nanti
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

            if ($movement->reference_type === RefundPembelian::class) {
                $refundItem = optional($refundItems->get($movement->reference_id))->first();

                if ($refundItem && ! empty($refundItem->alasan)) {
                    $this->appendKeteranganPart($parts, 'Alasan retur: ' . $refundItem->alasan);
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
