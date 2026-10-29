<?php

namespace App\Services;

use App\Models\CashierSession;
use App\Models\OutletPrice;
use App\Models\OwnerStock;
use App\Models\PaymentMethod;
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
        private readonly OutletStockService $stockService,
        private readonly PromotionService $promotionService,
        private readonly InvoiceNumberGenerator $invoiceNumbers
    ) {}

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
            $cashierSession = CashierSession::query()
                ->where('outlet_id', $outletId)
                ->where('cashier_id', $user->getAuthIdentifier())
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();
            if (! $cashierSession) {
                throw new RuntimeException('Buka kasir dan input saldo awal cash drawer sebelum memproses penjualan.');
            }
            $cashierShift = $cashierSession->activeShifts()->first();
            if (! $cashierShift) {
                throw new RuntimeException('Isi nama kasir atau lakukan ganti shift sebelum memproses penjualan.');
            }

            $rules = OutletPrice::with('outlet')->where('outlet_id', $outletId)
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
                // Harga jual memakai HPP terbaru untuk semua batch; stok tetap berkurang per batch (FIFO).
                $latestHpp = app(\App\Services\LatestHpp::class)->forProduct((int) $outletId, (int) $product->id);

                foreach ($stocks as $ownerStock) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $qty = min($remaining, (int) $ownerStock->qty);
                    if ($qty <= 0) {
                        continue;
                    }

                    $price = $this->calculator->calculateItem(
                        $latestHpp,
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

                // Stok sistem tidak cukup (barang fisik ada tapi belum tercatat): sisanya dijual dari
                // batch MINUS sehingga saldonya negatif dan nanti ditutup oleh penerimaan barang.
                if ($remaining > 0) {
                    $deficitStock = $this->stockService->deficitStock($outletId, (int) $product->id, (float) $latestHpp, $user);
                    $price = $this->calculator->calculateItem(
                        $latestHpp,
                        $rules->get($product->id),
                        $product
                    );
                    $lineTotal = (int) ($price['price'] * $remaining);
                    $allocations[] = [
                        'product' => $product,
                        'ownerStock' => $deficitStock,
                        'qty' => $remaining,
                        'price' => $price,
                        'line_total' => $lineTotal,
                        'remaining_total' => $lineTotal,
                    ];
                    $subtotal += $lineTotal;
                    $discountTotal += (int) ($price['disc_toko_amount'] * $remaining);
                    $remaining = 0;
                }
            }

            // Active promotions are evaluated automatically. PromotionService
            // skips the ones whose item or minimum-purchase conditions are not
            // met, so they never block an otherwise valid checkout.
            $promotions = $this->promotionService->activeForOutlet($outletId, now(), true);

            $promotionResult = $this->promotionService->apply($allocations, $promotions, true);
            $allocations = $promotionResult['allocations'];
            $subtotal = (int) $promotionResult['subtotal'];
            $promotionTotal = (int) $promotionResult['promotion_total'];

            $vouchers = $this->lockedVouchers($data['voucher_codes'] ?? [], $outletId);
            $voucherAmounts = [];
            $lineBalances = collect($allocations)->mapWithKeys(fn ($allocation, $index) => [
                $index => $allocation['line_total'],
            ])->all();
            foreach ($vouchers as $voucher) {
                $eligibleIndexes = collect($allocations)
                    ->keys()
                    ->filter(fn ($index) => $voucher->appliesToProduct((int) $allocations[$index]['product']->id))
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
            // Tidak ada pembulatan di total transaksi. Pembulatan sudah terjadi di harga jual tiap produk
            // (PriceCalculator::calculateItem). Kolom rounding_amount dipertahankan (selalu 0 untuk transaksi
            // baru) agar nota/invoice lama yang punya pembulatan tetap tampil benar.
            $grandTotal = max(0, $subtotal - $voucherTotal);
            $roundingAmount = 0.0;

            // Metode pembayaran bisa punya biaya tambahan (%) dan/atau "nominal fix". Biaya dihitung server dari
            // total belanja, dicatat terpisah (tidak ikut grand_total / omzet), dan ditagihkan ke pelanggan.
            $paymentMethod = ! empty($data['payment_method_id']) ? PaymentMethod::find($data['payment_method_id']) : null;
            $feePercent = $paymentMethod ? max(0.0, min(100.0, (float) $paymentMethod->charge_percent)) : 0.0;
            $feeAmount = $feePercent > 0 ? (float) round($grandTotal * $feePercent / 100) : 0.0;
            $amountDue = $grandTotal + $feeAmount;
            // Nominal fix: uang diterima otomatis pas, nilai dari client diabaikan.
            $paidAmount = $paymentMethod?->is_fixed_amount ? $amountDue : (float) ($data['paid_amount'] ?? 0);
            if ($paidAmount < $amountDue) {
                throw new RuntimeException('Uang Diterima kurang dari total bayar.');
            }

            // Nomor invoice berisi outlet + kasir + tanggal + urutan dari counter terkunci,
            // jadi tidak mungkin kembar walau dua checkout berjalan bersamaan.
            $code = $this->invoiceNumbers->next($outletId, (int) $user->getAuthIdentifier());

            $order = Penjualan::create([
                'code' => $code,
                'customer_id' => $data['customer_id'] ?? null,
                'outlet_id' => $outletId,
                'kasir_id' => $user->getAuthIdentifier(),
                'cashier_session_id' => $cashierSession->id,
                'cashier_shift_id' => $cashierShift->id,
                'voucher_id' => $vouchers->first()?->id,
                'salesman_id' => $data['salesman_id'] ?? null,
                'discount' => $discountTotal,
                'total' => $grandTotal,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'promotion_total' => $promotionTotal,
                'voucher_total' => $voucherTotal,
                'rounding_amount' => $roundingAmount,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'change_amount' => $paidAmount - $amountDue,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'payment_method_name' => $data['payment_method_name'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_fee_percent' => $feePercent,
                'payment_fee_amount' => $feeAmount,
                'status' => 'paid',
            ]);

            foreach ($allocations as $allocation) {
                $product = $allocation['product'];
                $ownerStock = $allocation['ownerStock'];
                $qty = $allocation['qty'];
                $price = $allocation['price'];

                $order->items()->create([
                    ...$price,
                    // Snapshot HPP = biaya asli batch yang terjual (bukan HPP terbaru
                    // yang dipakai menghitung harga), supaya laba penjualan terbaca benar.
                    'hpp' => (float) ($ownerStock->hpp ?? $product->harga_beli ?? $price['hpp']),
                    'product_id' => $product->id,
                    'stock_id' => $ownerStock->stock_id,
                    'owner_stock_id' => $ownerStock->id,
                    'qty' => $qty,
                    'is_oversold' => $ownerStock->isDeficit(),
                    'price' => $this->calculator->money($allocation['line_total'] / max(1, $qty)),
                    'subtotal' => $allocation['line_total'],
                    'base_price' => $this->calculator->money($allocation['base_line_total'] / max(1, $qty)),
                    'base_subtotal' => $allocation['base_line_total'],
                    'promotion_discount' => $allocation['promotion_discount'],
                    'promotion_details' => $allocation['promotion_details'],
                    'serial_number' => null,
                ]);

                $this->stockService->issue(
                    $ownerStock,
                    $qty,
                    Penjualan::class,
                    $order->id,
                    $user,
                    "Penjualan {$order->code} - {$product->name}",
                    $ownerStock->isDeficit()
                );
            }

            foreach ($promotionResult['applications'] as $application) {
                $order->promotionApplications()->create($application);
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

            return $order->load(['items.product', 'cashierShift', 'vouchers', 'promotionApplications.promotion', 'paymentMethod']);
            // 3 percobaan: bila MySQL membatalkan salah satu transaksi karena deadlock,
            // Laravel mengulang checkout otomatis (semua langkahnya ada di dalam transaksi).
        }, 3);
    }

    private function lockedStocksForCartItem($product, int $outletId)
    {
        $query = OwnerStock::with('stock')
            ->where('owner_id', $outletId)
            ->where('product_id', $product->id)
            ->where('qty', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expired_at')->orWhere('expired_at', '>=', today()->toDateString());
            });

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

        $vouchers = Voucher::with(['products', 'outlets'])
            ->whereIn('code', $codes)
            ->where(function ($query) use ($outletId) {
                $query->where(function ($legacy) use ($outletId) {
                    $legacy->whereNull('outlet_id')->orWhere('outlet_id', $outletId);
                })->whereDoesntHave('outlets')
                    ->orWhereHas('outlets', fn ($outlets) => $outlets->whereKey($outletId));
            })
            ->lockForUpdate()
            ->get()
            ->keyBy('code');
        if ($vouchers->count() !== $codes->count()) {
            throw new RuntimeException('Satu atau lebih kode voucher tidak ditemukan.');
        }

        // Satu query untuk semua voucher (sebelumnya redemptions()->exists() per kode).
        $redeemedVoucherIds = VoucherRedemption::whereIn('voucher_id', $vouchers->pluck('id')->all())
            ->distinct()
            ->pluck('voucher_id')
            ->flip();

        $ordered = collect();
        foreach ($codes as $code) {
            $voucher = $vouchers->get($code);
            if (! $voucher->isActive()) {
                throw new RuntimeException("Voucher {$code} sudah tidak aktif atau sudah digunakan.");
            }
            if ($redeemedVoucherIds->has($voucher->id)) {
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