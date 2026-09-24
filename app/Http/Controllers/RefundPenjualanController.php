<?php

namespace App\Http\Controllers;

use App\Models\CashierDrawerEntry;
use App\Models\CashierSession;
use App\Models\OutletPrice;
use App\Models\OwnerStock;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Models\Product;
use App\Models\RefundPenjualan;
use App\Models\RefundPenjualanItem;
use App\Services\OutletStockService;
use App\Services\PriceCalculator;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class RefundPenjualanController extends Controller
{
    public function index(Request $request)
    {
        $outletId = OutletAccess::id($request, false);
        $query = RefundPenjualan::with(['penjualan', 'outlet', 'user', 'items.product'])->latest();

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        return view('refundPenjualans.index', ['refundPenjualans' => $query->get()]);
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

    /** Search invoices for the optional receipt-assisted return flow. */
    public function invoices(Request $request)
    {
        $outletId = OutletAccess::id($request);
        $search = trim((string) $request->input('search', ''));

        $sales = Penjualan::with(['items.product', 'items.ownerStock'])
            ->where('outlet_id', $outletId)
            ->where(function ($query) {
                $query->where('status', 'paid')->orWhereNull('status');
            })
            ->when($search !== '', fn ($query) => $query->where('code', 'like', "%{$search}%"))
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
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'owner_stock_id' => $item->owner_stock_id,
                        'name' => $item->product?->name ?? 'Produk dihapus',
                        'code' => $item->product?->code,
                        'qty' => (int) $item->qty,
                        'available_qty' => max(0, (int) $item->qty - (int) ($returned[$item->id] ?? 0)),
                        'price' => (float) $item->price,
                        'subtotal' => (float) ($item->subtotal ?? ((int) $item->qty * (float) $item->price)),
                    ];
                })->values(),
            ];
        })->values());
    }

    /**
     * Resolve one scanned product. Returned items may have zero current stock;
     * replacement items must have stock in the selected outlet.
     */
    public function products(Request $request, PriceCalculator $calculator)
    {
        $outletId = OutletAccess::id($request);
        $barcode = trim((string) $request->input('barcode', $request->input('search', '')));
        $purpose = $request->input('purpose', 'return');

        if ($barcode === '') {
            return response()->json(['message' => 'Barcode produk wajib diisi.'], 422);
        }

        $stockQuery = OwnerStock::with(['product', 'stock'])
            ->where('owner_id', $outletId)
            ->where(function ($query) {
                $query->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
            });

        $product = Product::query()->where('code', $barcode)->first();
        $stock = null;
        if ($product) {
            $stock = (clone $stockQuery)
                ->where('product_id', $product->id)
                ->when($purpose === 'replacement', fn ($query) => $query->where('qty', '>', 0))
                ->orderBy('created_at')->orderBy('id')->first();
        } else {
            $stock = (clone $stockQuery)
                ->where('sku', $barcode)
                ->when($purpose === 'replacement', fn ($query) => $query->where('qty', '>', 0))
                ->orderBy('created_at')->orderBy('id')->first();
            $product = $stock?->product;
        }

        if (! $product) {
            return response()->json(['message' => 'Barcode produk tidak ditemukan.'], 404);
        }
        if ($purpose === 'replacement' && ! $stock) {
            return response()->json(['message' => 'Stok produk pengganti tidak tersedia di outlet ini.'], 422);
        }

        $rule = OutletPrice::query()
            ->where('outlet_id', $outletId)
            ->where('product_id', $product->id)
            ->currentlyActive()
            ->first();
        $price = $calculator->calculateItem(
            (float) ($stock?->hpp ?? $product->harga_beli ?? 0),
            $rule,
            $product
        );

        return response()->json([
            'product_id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'owner_stock_id' => $stock?->id,
            'stock_qty' => (int) ($stock?->qty ?? 0),
            'price' => (float) $price['price'],
        ]);
    }

    /** Backward-compatible stock search for existing integrations. */
    public function stocks(Request $request, PriceCalculator $calculator)
    {
        $outletId = OutletAccess::id($request);
        $search = trim((string) $request->input('search', ''));
        $rules = OutletPrice::where('outlet_id', $outletId)->currentlyActive()->get()->keyBy('product_id');
        $stocks = OwnerStock::with('product')
            ->where('owner_id', $outletId)
            ->where('qty', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($scope) use ($search) {
                    $scope->where('sku', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($product) => $product
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('created_at')->orderBy('id')->limit(25)->get();

        return response()->json($stocks->map(function (OwnerStock $stock) use ($calculator, $rules) {
            $price = $calculator->calculateItem(
                (float) ($stock->hpp ?? $stock->product?->harga_beli ?? 0),
                $rules->get($stock->product_id), $stock->product
            )['price'];

            return [
                'id' => $stock->id,
                'product_id' => $stock->product_id,
                'name' => $stock->product?->name ?? 'Produk dihapus',
                'code' => $stock->product?->code,
                'sku' => $stock->sku,
                'qty' => (int) $stock->qty,
                'available_qty' => (int) $stock->qty,
                'price' => (float) $price,
            ];
        })->values());
    }

    public function store(Request $request, PriceCalculator $calculator, OutletStockService $stockService)
    {
        $outletId = OutletAccess::id($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:refund_penjualans,code'],
            'penjualan_id' => ['nullable', 'integer', 'exists:penjualans,id'],
            'returns' => ['required', 'array', 'min:1'],
            'returns.*.item_id' => ['nullable', 'integer', 'exists:penjualan_items,id'],
            'returns.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'returns.*.owner_stock_id' => ['nullable', 'integer', 'exists:owner_stocks,id'],
            'returns.*.qty' => ['required', 'integer', 'min:1'],
            'replacements' => ['required', 'array', 'min:1'],
            'replacements.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'replacements.*.owner_stock_id' => ['nullable', 'integer', 'exists:owner_stocks,id'],
            'replacements.*.qty' => ['required', 'integer', 'min:1'],
            'difference_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method_name' => ['nullable', 'string', 'max:100'],
            'payment_reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'return_pin' => ['required', 'digits_between:4,8'],
        ], [
            'code.unique' => 'Kode retur penjualan sudah digunakan.',
            'penjualan_id.exists' => 'Invoice tidak ditemukan.',
            'returns.required' => 'Scan minimal satu barang yang diretur.',
            'returns.min' => 'Scan minimal satu barang yang diretur.',
            'replacements.required' => 'Scan minimal satu barang pengganti.',
            'replacements.min' => 'Scan minimal satu barang pengganti.',
        ]);

        $settings = json_decode(Storage::disk('public')->get('settings.json') ?? '{}', true) ?? [];
        $returnPinHash = $settings['return_pin_hash'] ?? null;
        if (! $returnPinHash) {
            throw ValidationException::withMessages(['return_pin' => 'PIN retur belum dikonfigurasi oleh superadmin.']);
        }
        if (! Hash::check((string) $data['return_pin'], $returnPinHash)) {
            throw ValidationException::withMessages(['return_pin' => 'PIN retur salah.']);
        }

        try {
            $refund = DB::transaction(function () use ($request, $data, $outletId, $calculator, $stockService) {
                $sale = null;
                if (! empty($data['penjualan_id'])) {
                    $sale = Penjualan::query()
                        ->whereKey($data['penjualan_id'])
                        ->where('outlet_id', $outletId)
                        ->where(function ($query) {
                            $query->where('status', 'paid')->orWhereNull('status');
                        })
                        ->lockForUpdate()->first();
                    if (! $sale) {
                        throw ValidationException::withMessages(['penjualan_id' => 'Invoice tidak tersedia untuk outlet ini.']);
                    }
                }

                $returnRows = collect($data['returns'])->values();
                $saleItemIds = $returnRows->pluck('item_id')->filter()->map(fn ($id) => (int) $id)->values();
                if ($saleItemIds->isNotEmpty() && ! $sale) {
                    throw ValidationException::withMessages(['returns' => 'Item invoice hanya dapat digunakan setelah scan no nota.']);
                }
                $saleItems = $saleItemIds->isNotEmpty()
                    ? PenjualanItem::with('product')
                        ->whereIn('id', $saleItemIds->all())
                        ->when($sale, fn ($query) => $query->where('penjualan_id', $sale->id))
                        ->lockForUpdate()->get()->keyBy('id')
                    : collect();
                if ($saleItemIds->count() !== $saleItems->count()) {
                    throw ValidationException::withMessages(['returns' => 'Ada item retur yang tidak sesuai dengan invoice.']);
                }

                $alreadyReturned = $saleItemIds->isNotEmpty()
                    ? RefundPenjualanItem::query()
                        ->where('type', 'return')->whereIn('penjualan_item_id', $saleItemIds->all())
                        ->select('penjualan_item_id', DB::raw('SUM(qty) as qty'))
                        ->groupBy('penjualan_item_id')->pluck('qty', 'penjualan_item_id')
                    : collect();
                $requestedReturnQty = $returnRows->filter(fn ($row) => ! empty($row['item_id']))
                    ->groupBy('item_id')->map(fn ($rows) => $rows->sum('qty'));
                $productIds = $returnRows->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique();
                $products = $productIds->isNotEmpty()
                    ? Product::whereIn('id', $productIds->all())->get()->keyBy('id')
                    : collect();
                $rules = OutletPrice::where('outlet_id', $outletId)->currentlyActive()->get()->keyBy('product_id');

                $stockIds = $returnRows->pluck('owner_stock_id')->filter()->map(fn ($id) => (int) $id)
                    ->merge($saleItems->pluck('owner_stock_id')->filter()->map(fn ($id) => (int) $id))->unique();
                $explicitStocks = $stockIds->isNotEmpty()
                    ? OwnerStock::with(['product', 'stock'])->where('owner_id', $outletId)
                        ->whereIn('id', $stockIds->all())->lockForUpdate()->get()->keyBy('id')
                    : collect();
                $stockCandidates = [];
                $candidateFor = function (int $productId) use (&$stockCandidates, $outletId) {
                    if (! array_key_exists($productId, $stockCandidates)) {
                        $stockCandidates[$productId] = OwnerStock::with(['product', 'stock'])
                            ->where('owner_id', $outletId)->where('product_id', $productId)
                            ->where(function ($query) {
                                $query->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
                            })->orderBy('created_at')->orderBy('id')->lockForUpdate()->first();
                    }

                    return $stockCandidates[$productId];
                };

                $returnItems = [];
                $returnedTotal = 0;
                foreach ($returnRows as $row) {
                    $saleItem = ! empty($row['item_id']) ? $saleItems->get((int) $row['item_id']) : null;
                    if (! $saleItem && empty($row['product_id'])) {
                        throw ValidationException::withMessages(['returns' => 'Setiap barang retur harus berasal dari scan produk atau invoice.']);
                    }
                    $product = $saleItem?->product ?? $products->get((int) $row['product_id']);
                    if (! $product) {
                        throw ValidationException::withMessages(['returns' => 'Produk barang retur tidak ditemukan.']);
                    }
                    if ($saleItem) {
                        $remainingQty = (int) $saleItem->qty - (int) ($alreadyReturned[$saleItem->id] ?? 0);
                        if ((int) $requestedReturnQty[$saleItem->id] > $remainingQty) {
                            throw ValidationException::withMessages(['returns' => "Jumlah retur {$product->name} melebihi sisa jumlah item pada invoice."]);
                        }
                    }

                    $stock = ! empty($row['owner_stock_id']) ? $explicitStocks->get((int) $row['owner_stock_id']) : null;
                    if ($stock && (int) $stock->product_id !== (int) $product->id) {
                        throw ValidationException::withMessages(['returns' => 'Batch stock barang retur tidak sesuai dengan produk.']);
                    }
                    $stock = $stock
                        ?: ($saleItem?->owner_stock_id ? $explicitStocks->get((int) $saleItem->owner_stock_id) : null)
                        ?: $candidateFor((int) $product->id);
                    $unitPrice = $saleItem
                        ? (float) $saleItem->price
                        : (float) $calculator->calculateItem(
                            (float) ($stock?->hpp ?? $product->harga_beli ?? 0),
                            $rules->get($product->id), $product
                        )['price'];
                    $returnItems[] = [
                        'saleItem' => $saleItem,
                        'product' => $product,
                        'stock' => $stock,
                        'ownerStockId' => $stock?->id,
                        'qty' => (int) $row['qty'],
                        'unit_price' => $unitPrice,
                    ];
                    $returnedTotal += $unitPrice * (int) $row['qty'];
                }

                $replacementRows = collect($data['replacements'])->values();
                $replacementStockIds = $replacementRows->pluck('owner_stock_id')->filter()->map(fn ($id) => (int) $id)->unique();
                $replacementExplicitStocks = $replacementStockIds->isNotEmpty()
                    ? OwnerStock::with('product')->where('owner_id', $outletId)
                        ->whereIn('id', $replacementStockIds->all())->lockForUpdate()->get()->keyBy('id')
                    : collect();
                $replacementQuantities = [];
                foreach ($replacementRows as $row) {
                    $stock = ! empty($row['owner_stock_id']) ? $replacementExplicitStocks->get((int) $row['owner_stock_id']) : null;
                    $productId = (int) ($row['product_id'] ?? $stock?->product_id ?? 0);
                    if (! $productId) {
                        throw ValidationException::withMessages(['replacements' => 'Setiap barang pengganti harus berasal dari scan produk.']);
                    }
                    if ($stock && (int) $stock->product_id !== $productId) {
                        throw ValidationException::withMessages(['replacements' => 'Batch stock barang pengganti tidak sesuai dengan produk.']);
                    }
                    $replacementQuantities[$productId] = ($replacementQuantities[$productId] ?? 0) + (int) $row['qty'];
                }

                $returnedTotal = $calculator->money($returnedTotal);
                $refund = RefundPenjualan::create([
                    'code' => $data['code'], 'penjualan_id' => $sale?->id, 'outlet_id' => $outletId,
                    'user_id' => $request->user()->getAuthIdentifier(), 'returned_total' => $returnedTotal,
                    'replacement_total' => 0, 'difference' => 0, 'notes' => $data['notes'] ?? null,
                ]);

                foreach ($returnItems as $returnItem) {
                    $stock = $returnItem['stock'];
                    $stockService->receive(
                        $outletId, $returnItem['product']->id, $returnItem['qty'],
                        (float) ($stock?->hpp ?? $returnItem['product']->harga_beli ?? 0),
                        [
                            'stock_id' => $stock?->stock_id, 'sku' => $stock?->sku,
                            'expired_at' => $stock?->expired_at, 'batch_number' => $stock?->batch_number,
                            'source_type' => RefundPenjualan::class, 'source_id' => $refund->id,
                            'notes' => 'Barang kembali dari retur penjualan',
                        ], RefundPenjualan::class, $refund->id, $request->user()
                    );
                }

                $replacementProductIds = collect(array_keys($replacementQuantities))->map(fn ($id) => (int) $id);
                $replacementProducts = Product::whereIn('id', $replacementProductIds->all())->get()->keyBy('id');
                $replacementStocks = OwnerStock::with('product')->where('owner_id', $outletId)
                    ->whereIn('product_id', $replacementProductIds->all())->where('qty', '>', 0)
                    ->where(function ($query) {
                        $query->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
                    })->orderBy('created_at')->orderBy('id')->lockForUpdate()->get()->groupBy('product_id');
                $replacementItems = [];
                $replacementTotal = 0;
                foreach ($replacementQuantities as $productId => $requestedQty) {
                    $product = $replacementProducts->get($productId);
                    $remainingQty = (int) $requestedQty;
                    foreach ($replacementStocks->get($productId, collect()) as $stock) {
                        if ($remainingQty <= 0) {
                            break;
                        }
                        $qty = min($remainingQty, (int) $stock->qty);
                        if ($qty < 1) {
                            continue;
                        }
                        $price = $calculator->calculateItem(
                            (float) ($stock->hpp ?? $product?->harga_beli ?? 0),
                            $rules->get($productId), $product
                        )['price'];
                        $subtotal = (float) $price * $qty;
                        $replacementItems[] = [
                            'stock' => $stock, 'qty' => $qty, 'unit_price' => (float) $price, 'subtotal' => $subtotal,
                        ];
                        $replacementTotal += $subtotal;
                        $remainingQty -= $qty;
                    }
                    if ($remainingQty > 0) {
                        throw ValidationException::withMessages(['replacements' => "Jumlah stock {$product?->name} tidak mencukupi."]);
                    }
                }

                $replacementTotal = $calculator->money($replacementTotal);
                if ($replacementTotal < $returnedTotal) {
                    throw ValidationException::withMessages(['replacements' => 'Total barang pengganti harus bernilai sama atau lebih mahal dari total barang yang diretur.']);
                }
                $difference = $calculator->money($replacementTotal - $returnedTotal);
                $differencePaid = $calculator->money($data['difference_paid'] ?? 0);
                if ($differencePaid !== $difference) {
                    throw ValidationException::withMessages(['difference_paid' => 'Selisih barang pengganti harus dibayar sebesar Rp '.number_format($difference, 0, ',', '.').'.']);
                }
                $paymentMethodName = $difference > 0 ? ($data['payment_method_name'] ?? 'Tunai') : null;
                $isCashPayment = preg_match('/tunai|cash/i', (string) ($paymentMethodName ?? 'Tunai')) === 1;
                if ($difference > 0 && ! $isCashPayment && blank($data['payment_reference'] ?? null)) {
                    throw ValidationException::withMessages(['payment_reference' => 'Nomor referensi wajib diisi untuk pembayaran non-tunai.']);
                }
                $refund->update([
                    'replacement_total' => $replacementTotal, 'difference' => $difference,
                    'payment_method_name' => $paymentMethodName,
                    'payment_reference' => $difference > 0 && ! $isCashPayment ? ($data['payment_reference'] ?? null) : null,
                ]);

                foreach ($replacementItems as $replacement) {
                    $stockService->issue($replacement['stock'], $replacement['qty'], RefundPenjualan::class, $refund->id, $request->user(), "Pengganti retur penjualan {$refund->code} - {$replacement['stock']->product?->name}");
                }
                foreach ($returnItems as $returnItem) {
                    $refund->items()->create([
                        'type' => 'return', 'product_id' => $returnItem['product']->id,
                        'penjualan_item_id' => $returnItem['saleItem']?->id, 'owner_stock_id' => $returnItem['ownerStockId'],
                        'qty' => $returnItem['qty'], 'unit_price' => $returnItem['unit_price'],
                        'subtotal' => $calculator->money($returnItem['unit_price'] * $returnItem['qty']),
                    ]);
                }
                foreach ($replacementItems as $replacement) {
                    $refund->items()->create([
                        'type' => 'replacement', 'product_id' => $replacement['stock']->product_id,
                        'owner_stock_id' => $replacement['stock']->id, 'qty' => $replacement['qty'],
                        'unit_price' => $replacement['unit_price'], 'subtotal' => $calculator->money($replacement['subtotal']),
                    ]);
                }

                if ($difference > 0) {
                    $session = CashierSession::query()->where('outlet_id', $outletId)
                        ->where('cashier_id', $request->user()->getAuthIdentifier())->where('status', 'open')
                        ->lockForUpdate()->first();
                    if ($session) {
                        $refund->update(['cashier_session_id' => $session->id]);
                        if ($isCashPayment) {
                            CashierDrawerEntry::create([
                                'cashier_session_id' => $session->id, 'outlet_id' => $outletId,
                                'cashier_id' => $request->user()->getAuthIdentifier(), 'type' => 'cash_in',
                                'amount' => $difference, 'note' => "Selisih tukar barang {$refund->code}", 'recorded_at' => now(),
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
        $outletId = OutletAccess::id(request(), false);
        if ($outletId && (int) $outletId !== (int) $refundPenjualan->outlet_id) {
            abort(403);
        }
    }
}
