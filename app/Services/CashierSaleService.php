<?php

namespace App\Services;

use App\Models\OwnerStock;
use App\Models\OutletPrice;
use App\Models\Penjualan;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CashierSaleService
{
    public function __construct(
        private readonly PriceCalculator $calculator,
        private readonly OutletStockService $stockService
    )
    {
    }

    public function checkout(Authenticatable $user, array $data): Penjualan
    {
        $outletId = (int) $data['outlet_id'];
        $cart = $user->cart()
            ->wherePivot('outlet_id', (string) $outletId)
            ->withPivot('qty', 'serial_number', 'stock_id', 'owner_stock_id', 'outlet_id')
            ->get();

        if ($cart->isEmpty()) {
            throw new RuntimeException('Keranjang masih kosong.');
        }

        return DB::transaction(function () use ($user, $data, $cart, $outletId) {
            $rules = OutletPrice::where('outlet_id', $outletId)
                ->currentlyActive()
                ->get()
                ->keyBy('product_id');
            $allocations = [];
            $subtotal = 0;
            $discountTotal = 0;

            foreach ($cart as $product) {
                $requestedQty = max(1, (int) $product->pivot->qty);
                $stocks = $this->lockedStocksForCartItem($product, $outletId);
                $remaining = $requestedQty;

                foreach ($stocks as $ownerStock) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $qty = $product->is_serialized ? 1 : min($remaining, (int) $ownerStock->qty);
                    if ($qty <= 0) {
                        continue;
                    }

                    $price = $this->calculator->calculateItem(
                        (float) ($ownerStock->hpp ?? $product->harga_beli ?? 0),
                        $rules->get($product->id),
                        $product
                    );
                    $lineTotal = (int) ($price['price'] * $qty);
                    $allocations[] = compact('product', 'ownerStock', 'qty', 'price') + [
                        'line_total' => $lineTotal,
                        'remaining_total' => $lineTotal,
                    ];
                    $subtotal += $lineTotal;
                    $discountTotal += (int) ($price['disc_toko_amount'] * $qty);
                    $remaining -= $qty;
                }

                if ($remaining > 0) {
                    throw new RuntimeException("Stok outlet untuk {$product->name} tidak mencukupi.");
                }
            }

            $vouchers = $this->lockedVouchers($data['voucher_codes'] ?? [], $outletId);
            $voucherAmounts = [];
            $lineBalances = collect($allocations)->mapWithKeys(fn ($allocation, $index) => [
                $index => $allocation['line_total'],
            ])->all();
            foreach ($vouchers as $voucher) {
                $eligibleIndexes = collect($allocations)
                    ->keys()
                    ->filter(fn ($index) => $voucher->product_id === null
                        || (int) $allocations[$index]['product']->id === (int) $voucher->product_id)
                    ->filter(fn ($index) => $lineBalances[$index] > 0)
                    ->values()
                    ->all();
                $voucherBase = array_sum(array_intersect_key($lineBalances, array_flip($eligibleIndexes)));
                $amount = (int) $this->calculator->voucherAmount($voucher, $voucherBase);
                if (empty($eligibleIndexes)) {
                    throw new RuntimeException("Voucher {$voucher->code} tidak berlaku untuk item transaksi.");
                }
                if ($amount <= 0 && (float) ($voucher->min_purchase ?? 0) > $voucherBase) {
                    throw new RuntimeException("Minimum pembelian voucher {$voucher->code} belum terpenuhi.");
                }
                $voucherAmounts[$voucher->id] = $amount;
                $this->reduceVoucherFromLines($lineBalances, $eligibleIndexes, $amount, $voucherBase);
            }

            $voucherTotal = array_sum($voucherAmounts);
            $grandTotal = max(0, $subtotal - $voucherTotal);
            $paidAmount = (float) ($data['paid_amount'] ?? 0);
            if ($paidAmount < $grandTotal) {
                throw new RuntimeException('Uang pembayaran kurang dari Grand Total.');
            }

            $lastOrder = Penjualan::where('outlet_id', $outletId)
                ->lockForUpdate()
                ->latest('id')
                ->first();
            $nextNumber = $lastOrder ? ((int) preg_replace('/\D+/', '', (string) $lastOrder->code) + 1) : 1;
            $code = 'INV' . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);

            $order = Penjualan::create([
                'code' => $code,
                'customer_id' => $data['customer_id'] ?? null,
                'outlet_id' => $outletId,
                'kasir_id' => $user->getAuthIdentifier(),
                'voucher_id' => $vouchers->first()?->id,
                'salesman_id' => $data['salesman_id'] ?? null,
                'discount' => $discountTotal,
                'total' => $grandTotal,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'voucher_total' => $voucherTotal,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'change_amount' => $paidAmount - $grandTotal,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'payment_method_name' => $data['payment_method_name'] ?? null,
                'status' => 'paid',
            ]);

            foreach ($allocations as $allocation) {
                $product = $allocation['product'];
                $ownerStock = $allocation['ownerStock'];
                $qty = $allocation['qty'];
                $price = $allocation['price'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'stock_id' => $ownerStock->stock_id,
                    'owner_stock_id' => $ownerStock->id,
                    'qty' => $qty,
                    'price' => $price['price'],
                    'subtotal' => $price['price'] * $qty,
                    'serial_number' => $ownerStock->stock?->serial_number ?? $product->pivot->serial_number,
                    ...$price,
                ]);

                $this->stockService->issue(
                    $ownerStock,
                    $qty,
                    Penjualan::class,
                    $order->id,
                    $user,
                    "Penjualan {$order->code} - {$product->name}"
                );
            }

            foreach ($vouchers as $voucher) {
                VoucherRedemption::create([
                    'voucher_id' => $voucher->id,
                    'penjualan_id' => $order->id,
                    'outlet_id' => $outletId,
                    'cashier_id' => $user->getAuthIdentifier(),
                    'code' => $voucher->code,
                    'type' => $voucher->type,
                    'value' => $voucher->value,
                    'amount' => $voucherAmounts[$voucher->id] ?? 0,
                ]);
            }

            if (! empty($data['payment_method_id'])) {
                Transaction::create([
                    'penjualan_id' => $order->id,
                    'payment_method' => $data['payment_method_id'],
                    'tanggal' => now(),
                    'status' => 'paid',
                ]);
            }

            $user->cart()->wherePivot('outlet_id', (string) $outletId)->detach();

            return $order->load(['items.product', 'vouchers', 'paymentMethod']);
        });
    }

    private function lockedStocksForCartItem($product, int $outletId)
    {
        $query = OwnerStock::with('stock')
            ->where('owner_id', $outletId)
            ->where('product_id', $product->id)
            ->where('qty', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
            });

        if ($product->is_serialized && $product->pivot->owner_stock_id) {
            $query->whereKey($product->pivot->owner_stock_id);
        }

        return $query->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();
    }

    private function lockedVouchers(array $codes, int $outletId)
    {
        $codes = collect($codes)
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return collect();
        }

        $vouchers = Voucher::whereIn('code', $codes)
            ->where(function ($query) use ($outletId) {
                $query->whereNull('outlet_id')->orWhere('outlet_id', $outletId);
            })
            ->lockForUpdate()
            ->get()
            ->keyBy('code');
        if ($vouchers->count() !== $codes->count()) {
            throw new RuntimeException('Satu atau lebih kode voucher tidak ditemukan.');
        }

        $ordered = collect();
        foreach ($codes as $code) {
            $voucher = $vouchers->get($code);
            if (! $voucher->isActive()) {
                throw new RuntimeException("Voucher {$code} sudah tidak aktif atau sudah digunakan.");
            }
            if ($voucher->redemptions()->exists()) {
                throw new RuntimeException("Voucher {$code} sudah digunakan.");
            }
            $ordered->push($voucher);
        }

        return $ordered;
    }

    private function reduceVoucherFromLines(array &$lineBalances, array $eligibleIndexes, int $amount, int $base): void
    {
        if ($amount <= 0 || $base <= 0) {
            return;
        }

        $remaining = $amount;
        foreach ($eligibleIndexes as $position => $index) {
            $available = (int) $lineBalances[$index];
            $reduction = $position === count($eligibleIndexes) - 1
                ? min($available, $remaining)
                : min($available, (int) round($amount * $available / $base, 0, PHP_ROUND_HALF_UP));
            $lineBalances[$index] -= $reduction;
            $remaining -= $reduction;
            if ($remaining <= 0) {
                break;
            }
        }
    }
}
