<?php

namespace App\Http\Controllers;

use App\Models\CashierDrawerEntry;
use App\Models\CashierSession;
use App\Models\OwnerStock;
use App\Models\OutletPrice;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Models\RefundPenjualan;
use App\Models\RefundPenjualanItem;
use App\Services\OutletStockService;
use App\Services\PriceCalculator;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class RefundPenjualanController extends Controller
{
    public function index(Request $request)
    {
        $outletId = OutletAccess::id($request, false);
        $query = RefundPenjualan::with(['penjualan', 'outlet', 'user', 'items.product'])
            ->latest();

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        return view('refundPenjualans.index', [
            'refundPenjualans' => $query->get(),
        ]);
    }

    public function create(Request $request)
    {
        $outletId = OutletAccess::id($request, false);
        $lastReturn = RefundPenjualan::latest('id')->first();
        $nextNumber = $lastReturn
            ? ((int) preg_replace('/\D+/', '', (string) $lastReturn->code) + 1)
            : 1;

        return view('refundPenjualans.create', [
            'outlets' => OutletAccess::outlets(),
            'outletId' => $outletId,
            'code' => 'RJP'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT),
        ]);
    }

    public function invoices(Request $request)
    {
        $outletId = OutletAccess::id($request);
        $search = trim((string) $request->input('search', ''));

        $sales = Penjualan::with(['items.product', 'items.ownerStock'])
            ->where('outlet_id', $outletId)
            ->where(function ($query) {
                $query->where('status', 'paid')->orWhereNull('status');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where('code', 'like', "%{$search}%");
            })
            ->latest('created_at')
            ->latest('id')
            ->limit(50)
            ->get();

        $itemIds = $sales->flatMap(fn (Penjualan $sale) => $sale->items->pluck('id'));
        $returned = RefundPenjualanItem::query()
            ->where('type', 'return')
            ->whereIn('penjualan_item_id', $itemIds)
            ->select('penjualan_item_id', DB::raw('SUM(qty) as qty'))
            ->groupBy('penjualan_item_id')
            ->pluck('qty', 'penjualan_item_id');

        return response()->json($sales->map(function (Penjualan $sale) use ($returned) {
            return [
                'id' => $sale->id,
                'code' => $sale->code,
                'date' => optional($sale->created_at)->format('d/m/Y H:i'),
                'total' => (float) ($sale->grand_total ?? $sale->total ?? 0),
                'items' => $sale->items->map(function (PenjualanItem $item) use ($returned) {
                    $alreadyReturned = (int) ($returned[$item->id] ?? 0);
                    $availableQty = max(0, (int) $item->qty - $alreadyReturned);

                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'owner_stock_id' => $item->owner_stock_id,
                        'name' => $item->product?->name ?? 'Produk dihapus',
                        'code' => $item->product?->code,
                        'qty' => (int) $item->qty,
                        'available_qty' => $availableQty,
                        'price' => (float) $item->price,
                        'subtotal' => (float) ($item->subtotal ?? ((int) $item->qty * (float) $item->price)),
                    ];
                })->values(),
            ];
        })->values());
    }

    public function stocks(Request $request, PriceCalculator $calculator)
    {
        $outletId = OutletAccess::id($request);
        $returnedQtyByStock = collect((array) $request->input('returned_stock_qtys', []))
            ->mapWithKeys(fn ($qty, $stockId) => [(int) $stockId => max(0, (int) $qty)])
            ->filter(fn ($qty, $stockId) => $stockId > 0 && $qty > 0);
        $includedOwnerStockIds = $returnedQtyByStock->keys()->map(fn ($id) => (int) $id)->values();
        $search = trim((string) $request->input('search', ''));
        $rules = OutletPrice::query()
            ->where('outlet_id', $outletId)
            ->currentlyActive()
            ->get()
            ->keyBy('product_id');

        $stocks = OwnerStock::with('product')
            ->where('owner_id', $outletId)
            ->where(function ($query) use ($includedOwnerStockIds) {
                $query->where('qty', '>', 0);
                if ($includedOwnerStockIds->isNotEmpty()) {
                    $query->orWhereIn('id', $includedOwnerStockIds->all());
                }
            })
            ->where(function ($query) {
                $query->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
            })
            ->when($search !== '', function ($query) use ($search, $includedOwnerStockIds) {
                $query->where(function ($searchQuery) use ($search, $includedOwnerStockIds) {
                    $searchQuery->whereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })->orWhere('sku', 'like', "%{$search}%");
                    if ($includedOwnerStockIds->isNotEmpty()) {
                        $searchQuery->orWhereIn('id', $includedOwnerStockIds->all());
                    }
                });
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(function (OwnerStock $stock) use ($calculator, $rules, $returnedQtyByStock) {
                $price = $calculator->calculateItem(
                    (float) ($stock->hpp ?? $stock->product?->harga_beli ?? 0),
                    $rules->get($stock->product_id),
                    $stock->product
                );

                return [
                    'id' => $stock->id,
                    'product_id' => $stock->product_id,
                    'name' => $stock->product?->name ?? 'Produk dihapus',
                    'code' => $stock->product?->code,
                    'sku' => $stock->sku,
                    'qty' => (int) $stock->qty,
                    'available_qty' => (int) $stock->qty + (int) $returnedQtyByStock->get((int) $stock->id, 0),
                    'price' => (float) $price['price'],
                ];
            })
            ->values();

        return response()->json($stocks);
    }

    public function store(
        Request $request,
        PriceCalculator $calculator,
        OutletStockService $stockService
    ) {
        $outletId = OutletAccess::id($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:refund_penjualans,code'],
            'penjualan_id' => ['required', 'integer', 'exists:penjualans,id'],
            'returns' => ['required', 'array', 'min:1'],
            'returns.*.item_id' => ['required', 'integer', 'distinct', 'exists:penjualan_items,id'],
            'returns.*.qty' => ['required', 'integer', 'min:1'],
            'replacements' => ['required', 'array', 'min:1'],
            'replacements.*.owner_stock_id' => ['required', 'integer', 'distinct', 'exists:owner_stocks,id'],
            'replacements.*.qty' => ['required', 'integer', 'min:1'],
            'difference_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method_name' => ['nullable', 'string', 'max:100'],
            'payment_reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'code.unique' => 'Kode retur penjualan sudah digunakan.',
            'penjualan_id.exists' => 'Invoice tidak ditemukan.',
            'returns.required' => 'Pilih minimal satu barang yang diretur.',
            'returns.min' => 'Pilih minimal satu barang yang diretur.',
            'replacements.required' => 'Pilih minimal satu barang pengganti.',
            'replacements.min' => 'Pilih minimal satu barang pengganti.',
        ]);

        try {
            $refund = DB::transaction(function () use ($request, $data, $outletId, $calculator, $stockService) {
                $sale = Penjualan::query()
                    ->whereKey($data['penjualan_id'])
                    ->where('outlet_id', $outletId)
                    ->where(function ($query) {
                        $query->where('status', 'paid')->orWhereNull('status');
                    })
                    ->lockForUpdate()
                    ->first();
                if (! $sale) {
                    throw ValidationException::withMessages(['penjualan_id' => 'Invoice tidak tersedia untuk outlet ini.']);
                }

                $returnRows = collect($data['returns'])->values();
                $saleItems = PenjualanItem::query()
                    ->whereIn('id', $returnRows->pluck('item_id')->all())
                    ->where('penjualan_id', $sale->id)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                if ($saleItems->count() !== $returnRows->count()) {
                    throw ValidationException::withMessages(['returns' => 'Ada item retur yang tidak sesuai dengan invoice.']);
                }

                $returnItems = [];
                $returnStockQtys = [];
                $returnedTotal = 0;
                foreach ($returnRows as $row) {
                    $saleItem = $saleItems->get($row['item_id']);
                    $alreadyReturned = (int) RefundPenjualanItem::query()
                        ->where('type', 'return')
                        ->where('penjualan_item_id', $saleItem->id)
                        ->sum('qty');
                    if ((int) $row['qty'] > ((int) $saleItem->qty - $alreadyReturned)) {
                        throw ValidationException::withMessages([
                            'returns' => "Jumlah retur {$saleItem->product?->name} melebihi sisa jumlah item pada invoice.",
                        ]);
                    }

                    if (! $saleItem->owner_stock_id) {
                        throw ValidationException::withMessages([
                            'returns' => "Item {$saleItem->product?->name} tidak memiliki batch stock toko untuk dikembalikan.",
                        ]);
                    }

                    $returnItems[] = [
                        'sale_item' => $saleItem,
                        'qty' => (int) $row['qty'],
                        'unit_price' => (float) $saleItem->price,
                    ];
                    $returnStockQtys[(int) $saleItem->owner_stock_id] = ($returnStockQtys[(int) $saleItem->owner_stock_id] ?? 0) + (int) $row['qty'];
                    $returnedTotal += (float) $saleItem->price * (int) $row['qty'];
                }

                $returnedStocks = OwnerStock::query()
                    ->where('owner_id', $outletId)
                    ->whereIn('id', array_keys($returnStockQtys))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                if ($returnedStocks->count() !== count($returnStockQtys)) {
                    throw ValidationException::withMessages([
                        'returns' => 'Ada batch stock toko item retur yang tidak ditemukan.',
                    ]);
                }

                $replacementRows = collect($data['replacements'])->values();
                $replacementIds = $replacementRows->pluck('owner_stock_id')->map(fn ($id) => (int) $id);
                $replacementStock = OwnerStock::query()
                    ->with('product')
                    ->where('owner_id', $outletId)
                    ->where(function ($query) use ($returnStockQtys) {
                        $query->where('qty', '>', 0)->orWhereIn('id', array_keys($returnStockQtys));
                    })
                    ->whereIn('id', $replacementIds->all())
                    ->where(function ($query) {
                        $query->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
                    })
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                if ($replacementStock->count() !== $replacementIds->unique()->count()) {
                    throw ValidationException::withMessages([
                        'replacements' => 'Ada stock toko pengganti yang tidak ditemukan atau sudah habis.',
                    ]);
                }

                $replacementProductIds = $replacementStock->pluck('product_id')->unique();
                $rules = OutletPrice::query()
                    ->where('outlet_id', $outletId)
                    ->whereIn('product_id', $replacementProductIds->all())
                    ->currentlyActive()
                    ->get()
                    ->keyBy('product_id');

                $replacementItems = [];
                $replacementTotal = 0;
                foreach ($replacementRows as $row) {
                    $stock = $replacementStock->get($row['owner_stock_id']);
                    $availableQty = (int) $stock->qty + (int) ($returnStockQtys[(int) $stock->id] ?? 0);
                    if ($availableQty < (int) $row['qty']) {
                        throw ValidationException::withMessages([
                            'replacements' => "Jumlah stock {$stock->product?->name} tidak mencukupi.",
                        ]);
                    }
                    $price = $calculator->calculateItem(
                        (float) ($stock->hpp ?? $stock->product?->harga_beli ?? 0),
                        $rules->get($stock->product_id),
                        $stock->product
                    )['price'];
                    $subtotal = (float) $price * (int) $row['qty'];
                    $replacementItems[] = [
                        'stock' => $stock,
                        'qty' => (int) $row['qty'],
                        'unit_price' => (float) $price,
                        'subtotal' => $subtotal,
                    ];
                    $replacementTotal += $subtotal;
                }

                $returnedTotal = $calculator->money($returnedTotal);
                $replacementTotal = $calculator->money($replacementTotal);
                if ($replacementTotal < $returnedTotal) {
                    throw ValidationException::withMessages([
                        'replacements' => 'Total barang pengganti harus bernilai sama atau lebih mahal dari total barang yang diretur.',
                    ]);
                }

                $difference = $calculator->money($replacementTotal - $returnedTotal);
                $differencePaid = $calculator->money($data['difference_paid'] ?? 0);
                if ($differencePaid !== $difference) {
                    throw ValidationException::withMessages([
                        'difference_paid' => 'Selisih barang pengganti harus dibayar sebesar Rp '.number_format($difference, 0, ',', '.').'.',
                    ]);
                }
                $paymentMethodName = $difference > 0 ? ($data['payment_method_name'] ?? 'Tunai') : null;
                $isCashPayment = preg_match('/tunai|cash/i', (string) ($paymentMethodName ?? 'Tunai')) === 1;
                if ($difference > 0
                    && ! $isCashPayment
                    && blank($data['payment_reference'] ?? null)) {
                    throw ValidationException::withMessages([
                        'payment_reference' => 'Nomor referensi wajib diisi untuk pembayaran non-tunai.',
                    ]);
                }

                $refund = RefundPenjualan::create([
                    'code' => $data['code'],
                    'penjualan_id' => $sale->id,
                    'outlet_id' => $outletId,
                    'user_id' => $request->user()->getAuthIdentifier(),
                    'returned_total' => $returnedTotal,
                    'replacement_total' => $replacementTotal,
                    'difference' => $difference,
                    'payment_method_name' => $paymentMethodName,
                    'payment_reference' => $difference > 0 && ! $isCashPayment ? ($data['payment_reference'] ?? null) : null,
                    'notes' => $data['notes'] ?? null,
                ]);

                // Return to and issue from OwnerStock only. Warehouse Stock is not changed.
                foreach ($returnStockQtys as $ownerStockId => $qty) {
                    $stock = $returnedStocks->get($ownerStockId);
                    $stockService->receive(
                        $outletId,
                        $stock->product_id,
                        $qty,
                        (float) ($stock->hpp ?? 0),
                        [
                            'stock_id' => $stock->stock_id,
                            'sku' => $stock->sku,
                            'expired_at' => $stock->expired_at,
                            'batch_number' => $stock->batch_number,
                            'source_type' => RefundPenjualan::class,
                            'source_id' => $refund->id,
                            'notes' => 'Barang kembali dari retur penjualan',
                        ],
                        RefundPenjualan::class,
                        $refund->id,
                        $request->user()
                    );
                }

                foreach ($replacementItems as $replacement) {
                    $stockService->issue(
                        $replacement['stock'],
                        $replacement['qty'],
                        RefundPenjualan::class,
                        $refund->id,
                        $request->user(),
                        "Pengganti retur penjualan {$refund->code} - {$replacement['stock']->product?->name}"
                    );
                }

                foreach ($returnItems as $returnItem) {
                    $refund->items()->create([
                        'type' => 'return',
                        'product_id' => $returnItem['sale_item']->product_id,
                        'penjualan_item_id' => $returnItem['sale_item']->id,
                        'owner_stock_id' => $returnItem['sale_item']->owner_stock_id,
                        'qty' => $returnItem['qty'],
                        'unit_price' => $returnItem['unit_price'],
                        'subtotal' => $calculator->money($returnItem['unit_price'] * $returnItem['qty']),
                    ]);
                }
                foreach ($replacementItems as $replacement) {
                    $refund->items()->create([
                        'type' => 'replacement',
                        'product_id' => $replacement['stock']->product_id,
                        'owner_stock_id' => $replacement['stock']->id,
                        'qty' => $replacement['qty'],
                        'unit_price' => $replacement['unit_price'],
                        'subtotal' => $calculator->money($replacement['subtotal']),
                    ]);
                }

                if ($difference > 0) {
                    $session = CashierSession::query()
                        ->where('outlet_id', $outletId)
                        ->where('cashier_id', $request->user()->getAuthIdentifier())
                        ->where('status', 'open')
                        ->lockForUpdate()
                        ->first();

                    if ($session) {
                        $refund->update(['cashier_session_id' => $session->id]);
                        if ($isCashPayment) {
                            CashierDrawerEntry::create([
                                'cashier_session_id' => $session->id,
                                'outlet_id' => $outletId,
                                'cashier_id' => $request->user()->getAuthIdentifier(),
                                'type' => 'cash_in',
                                'amount' => $difference,
                                'note' => "Selisih tukar barang {$refund->code}",
                                'recorded_at' => now(),
                            ]);
                        }
                    }
                }

                return $refund;
            });

            return redirect()->route('refundPenjualan.index')
                ->with('toast_success', "Berhasil menyimpan {$refund->code}. Barang retur masuk kembali ke stock toko.");
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return redirect()->back()->withInput()->with('toast_error', 'Gagal menyimpan retur penjualan: '.$e->getMessage());
        }
    }

    public function show(RefundPenjualan $refundPenjualan)
    {
        $this->ensureAccess($refundPenjualan);

        return view('refundPenjualans.show', [
            'refundPenjualan' => $refundPenjualan->load(['penjualan', 'outlet', 'user', 'items.product']),
        ]);
    }

    private function ensureAccess(RefundPenjualan $refundPenjualan): void
    {
        $request = request();
        $outletId = OutletAccess::id($request, false);
        if ($outletId && (int) $outletId !== (int) $refundPenjualan->outlet_id) {
            abort(403);
        }
    }
}
