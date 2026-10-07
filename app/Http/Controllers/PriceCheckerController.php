<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\OutletPrice;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Voucher;
use App\Services\LatestHpp;
use App\Services\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PriceCheckerController extends Controller
{
    /**
     * Hasil lookup (produk, harga, promo, voucher) disimpan sebentar per kombinasi
     * (outlet, barcode). Keputusan #4: selisih di bawah 5 menit masih dapat diterima,
     * jadi 60 detik dipilih agar perubahan harga/promo cepat terlihat.
     * Barcode yang tidak ditemukan juga disimpan supaya bot yang menebak barcode
     * tidak membuka query ke database berulang-ulang.
     */
    private const LOOKUP_CACHE_TTL = 60;

    public function index(Request $request)
    {
        $selectedOutlet = $this->resolveOutlet($request);

        return view('price-checker.index', [
            'selectedOutlet' => $selectedOutlet,
            'selectedOutletId' => $selectedOutlet?->id,
        ]);
    }

    public function lookup(Request $request, PriceCalculator $calculator)
    {
        // Validasi dibuat murah: tanpa aturan `exists` (itu 1 query ke DB), karena
        // outlet dicocokkan ke daftar outlet yang sudah di-cache di resolveOutlet().
        $validated = $request->validate([
            // Tolak karakter kontrol (hasil scanner rusak / input iseng), selain itu bebas
            // karena kode produk bisa berisi huruf, angka, tanda hubung, dll.
            'barcode' => ['required', 'string', 'max:100', 'regex:/^[^\x00-\x1F\x7F]+$/'],
            'outlet_id' => ['nullable', 'integer', 'min:1'],
            'outlet' => ['nullable', 'string', 'max:100'],
        ]);

        $barcode = trim($validated['barcode']);
        $outlet = $this->resolveOutlet($request, false);
        $outletId = $outlet?->id;

        $result = Cache::remember(
            'price-checker:lookup:'.($outletId ?? 0).':'.md5($barcode),
            self::LOOKUP_CACHE_TTL,
            fn () => $this->buildLookup($barcode, $outletId, $calculator)
        );

        return response()->json($result['body'], $result['status']);
    }

    /**
     * Menghitung hasil lookup. Dibungkus [status, body] agar hasil "tidak ditemukan"
     * juga bisa di-cache (Cache::remember tidak menyimpan nilai null).
     *
     * @return array{status: int, body: array}
     */
    private function buildLookup(string $barcode, ?int $outletId, PriceCalculator $calculator): array
    {
        $product = Product::query()->where('code', $barcode)->first();

        if (! $product) {
            return [
                'status' => 404,
                'body' => ['message' => 'Produk tidak ditemukan. Silakan scan barcode yang lain.'],
            ];
        }

        $priceRule = $outletId
            ? OutletPrice::query()
                ->where('outlet_id', $outletId)
                ->where('product_id', $product->id)
                ->currentlyActive()
                ->first()
            : null;
        // HPP terbaru dipakai untuk semua batch, sama seperti master harga dan POS.
        $price = $calculator->calculateItem(
            app(LatestHpp::class)->forProduct((int) $outletId, (int) $product->id),
            $priceRule,
            $product
        );

        $promotions = $this->activePromotionsForProduct($product, $outletId)
            ->map(fn (Promotion $promotion) => $this->formatPromotion($promotion, (int) $price['price'], $calculator))
            ->values();
        $vouchers = $this->activeVouchersForProduct($product, $outletId)
            ->map(fn (Voucher $voucher) => $this->formatVoucher($voucher, (int) $price['price'], $calculator))
            ->values();

        return [
            'status' => 200,
            'body' => [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name ?: 'Produk tanpa nama',
                    'barcode' => $product->code,
                    'unit' => $product->satuan,
                ],
                // Sama seperti halaman master harga barang:
                // Harga Coret = HPP setelah pajak + margin, Harga Jual POS = harga akhir.
                'price_strike' => (int) $calculator->money(
                    $price['hpp_setelah_pajak'] + $price['margin_amount']
                ),
                'price' => (int) $price['price'],
                'promotions' => $promotions->concat($vouchers)->values()->all(),
            ],
        ];
    }

    /**
     * Daftar outlet ringkas (id, name, slug) dari cache, supaya tiap lookup tidak
     * memuat tabel outlet dan menghitung slug satu per satu. Cache dihapus otomatis
     * saat outlet disimpan/dihapus (lihat Outlet::booted()).
     *
     * @return array<int, array{id: int, name: ?string, slug: string}>
     */
    private function outlets(): array
    {
        return Cache::remember(Outlet::LIST_CACHE_KEY, Outlet::LIST_CACHE_TTL, function () {
            return Outlet::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Outlet $outlet) => [
                    'id' => (int) $outlet->id,
                    'name' => $outlet->name,
                    'slug' => Str::slug((string) $outlet->name),
                ])
                ->all();
        });
    }

    private function resolveOutlet(Request $request, bool $fallbackToFirst = true): ?object
    {
        $outlets = collect($this->outlets());

        if ($request->filled('outlet_id')) {
            $outlet = $outlets->firstWhere('id', $request->integer('outlet_id'));
            abort_if(! $outlet, 404, 'Outlet tidak ditemukan.');

            return (object) $outlet;
        }

        $identifier = trim((string) $request->input('outlet', ''));
        if ($identifier !== '') {
            $identifierSlug = Str::slug($identifier);
            $outlet = $outlets->first(fn (array $outlet) => $outlet['slug'] === $identifierSlug);

            abort_if(! $outlet, 404, 'Outlet tidak ditemukan.');

            return (object) $outlet;
        }

        $first = $fallbackToFirst ? $outlets->first() : null;

        return $first ? (object) $first : null;
    }

    private function activePromotionsForProduct(Product $product, ?int $outletId)
    {
        // `outlets` tidak di-eager-load: hanya dipakai di klausa whereHas, tidak di formatPromotion().
        // `promotionProducts.product` hanya dibutuhkan promo bundle, dimuat setelah query (lihat bawah).
        $query = Promotion::query()
            ->with(['bonuses'])
            ->whereHas('promotionProducts', fn ($productQuery) => $productQuery->where('product_id', $product->id))
            ->where('is_active', true)
            ->where(function ($dateQuery) {
                $dateQuery->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($dateQuery) {
                $dateQuery->whereNull('end_at')->orWhere('end_at', '>=', now());
            })
            ->where(function ($quotaQuery) {
                $quotaQuery->whereNull('quota_qty')->orWhereColumn('used_qty', '<', 'quota_qty');
            });

        if ($outletId) {
            $query->where(function ($outletQuery) use ($outletId) {
                $outletQuery
                    ->where(function ($legacyQuery) use ($outletId) {
                        $legacyQuery->whereNull('outlet_id')->orWhere('outlet_id', $outletId);
                    })
                    ->whereDoesntHave('outlets')
                    ->orWhereHas('outlets', fn ($outletsQuery) => $outletsQuery->whereKey($outletId));
            });
        } else {
            // With no outlet selected, only expose promotions that are explicitly
            // global. The page normally selects the first configured outlet.
            $query->whereNull('outlet_id')->whereDoesntHave('outlets');
        }

        $promotions = $query
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        // Daftar syarat paket ("2× produk A + 1× produk B") hanya ditampilkan untuk promo bundle.
        $promotions
            ->filter(fn (Promotion $promotion) => strtolower(trim((string) $promotion->type)) === 'bundle')
            ->load('promotionProducts.product');

        return $promotions;
    }

    private function activeVouchersForProduct(Product $product, ?int $outletId)
    {
        return Voucher::query()
            ->with(['products', 'outlets'])
            ->withCount('redemptions')
            ->where(function ($productQuery) use ($product) {
                $productQuery
                    ->where('product_id', $product->id)
                    ->orWhereHas('products', fn ($productsQuery) => $productsQuery->whereKey($product->id))
                    ->orWhere(function ($globalQuery) {
                        $globalQuery->whereNull('product_id')->whereDoesntHave('products');
                    });
            })
            ->where(function ($dateQuery) {
                $dateQuery->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($dateQuery) {
                $dateQuery->whereNull('end_at')->orWhere('end_at', '>=', now());
            })
            ->orderBy('id')
            ->get()
            ->filter(function (Voucher $voucher) use ($outletId, $product) {
                $limitAvailable = $voucher->limit === null
                    || ((int) $voucher->limit > 0 && (int) $voucher->redemptions_count < (int) $voucher->limit);

                return $limitAvailable
                    && $voucher->appliesToOutlet($outletId)
                    && $voucher->appliesToProduct($product->id);
            })
            ->values();
    }

    private function formatPromotion(Promotion $promotion, int $basePrice, PriceCalculator $calculator): array
    {
        $type = strtolower(trim((string) $promotion->type));
        $discountType = strtolower(trim((string) $promotion->discount_type));
        $promoPrice = null;
        $discount = 0;
        $terms = [];
        $limits = [];

        if ($type === 'flash_sale') {
            if ($discountType === 'fixed_price') {
                $promoPrice = min($basePrice, $calculator->money($promotion->discount_value));
                $discount = max(0, $basePrice - $promoPrice);
                $summary = 'Harga spesial flash sale';
            } else {
                $discount = $calculator->discountAmount(
                    $basePrice,
                    $discountType,
                    (float) $promotion->discount_value
                );
                $promoPrice = max(0, $basePrice - $discount);
                $summary = $discountType === 'percentage'
                    ? 'Diskon '.$this->number($promotion->discount_value).'%'
                    : 'Hemat '.$this->rupiah($discount);
            }

            if ($promotion->max_qty !== null) {
                $limits[] = 'Maks. '.$this->number($promotion->max_qty).' unit berdiskon per transaksi';
            }
        } elseif ($type === 'bundle') {
            $requirements = $promotion->promotionProducts
                ->map(function ($target) {
                    $name = $target->product?->name ?: 'produk';

                    return $this->number($target->required_qty).'× '.$name;
                })
                ->implode(' + ');
            $summary = 'Promo bundle';
            if ($requirements !== '') {
                $terms[] = 'Beli paket: '.$requirements;
            }

            $bundleDiscount = $calculator->money($promotion->bundle_price);
            if ($bundleDiscount > 0) {
                $summary .= ' · Hemat '.$this->rupiah($bundleDiscount).' per paket';
            }

            if ($promotion->max_qty !== null) {
                $limits[] = 'Maks. '.$this->number($promotion->max_qty).' paket per transaksi';
            }
        } else {
            $summary = 'Promo tersedia';
        }

        if ($promotion->bonuses->isNotEmpty()) {
            $bonuses = $promotion->bonuses
                ->map(fn ($bonus) => 'Bonus '.$this->number($bonus->qty).'× '.($bonus->name ?: 'produk'))
                ->implode(', ');
            $summary .= ' · '.$bonuses;
        }

        if ((float) $promotion->min_purchase > 0) {
            $terms[] = 'Min. belanja '.$this->rupiah($promotion->min_purchase);
        }

        if ($promotion->desc && trim($promotion->desc) !== '') {
            $terms[] = trim($promotion->desc);
        }

        if ($promotion->quota_qty !== null) {
            $remaining = max(0, (int) $promotion->quota_qty - (int) $promotion->used_qty);
            $limits[] = 'Sisa kuota '.$this->number($remaining).' dari '.$this->number($promotion->quota_qty);
        }

        if (! $promotion->stackable) {
            $limits[] = 'Tidak dapat digabung dengan promo lain';
        }

        return [
            'name' => $promotion->name ?: 'Promo',
            'summary' => $summary,
            'terms' => $terms,
            'period' => $this->period($promotion->start_at, $promotion->end_at),
            'limits' => $limits,
            'price' => $promoPrice,
            'discount' => $discount,
            'type' => $type,
        ];
    }

    private function formatVoucher(Voucher $voucher, int $basePrice, PriceCalculator $calculator): array
    {
        $discount = (int) $calculator->voucherAmount($voucher, $basePrice);
        $discountType = strtolower(trim((string) $voucher->type));
        $summary = $discountType === 'percentage'
            ? 'Voucher diskon '.$this->number($voucher->value).'%'
            : 'Voucher hemat '.$this->rupiah($voucher->value);
        $terms = [];
        $limits = [];

        if ($voucher->min_purchase && (float) $voucher->min_purchase > 0) {
            $terms[] = 'Min. belanja '.$this->rupiah($voucher->min_purchase);
        }

        if ($voucher->desc && trim($voucher->desc) !== '') {
            $terms[] = trim($voucher->desc);
        }

        if ($voucher->max_discount_amount !== null) {
            $limits[] = 'Maks. potongan '.$this->rupiah($voucher->max_discount_amount);
        }

        if ($voucher->limit !== null) {
            $remaining = max(0, (int) $voucher->limit - (int) $voucher->redemptions_count);
            $limits[] = 'Sisa pemakaian '.$this->number($remaining).' dari '.$this->number($voucher->limit);
        }

        return [
            'name' => $voucher->name ?: 'Voucher',
            'summary' => $summary,
            'terms' => $terms,
            'period' => $this->period($voucher->start_at, $voucher->end_at),
            'limits' => $limits,
            'price' => $discount > 0 ? max(0, $basePrice - $discount) : null,
            'discount' => $discount,
            'type' => 'voucher',
        ];
    }

    private function period($start, $end): string
    {
        $format = fn ($date) => $date->copy()->locale('id')->translatedFormat('j M Y, H:i');

        if ($start && $end) {
            return $format($start).' – '.$format($end);
        }

        if ($end) {
            return 'Sampai '.$format($end);
        }

        if ($start) {
            return 'Mulai '.$format($start);
        }

        return 'Tanpa batas waktu';
    }

    private function rupiah(float|int|null $amount): string
    {
        return 'Rp '.number_format((float) ($amount ?? 0), 0, ',', '.');
    }

    private function number(float|int|null $amount): string
    {
        $amount = (float) ($amount ?? 0);

        return fmod($amount, 1) === 0.0
            ? number_format($amount, 0, ',', '.')
            : number_format($amount, 2, ',', '.');
    }
}