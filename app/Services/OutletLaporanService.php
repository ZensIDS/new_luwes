<?php

namespace App\Services;

use App\Models\CashierDrawerEntry;
use App\Models\CashierSession;
use App\Models\Outlet;
use App\Models\OutletPrice;
use App\Models\OutletPurchase;
use App\Models\OwnerStock;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\RefundPenjualan;
use App\Models\Stock;
use App\Support\OutletAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OutletLaporanService
{
    public function filters(Request $request): array
    {
        [$start, $end] = $this->dateRange($request);

        return [
            'outlet_id' => $this->outletId($request),
            'kasir_id' => $request->filled('kasir_id') ? (int) $request->input('kasir_id') : null,
            'promotion_id' => $request->filled('promotion_id') ? (int) $request->input('promotion_id') : null,
            'mulai' => $start->toDateString(),
            'selesai' => $end->toDateString(),
        ];
    }

    public function sales(Request $request): array
    {
        [$start, $end] = $this->dateRange($request);
        $outletId = $this->outletId($request);
        $cashierId = $request->filled('kasir_id') ? (int) $request->input('kasir_id') : null;

        $sales = $this->saleQuery($start, $end, $outletId, $cashierId)
            ->with([
                'outlet', 'kasir', 'cashierShift', 'paymentMethod',
                'items.product', 'vouchers.products',
            ])
            ->orderBy('created_at')->orderBy('id')->get();

        $rows = collect();
        $transactions = collect();
        $returnedByItem = $this->returnedQuantities($end, $outletId);
        $totalVoucher = 0.0;

        foreach ($sales as $sale) {
            $voucherByItem = $this->voucherAllocation($sale);
            $saleTotal = 0.0;
            foreach ($sale->items as $item) {
                $returnedQty = (float) ($returnedByItem[$item->id] ?? 0);
                $qty = max(0, (float) $item->qty - $returnedQty);
                $unitPrice = (float) ($item->price ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $ratio = (float) $item->qty > 0 ? $qty / (float) $item->qty : 0;
                $voucher = (float) ($voucherByItem[$item->id] ?? 0) * $ratio;
                $rafaksi = ((float) ($item->promotion_discount ?? 0) * $ratio + $voucher) / $qty;
                $subtotal = $unitPrice * $qty;
                $saleTotal += $subtotal - $voucher;
                $totalVoucher += $voucher;

                $rows->push($this->saleRow($sale, $item->product, [
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'rafaksi' => $rafaksi,
                    'subtotal' => $subtotal,
                    'type' => 'Penjualan',
                ]));
            }

            $transactions->push([
                'date' => $sale->created_at,
                'invoice' => $sale->code,
                'outlet' => $sale->outlet?->name ?? '-',
                'cashier' => $this->cashierName($sale),
                'payment_method' => $this->paymentMethod($sale),
                'total' => max(0, $saleTotal),
            ]);
        }

        // A replacement item is a sale for reporting purposes. It is added at
        // the date of the return, while the returned item is removed above.
        $refunds = $this->refundQuery($start, $end, $outletId)
            ->with(['outlet', 'penjualan.kasir', 'penjualan.cashierShift', 'items.product'])
            ->orderBy('created_at')->orderBy('id')->get();

        foreach ($refunds as $refund) {
            $replacementTotal = 0.0;
            foreach ($refund->items->where('type', 'replacement') as $item) {
                $qty = (float) ($item->qty ?? 0);
                $unitPrice = (float) ($item->unit_price ?? 0);
                $subtotal = (float) ($item->subtotal ?? ($qty * $unitPrice));
                if ($qty <= 0) {
                    continue;
                }

                $replacementTotal += $subtotal;
                $rows->push($this->saleRow($refund->penjualan, $item->product, [
                    'date' => $refund->created_at,
                    'invoice' => $refund->penjualan?->code ?? $refund->code,
                    'outlet' => $refund->outlet?->name ?? '-',
                    'cashier' => $this->cashierName($refund->penjualan),
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'rafaksi' => 0,
                    'subtotal' => $subtotal,
                    'type' => 'Pengganti Retur',
                ]));
            }

            if ($replacementTotal > 0) {
                $transactions->push([
                    'date' => $refund->created_at,
                    'invoice' => $refund->penjualan?->code ?? $refund->code,
                    'outlet' => $refund->outlet?->name ?? '-',
                    'cashier' => $this->cashierName($refund->penjualan),
                    'payment_method' => $refund->payment_method_name ?: $this->paymentMethod($refund->penjualan),
                    'total' => $replacementTotal,
                ]);
            }
        }

        $paymentMethods = $transactions->groupBy('payment_method')->map(fn ($items) => (float) $items->sum('total'));
        $bon = $this->bonTotal($start, $end, $outletId, $cashierId);
        $setoranAkhir = $this->setoranTotal($start, $end, $outletId, $cashierId);
        $displayRows = $this->withRowSpans(
            $rows->sortBy([['outlet', 'asc'], ['invoice', 'asc'], ['tanggal', 'asc']])->values(),
            ['invoice', 'outlet', 'kasir', 'type'],
            ['invoice', 'outlet']
        );

        return [
            'rows' => $displayRows,
            'transactions' => $transactions->values(),
            'paymentMethods' => $paymentMethods,
            'summary' => [
                'total_penjualan' => (float) $transactions->sum('total'),
                'bon' => $bon,
                'total_voucher' => $totalVoucher,
                'setoran_akhir' => $setoranAkhir,
                'jumlah_transaksi' => $transactions->count(),
            ],
            ...$this->filters($request),
        ];
    }

    public function rafaksi(Request $request): array
    {
        [$start, $end] = $this->dateRange($request);
        $outletId = $this->outletId($request);
        $promotionId = $request->filled('promotion_id') ? (int) $request->input('promotion_id') : null;
        $selectedPromotion = $promotionId ? Promotion::find($promotionId) : null;
        if ($selectedPromotion) {
            $start = $selectedPromotion->start_at?->copy()->startOfDay() ?? $start;
            $end = $selectedPromotion->end_at?->copy()->endOfDay() ?? $end;
        }

        $sales = $this->saleQuery($start, $end, $outletId)
            ->whereHas('promotionApplications', function ($query) use ($promotionId) {
                $query->when($promotionId, fn ($q) => $q->where('promotion_id', $promotionId));
            })
            ->with('items.product')
            ->orderBy('created_at')->orderBy('id')->get();

        $rows = collect();
        $returnedByItem = $this->returnedQuantities($end, $outletId);
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $details = collect(is_array($item->promotion_details) ? $item->promotion_details : []);
                if ($promotionId) {
                    $details = $details->filter(fn ($detail) => (int) ($detail['promotion_id'] ?? 0) === $promotionId);
                }
                if ($details->isEmpty()) {
                    continue;
                }

                $returnedQty = (float) ($returnedByItem[$item->id] ?? 0);
                $qty = max(0, (float) $item->qty - $returnedQty);
                if ($qty <= 0) {
                    continue;
                }

                $promotionQty = (float) $details->sum(fn ($detail) => (float) ($detail['quantity'] ?? 0));
                $discount = (float) $details->sum(fn ($detail) => (float) ($detail['amount'] ?? 0));
                // A return reduces the rafaksi quantity and its discount in the
                // same proportion as the original item quantity.
                $ratio = (float) $item->qty > 0 ? $qty / (float) $item->qty : 0;
                $rafaksiQty = min($qty, $promotionQty * $ratio);
                $discountTotal = $discount * $ratio;
                if ($rafaksiQty <= 0 || $discountTotal <= 0) {
                    continue;
                }

                $promotionNames = $details->map(fn ($detail) => $detail['promotion_name'] ?? $detail['promotion_code'] ?? 'Rafaksi')
                    ->filter()->unique()->implode(', ');
                $unitPrice = (float) ($item->price ?? 0);
                $rows->push([
                    'product_id' => $item->product_id,
                    'barcode' => $item->product?->code ?? '-',
                    'product' => $item->product?->name ?? '-',
                    'qty' => $rafaksiQty,
                    'unit_price' => $unitPrice,
                    'discount_per_unit' => $rafaksiQty > 0 ? $discountTotal / $rafaksiQty : 0,
                    'discount_total' => $discountTotal,
                    'subtotal' => $unitPrice * $rafaksiQty,
                    'keterangan' => $promotionNames ?: 'Rafaksi',
                ]);
            }
        }
        $rows = $rows->groupBy('product_id')->map(function ($productRows) {
            $first = $productRows->first();
            $qty = (float) $productRows->sum('qty');
            $discount = (float) $productRows->sum('discount_total');
            $subtotal = (float) $productRows->sum('subtotal');

            return [
                'barcode' => $first['barcode'],
                'product' => $first['product'],
                'qty' => $qty,
                'unit_price' => $qty > 0 ? $subtotal / $qty : 0,
                'discount_per_unit' => $qty > 0 ? $discount / $qty : 0,
                'discount_total' => $discount,
                'subtotal' => $subtotal,
                'keterangan' => $productRows->pluck('keterangan')->unique()->implode(', '),
            ];
        })->sortBy('product')->values();

        $promotions = Promotion::query()
            ->when($promotionId, fn ($query) => $query->whereKey($promotionId))
            ->get();

        return [
            'rows' => $rows->values(),
            'promotions' => $promotions,
            'summary' => [
                'total_penjualan' => (float) $rows->sum('subtotal'),
                'total_diskon' => (float) $rows->sum('discount_total'),
                'qty' => (float) $rows->sum('qty'),
            ],
            ...$this->filters($request),
            'mulai' => $start->toDateString(),
            'selesai' => $end->toDateString(),
        ];
    }

    public function returns(Request $request): array
    {
        [$start, $end] = $this->dateRange($request);
        $outletId = $this->outletId($request);
        $refunds = $this->refundQuery($start, $end, $outletId)
            ->with(['outlet', 'user', 'penjualan', 'items.product'])
            ->orderBy('created_at')->orderBy('id')->get();

        $rows = collect();
        foreach ($refunds as $refund) {
            $returns = $refund->items->where('type', 'return')->values();
            $replacements = $refund->items->where('type', 'replacement')->values();
            $count = max($returns->count(), $replacements->count(), 1);

            for ($index = 0; $index < $count; $index++) {
                $returned = $returns->get($index);
                $replacement = $replacements->get($index);
                $rows->push([
                    'tanggal' => $refund->created_at,
                    'kode_retur' => $refund->code,
                    'invoice' => $refund->penjualan?->code ?? '-',
                    'outlet' => $refund->outlet?->name ?? '-',
                    'petugas' => $refund->user?->name ?? '-',
                    'barang_retur' => $returned?->product?->name ?? '-',
                    'barcode_retur' => $returned?->product?->code ?? '-',
                    'qty_retur' => (float) ($returned?->qty ?? 0),
                    'barang_pengganti' => $replacement?->product?->name ?? '-',
                    'barcode_pengganti' => $replacement?->product?->code ?? '-',
                    'qty_pengganti' => (float) ($replacement?->qty ?? 0),
                    'selisih' => $index === 0 ? (float) ($refund->difference ?? 0) : 0,
                    'keterangan' => $refund->notes ?? '',
                ]);
            }
        }

        return [
            'rows' => $rows->values(),
            'summary' => [
                'jumlah_retur' => $refunds->count(),
                'total_barang_retur' => (float) $refunds->flatMap(fn ($refund) => $refund->items)->where('type', 'return')->sum('qty'),
                'total_barang_pengganti' => (float) $refunds->flatMap(fn ($refund) => $refund->items)->where('type', 'replacement')->sum('qty'),
                'total_selisih' => (float) $refunds->sum('difference'),
            ],
            ...$this->filters($request),
        ];
    }

    public function allStock(Request $request): array
    {
        $outletId = $this->outletId($request);
        $stocks = OwnerStock::query()
            ->with(['owner', 'product.category', 'stock'])
            ->when($outletId, fn ($query) => $query->where('owner_id', $outletId))
            ->orderBy('owner_id')->orderBy('product_id')->orderBy('id')->get();

        $prices = OutletPrice::query()
            ->currentlyActive()
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))
            ->get()->keyBy(fn ($price) => $price->outlet_id.'-'.$price->product_id);

        // A barcode is the product code. Merge all owner-stock batches for
        // the same outlet/product so the report never falls back to SKU rows.
        $rows = $stocks->groupBy(fn ($stock) => $stock->owner_id.'-'.$stock->product_id)->map(function ($stockRows) use ($prices) {
            $stock = $stockRows->first();
            $qty = (float) $stockRows->sum('qty');
            $stockValue = (float) $stockRows->sum(function ($row) {
                $hpp = (float) ($row->hpp ?? $row->stock?->harga_beli ?? $row->product?->harga_beli ?? 0);

                return $hpp * (float) ($row->qty ?? 0);
            });
            $hpp = $qty > 0
                ? $stockValue / $qty
                : (float) ($stock->hpp ?? $stock->stock?->harga_beli ?? $stock->product?->harga_beli ?? 0);
            $price = $prices->get($stock->owner_id.'-'.$stock->product_id);
            $tax = $this->taxAmount($hpp, $price?->pajak_type, $price?->pajak_value);
            $hppAfterTax = $hpp + $tax;

            return [
                'outlet_id' => $stock->owner_id,
                'outlet' => $stock->owner?->name ?? '-',
                'barcode' => $stock->product?->code ?? '-',
                'product' => $stock->product?->name ?? '-',
                'category' => $stock->product?->category?->name ?? '-',
                'qty' => $qty,
                'satuan' => $stock->product?->satuan ?? 'PCS',
                'hpp' => $hpp,
                'tax' => $tax,
                'hpp_after_tax' => $hppAfterTax,
                'inventory_before_tax' => $hpp * $qty,
                'inventory_after_tax' => $hppAfterTax * $qty,
            ];
        })->values();
        $rows = $this->withRowSpans($rows, ['outlet'], ['outlet_id']);

        return [
            'rows' => $rows,
            'summary' => [
                'total_persediaan_sebelum_pajak' => (float) $rows->sum('inventory_before_tax'),
                'total_persediaan_setelah_pajak' => (float) $rows->sum('inventory_after_tax'),
                'total_qty' => (float) $rows->sum('qty'),
            ],
            ...$this->filters($request),
        ];
    }

    public function minimumStock(Request $request): array
    {
        $asOf = $request->filled('tanggal_selesai')
            ? Carbon::parse($request->input('tanggal_selesai'))->endOfDay()
            : now()->endOfDay();
        // The rolling window is the three completed months before the current
        // month: month 4 uses months 1-3, month 5 uses months 2-4, etc.
        $windowEnd = $asOf->copy()->startOfMonth()->subSecond();
        $windowStart = $windowEnd->copy()->startOfMonth()->subMonths(2);
        $outletId = $this->outletId($request);

        $outletStocks = OwnerStock::query()
            ->select('owner_id', 'product_id')
            ->when($outletId, fn ($query) => $query->where('owner_id', $outletId))
            ->groupBy('owner_id', 'product_id')->get();
        $warehouseStocks = Stock::query()
            ->select('product_id', DB::raw('SUM(qty) as qty'))
            ->groupBy('product_id')->pluck('qty', 'product_id');

        $historySales = $this->saleQuery(null, $windowEnd, $outletId)
            ->with('items')->get();
        $windowSales = $this->saleQuery($windowStart, $windowEnd, $outletId)
            ->with('items')
            ->get();
        $returnedByItem = $this->returnedQuantities($windowEnd, $outletId);
        $salesQty = collect();
        $firstSales = collect();

        foreach ($historySales as $sale) {
            foreach ($sale->items as $item) {
                $key = $sale->outlet_id.'-'.$item->product_id;
                $firstSales[$key] = isset($firstSales[$key])
                    ? min($firstSales[$key], $sale->created_at)
                    : $sale->created_at;
            }
        }

        foreach ($windowSales as $sale) {
            foreach ($sale->items as $item) {
                $key = $sale->outlet_id.'-'.$item->product_id;
                $returned = $returnedByItem[$item->id] ?? 0;
                $salesQty[$key] = ($salesQty[$key] ?? 0) + max(0, (float) $item->qty - (float) $returned);
            }
        }

        $replacementQuery = $this->refundQuery($windowStart, $windowEnd, $outletId)
            ->with('items');
        foreach ($replacementQuery->get() as $refund) {
            foreach ($refund->items->where('type', 'replacement') as $item) {
                $key = $refund->outlet_id.'-'.$item->product_id;
                $salesQty[$key] = ($salesQty[$key] ?? 0) + (float) ($item->qty ?? 0);
            }
        }

        $outletIds = $outletStocks->pluck('owner_id')->merge($salesQty->keys()
            ->map(fn ($key) => (int) explode('-', $key)[0]))->filter()->unique()->values();
        if ($outletId) {
            $outletIds = collect([$outletId]);
        }

        $productIds = $outletStocks->pluck('product_id')->merge($salesQty->keys()
            ->map(fn ($key) => (int) explode('-', $key)[1]))->filter()->unique()->values();
        $products = Product::with('category')->whereIn('id', $productIds)->get()->keyBy('id');
        $outlets = Outlet::whereIn('id', $outletIds)->get()->keyBy('id');
        $poCounts = OutletPurchase::query()
            ->select('outlet_id', DB::raw('COUNT(*) as total'))
            ->whereDate('purchase_date', '>=', $windowStart->toDateString())
            ->whereDate('purchase_date', '<=', $windowEnd->toDateString())
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))
            ->groupBy('outlet_id')->pluck('total', 'outlet_id');
        $stockMap = $outletStocks->keyBy(fn ($stock) => $stock->owner_id.'-'.$stock->product_id);

        $rows = collect();
        foreach ($outletIds as $currentOutletId) {
            $currentProductIds = $productIds->filter(function ($productId) use ($currentOutletId, $stockMap, $salesQty) {
                return $stockMap->has($currentOutletId.'-'.$productId) || $salesQty->has($currentOutletId.'-'.$productId);
            });
            foreach ($currentProductIds as $productId) {
                $product = $products->get($productId);
                if (! $product) {
                    continue;
                }
                $key = $currentOutletId.'-'.$productId;
                $firstSale = $firstSales[$key] ?? null;
                $eligible = $firstSale && Carbon::parse($firstSale)->startOfMonth()->lte($windowStart->copy()->startOfMonth());
                $averageSold = (float) ($salesQty[$key] ?? 0) / 3;
                $poCount = (int) ($poCounts[$currentOutletId] ?? 0);
                $poPerMonth = $poCount / 3;
                $frequency = max(1, min(4, (int) ceil($poPerMonth)));
                $factor = [1 => 2.5, 2 => 1.875, 3 => 1.25, 4 => 0.625][$frequency];
                $manualMin = (int) ($product->min_stock ?? 0);
                $minStock = $eligible ? (int) ceil($averageSold * $factor) : $manualMin;
                $currentQty = (float) ($warehouseStocks[$productId] ?? 0);
                $outOfStock = $currentQty < $minStock;

                $rows->push([
                    'outlet_id' => $currentOutletId,
                    'outlet' => $outlets->get($currentOutletId)?->name ?? '-',
                    'barcode' => $product->code ?? '-',
                    'product' => $product->name ?? '-',
                    'category' => $product->category?->name ?? '-',
                    'window_start' => $windowStart->toDateString(),
                    'window_end' => $windowEnd->toDateString(),
                    'first_sale' => $firstSale,
                    'average_sold' => $averageSold,
                    'po_count' => $poCount,
                    'po_per_month' => $poPerMonth,
                    'factor' => $eligible ? $factor : null,
                    'manual_min_stock' => $manualMin,
                    'min_stock' => $minStock,
                    'stock_qty' => $currentQty,
                    'suggested_po_qty' => $outOfStock ? max(0, $minStock - $currentQty) : 0,
                    'status' => $outOfStock ? 'out_of_stock' : 'normal',
                    'calculation_status' => $eligible ? 'auto (3 bulan)' : 'manual (belum 3 bulan)',
                ]);
            }
        }
        $rows = $this->withRowSpans($rows, ['outlet'], ['outlet_id']);

        return [
            'rows' => $rows->values(),
            'summary' => [
                'total_produk' => $rows->count(),
                'out_of_stock' => $rows->where('status', 'out_of_stock')->count(),
                'total_suggested_po' => (float) $rows->sum('suggested_po_qty'),
            ],
            'asOf' => $asOf->toDateString(),
            'windowStart' => $windowStart->toDateString(),
            'windowEnd' => $windowEnd->toDateString(),
            ...$this->filters($request),
        ];
    }

    protected function dateRange(Request $request): array
    {
        $start = $request->input('tanggal_mulai', now()->startOfMonth()->toDateString());
        $end = $request->input('tanggal_selesai', now()->toDateString());

        return [
            Carbon::parse($start)->startOfDay(),
            Carbon::parse($end)->endOfDay(),
        ];
    }

    /** Mark the first row of each consecutive equal value with its table rowspan. */
    protected function withRowSpans(Collection $rows, array $columns, array $scope): Collection
    {
        $displayRows = $rows->values()->all();
        $count = count($displayRows);
        foreach ($columns as $column) {
            for ($index = 0; $index < $count;) {
                $span = 1;
                while ($index + $span < $count
                    && $displayRows[$index + $span][$column] === $displayRows[$index][$column]
                    && collect($scope)->every(fn ($field) => $displayRows[$index + $span][$field] === $displayRows[$index][$field])) {
                    $span++;
                }
                $displayRows[$index][$column.'_span'] = $span;
                for ($next = 1; $next < $span; $next++) {
                    $displayRows[$index + $next][$column.'_span'] = 0;
                }
                $index += $span;
            }
        }

        return collect($displayRows);
    }

    protected function outletId(Request $request): ?int
    {
        return OutletAccess::id($request, false);
    }

    protected function saleQuery(?Carbon $start, ?Carbon $end, ?int $outletId = null, ?int $cashierId = null)
    {
        return Penjualan::query()
            ->where(function ($query) {
                $query->where('status', 'paid')->orWhereNull('status');
            })
            ->when($start, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end, fn ($query) => $query->where('created_at', '<=', $end))
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))
            ->when($cashierId, fn ($query) => $query->where('kasir_id', $cashierId));
    }

    protected function refundQuery(Carbon $start, Carbon $end, ?int $outletId = null)
    {
        return RefundPenjualan::query()
            ->whereBetween('created_at', [$start, $end])
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId));
    }

    protected function saleRow(?Penjualan $sale, ?Product $product, array $values): array
    {
        return [
            'tanggal' => $values['date'] ?? $sale?->created_at,
            'invoice' => $values['invoice'] ?? $sale?->code ?? '-',
            'outlet' => $values['outlet'] ?? $sale?->outlet?->name ?? '-',
            'kasir' => $values['cashier'] ?? $this->cashierName($sale),
            'barcode' => $product?->code ?? '-',
            'product' => $product?->name ?? '-',
            'qty' => (float) ($values['qty'] ?? 0),
            'unit_price' => (float) ($values['unit_price'] ?? 0),
            'rafaksi' => (float) ($values['rafaksi'] ?? 0),
            'subtotal' => (float) ($values['subtotal'] ?? 0),
            'payment_method' => $values['payment_method'] ?? ($sale ? $this->paymentMethod($sale) : '-'),
            'type' => $values['type'] ?? 'Penjualan',
        ];
    }

    /** Allocate returns without a sale item to the newest eligible invoice at the same outlet. */
    protected function returnedQuantities(Carbon $end, ?int $outletId): \Illuminate\Support\Collection
    {
        $refunds = RefundPenjualan::query()
            ->where('created_at', '<=', $end)
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))
            ->with('items')
            ->orderBy('created_at')->orderBy('id')->get();
        $items = $refunds->flatMap(fn ($refund) => $refund->items->where('type', 'return'));
        $allocated = $items->filter(fn ($item) => $item->penjualan_item_id)
            ->groupBy('penjualan_item_id')
            ->map(fn ($group) => (float) $group->sum('qty'));
        $unlinked = $items->reject(fn ($item) => $item->penjualan_item_id);
        if ($unlinked->isEmpty()) {
            return $allocated;
        }

        $candidates = PenjualanItem::query()
            ->whereIn('product_id', $unlinked->pluck('product_id')->unique())
            ->whereHas('penjualan', function ($query) use ($end, $outletId) {
                $query->where('created_at', '<=', $end)
                    ->where(fn ($q) => $q->where('status', 'paid')->orWhereNull('status'))
                    ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));
            })
            ->with('penjualan')
            ->get()
            ->sort(fn ($a, $b) => $b->penjualan->created_at <=> $a->penjualan->created_at ?: $b->id <=> $a->id)
            ->groupBy(fn ($item) => $item->penjualan->outlet_id.'-'.$item->product_id);

        foreach ($refunds as $refund) {
            foreach ($refund->items->where('type', 'return')->reject(fn ($item) => $item->penjualan_item_id) as $item) {
                $remaining = (float) $item->qty;
                foreach ($candidates->get($refund->outlet_id.'-'.$item->product_id, collect()) as $candidate) {
                    if ($remaining <= 0) {
                        break;
                    }
                    if ($candidate->penjualan->created_at->gt($refund->created_at)
                        || ($refund->penjualan_id && (int) $candidate->penjualan_id !== (int) $refund->penjualan_id)) {
                        continue;
                    }
                    $available = max(0, (float) $candidate->qty - (float) ($allocated[$candidate->id] ?? 0));
                    $take = min($remaining, $available);
                    $allocated[$candidate->id] = (float) ($allocated[$candidate->id] ?? 0) + $take;
                    $remaining -= $take;
                }
            }
        }

        return $allocated;
    }

    /** Apportion voucher redemptions to the products they apply to. */
    protected function voucherAllocation(Penjualan $sale): array
    {
        $balances = $sale->items->mapWithKeys(fn ($item) => [
            $item->id => (float) $item->price * (float) $item->qty,
        ])->all();
        $allocated = array_fill_keys(array_keys($balances), 0.0);
        foreach ($sale->vouchers as $voucher) {
            $eligible = $sale->items->filter(fn ($item) => $voucher->appliesToProduct((int) $item->product_id))
                ->pluck('id')->all();
            $base = array_sum(array_intersect_key($balances, array_flip($eligible)));
            $remaining = min((float) $voucher->pivot->amount, $base);
            foreach ($eligible as $index => $itemId) {
                $amount = $index === count($eligible) - 1 ? $remaining
                    : min($remaining, $base > 0 ? (float) $voucher->pivot->amount * $balances[$itemId] / $base : 0);
                $allocated[$itemId] += $amount;
                $balances[$itemId] -= $amount;
                $remaining -= $amount;
            }
        }
        $missing = max(0, (float) ($sale->voucher_total ?? 0) - array_sum($allocated));
        $base = array_sum($balances);
        foreach (array_keys($balances) as $itemId) {
            $amount = $base > 0 ? $missing * $balances[$itemId] / $base : 0;
            $allocated[$itemId] += $amount;
        }

        return $allocated;
    }

    protected function paymentMethod(?Penjualan $sale): string
    {
        if (! $sale) {
            return 'Tunai';
        }

        return $sale->paymentMethod?->name ?? $sale->payment_method_name ?? 'Tunai';
    }

    protected function cashierName(?Penjualan $sale): string
    {
        $accountName = $sale?->kasir?->name;
        $shiftName = $sale?->cashierShift?->name;

        if ($accountName && $shiftName) {
            return "{$accountName} ({$shiftName})";
        }

        return $accountName ?: ($shiftName ?: '-');
    }

    protected function bonTotal(Carbon $start, Carbon $end, ?int $outletId, ?int $cashierId): float
    {
        return (float) CashierDrawerEntry::query()
            ->whereIn('type', ['bon', 'cash_out'])
            ->whereBetween(DB::raw('COALESCE(recorded_at, created_at)'), [$start, $end])
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))
            ->when($cashierId, fn ($query) => $query->where('cashier_id', $cashierId))
            ->sum('amount');
    }

    protected function setoranTotal(Carbon $start, Carbon $end, ?int $outletId, ?int $cashierId): float
    {
        return (float) CashierSession::query()
            ->whereNotNull('cash_removed')
            ->whereBetween(DB::raw('COALESCE(closed_at, updated_at)'), [$start, $end])
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))
            ->when($cashierId, function ($query) use ($cashierId) {
                $query->where(function ($scope) use ($cashierId) {
                    $scope->where('cashier_id', $cashierId)
                        ->orWhere('opening_cashier_id', $cashierId)
                        ->orWhere('closing_cashier_id', $cashierId);
                });
            })
            ->sum('cash_removed');
    }

    protected function taxAmount(float $hpp, ?string $type, $value): float
    {
        $value = max(0, (float) ($value ?? 0));
        if (strtolower((string) $type) === 'percentage') {
            return round($hpp * min(100, $value) / 100, 2);
        }

        return round($value, 2);
    }
}
