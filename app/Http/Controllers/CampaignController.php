<?php

namespace App\Http\Controllers;

use App\Http\Requests\CampaignRequest;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Voucher;
use App\Support\OutletAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CampaignController extends Controller
{
    public function create(Request $request)
    {
        $this->ensureManagementAccess();

        $campaignType = $request->query('type', 'voucher');
        if (! in_array($campaignType, ['voucher', 'flash_sale', 'bundle'], true)) {
            $campaignType = 'voucher';
        }

        return view('campaigns.form', [
            'campaignType' => $campaignType,
            'campaign' => null,
            'selectedProducts' => [],
            'selectedOutlets' => [],
            'bonusRows' => [],
            'isEdit' => false,
            'routeType' => $campaignType === 'voucher' ? 'voucher' : 'promotion',
            'products' => Product::orderBy('name')->get(['id', 'code', 'name']),
            'outlets' => OutletAccess::outlets(),
        ]);
    }

    public function edit(string $type, int $id)
    {
        $this->ensureManagementAccess();

        if ($type === 'voucher') {
            $campaign = Voucher::with(['products', 'outlets'])->findOrFail($id);
            $selectedProducts = $campaign->products->mapWithKeys(fn ($product) => [$product->id => 1])->all();
            if (empty($selectedProducts) && $campaign->product_id) {
                $selectedProducts = [$campaign->product_id => 1];
            }
            $selectedOutlets = $campaign->outlets->pluck('id')->all();
            if (empty($selectedOutlets) && $campaign->outlet_id) {
                $selectedOutlets = [$campaign->outlet_id];
            }
            $bonusRows = [];
            $campaignType = 'voucher';
        } else {
            $campaign = Promotion::with(['promotionProducts', 'bonuses', 'outlets'])->findOrFail($id);
            $selectedProducts = $campaign->promotionProducts->pluck('required_qty', 'product_id')->all();
            $selectedOutlets = $campaign->outlets->pluck('id')->all();
            if (empty($selectedOutlets) && $campaign->outlet_id) {
                $selectedOutlets = [$campaign->outlet_id];
            }
            $bonusRows = $campaign->bonuses->map(fn ($bonus) => [
                'name' => $bonus->name,
                'qty' => $bonus->qty,
            ])->all();
            $campaignType = $campaign->type;
        }

        return view('campaigns.form', [
            'campaignType' => $campaignType,
            'campaign' => $campaign,
            'selectedProducts' => $selectedProducts,
            'selectedOutlets' => $selectedOutlets,
            'bonusRows' => $bonusRows,
            'isEdit' => true,
            'routeType' => $type,
            'products' => Product::orderBy('name')->get(['id', 'code', 'name']),
            'outlets' => OutletAccess::outlets(),
        ]);
    }

    public function store(CampaignRequest $request)
    {
        $this->ensureManagementAccess();

        $data = $request->validated();
        $type = $data['campaign_type'];
        [$startAt, $endAt] = $this->parseDateRange($request->input('daterange'));

        if ($type === 'voucher') {
            $this->storeVouchers($data, $startAt, $endAt);

            return redirect()->route('voucher.index')->with('toast_success', 'Voucher berhasil dibuat.');
        }

        $this->storePromotion($data, $startAt, $endAt);

        return redirect()->route('voucher.index')->with('toast_success', 'Promo berhasil dibuat.');
    }

    public function update(CampaignRequest $request, string $type, int $id)
    {
        $this->ensureManagementAccess();

        $data = $request->validated();
        [$startAt, $endAt] = $this->parseDateRange($request->input('daterange'));

        if ($type === 'voucher') {
            abort_unless($data['campaign_type'] === 'voucher', 422);
            $voucher = Voucher::findOrFail($id);
            $this->updateVoucher($voucher, $data, $startAt, $endAt);
            $message = 'Voucher berhasil diperbarui.';
        } else {
            abort_unless(in_array($data['campaign_type'], ['flash_sale', 'bundle'], true), 422);
            $promotion = Promotion::findOrFail($id);
            $this->updatePromotion($promotion, $data, $startAt, $endAt);
            $message = 'Promo berhasil diperbarui.';
        }

        return redirect()->route('voucher.index')->with('toast_success', $message);
    }

    private function storeVouchers(array $data, ?Carbon $startAt, ?Carbon $endAt): void
    {
        $quantity = (int) ($data['quota_qty'] ?? 1);
        $autoCode = filter_var($data['code_auto'] ?? false, FILTER_VALIDATE_BOOLEAN) || empty($data['code']);
        $baseCode = $this->resolveCode(
            $data['name'],
            $data['code'] ?? null,
            'VCR',
            $autoCode
        );
        $codes = $this->voucherCodes($baseCode, $quantity);

        if ($this->codesExist($codes)) {
            if (! $autoCode) {
                throw ValidationException::withMessages(['code' => 'Kode voucher atau variannya sudah digunakan.']);
            }

            $suffix = 2;
            do {
                $candidate = Str::limit($baseCode, 90, '').'-'.$suffix++;
                $codes = $this->voucherCodes($candidate, $quantity);
            } while ($this->codesExist($codes));
            $baseCode = $candidate;
        }

        $productIds = $this->productIds($data);
        $outletIds = array_values($data['outlet_ids'] ?? []);

        DB::transaction(function () use ($data, $codes, $startAt, $endAt, $productIds, $outletIds) {
            foreach ($codes as $code) {
                $voucher = Voucher::create([
                    'name' => $data['name'],
                    'code' => $code,
                    'type' => $data['discount_type'],
                    'jenis' => 'keseluruhan',
                    'limit' => 1,
                    'value' => $data['discount_value'],
                    'min_purchase' => $data['min_purchase'] ?? 0,
                    'max_discount_amount' => $data['max_discount_amount'] ?? null,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'desc' => $data['desc'] ?? null,
                    'product_id' => count($productIds) === 1 ? $productIds[0] : null,
                    'outlet_id' => count($outletIds) === 1 ? $outletIds[0] : null,
                ]);

                $voucher->products()->sync($productIds);
                $voucher->outlets()->sync($outletIds);
            }
        });
    }

    private function storePromotion(array $data, ?Carbon $startAt, ?Carbon $endAt): void
    {
        $autoCode = filter_var($data['code_auto'] ?? false, FILTER_VALIDATE_BOOLEAN) || empty($data['code']);
        $code = $this->resolveCode(
            $data['name'],
            $data['code'] ?? null,
            'PROMO',
            $autoCode
        );
        $productIds = $this->productIds($data);
        $outletIds = array_values($data['outlet_ids'] ?? []);

        DB::transaction(function () use ($data, $code, $startAt, $endAt, $outletIds) {
            $isBundle = $data['campaign_type'] === 'bundle';
            $promotion = Promotion::create([
                'name' => $data['name'],
                'code' => $code,
                'type' => $data['campaign_type'],
                'discount_type' => $isBundle ? 'nominal' : $data['discount_type'],
                'discount_value' => $isBundle ? ($data['discount_value'] ?? 0) : $data['discount_value'],
                'bundle_price' => $isBundle ? ($data['discount_value'] ?? null) : null,
                'max_qty' => $data['max_qty'] ?? null,
                'quota_qty' => $data['quota_qty'] ?? null,
                'min_purchase' => $data['min_purchase'] ?? 0,
                'outlet_id' => count($outletIds) === 1 ? $outletIds[0] : null,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'priority' => 100,
                'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'stackable' => false,
                'desc' => $data['desc'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($data['products'] ?? [] as $productId => $requiredQty) {
                $promotion->promotionProducts()->create([
                    'product_id' => (int) $productId,
                    'required_qty' => $requiredQty,
                ]);
            }

            $promotion->outlets()->sync($outletIds);

            foreach ($isBundle ? ($data['bonuses'] ?? []) : [] as $bonus) {
                $promotion->bonuses()->create([
                    'name' => $bonus['name'],
                    'qty' => $bonus['qty'],
                ]);
            }
        });
    }

    private function updateVoucher(Voucher $voucher, array $data, ?Carbon $startAt, ?Carbon $endAt): void
    {
        $code = $this->resolveUpdateCode($voucher->name, $voucher->code, $data, 'VCR', 'voucher', $voucher->id);
        $productIds = $this->productIds($data);
        $outletIds = array_values($data['outlet_ids'] ?? []);

        DB::transaction(function () use ($voucher, $data, $code, $startAt, $endAt, $productIds, $outletIds) {
            $voucher->update([
                'name' => $data['name'],
                'code' => $code,
                'type' => $data['discount_type'],
                'value' => $data['discount_value'],
                'min_purchase' => $data['min_purchase'] ?? 0,
                'max_discount_amount' => $data['max_discount_amount'] ?? null,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'desc' => $data['desc'] ?? null,
                'product_id' => count($productIds) === 1 ? $productIds[0] : null,
                'outlet_id' => count($outletIds) === 1 ? $outletIds[0] : null,
            ]);

            $voucher->products()->sync($productIds);
            $voucher->outlets()->sync($outletIds);
        });
    }

    private function updatePromotion(Promotion $promotion, array $data, ?Carbon $startAt, ?Carbon $endAt): void
    {
        $isBundle = $data['campaign_type'] === 'bundle';
        $code = $this->resolveUpdateCode($promotion->name, $promotion->code, $data, 'PROMO', 'promotion', $promotion->id);
        $productIds = $this->productIds($data);
        $outletIds = array_values($data['outlet_ids'] ?? []);

        DB::transaction(function () use ($promotion, $data, $code, $startAt, $endAt, $outletIds, $isBundle) {
            $promotion->update([
                'name' => $data['name'],
                'code' => $code,
                'type' => $data['campaign_type'],
                'discount_type' => $isBundle ? 'nominal' : ($data['discount_type'] ?? 'percentage'),
                'discount_value' => $isBundle ? ($data['discount_value'] ?? 0) : $data['discount_value'],
                'bundle_price' => $isBundle ? ($data['discount_value'] ?? null) : null,
                'max_qty' => $data['max_qty'] ?? null,
                'quota_qty' => $data['quota_qty'] ?? null,
                'min_purchase' => $data['min_purchase'] ?? 0,
                'outlet_id' => count($outletIds) === 1 ? $outletIds[0] : null,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'is_active' => filter_var($data['is_active'] ?? $promotion->is_active, FILTER_VALIDATE_BOOLEAN),
                'desc' => $data['desc'] ?? null,
            ]);

            $promotion->promotionProducts()->delete();
            foreach ($data['products'] ?? [] as $productId => $requiredQty) {
                $promotion->promotionProducts()->create([
                    'product_id' => (int) $productId,
                    'required_qty' => $requiredQty,
                ]);
            }

            $promotion->outlets()->sync($outletIds);
            $promotion->bonuses()->delete();
            foreach ($isBundle ? ($data['bonuses'] ?? []) : [] as $bonus) {
                $promotion->bonuses()->create([
                    'name' => $bonus['name'],
                    'qty' => $bonus['qty'],
                ]);
            }
        });
    }

    private function productIds(array $data): array
    {
        return array_map('intval', array_keys($data['products'] ?? []));
    }

    private function resolveCode(string $name, ?string $requestedCode, string $prefix, bool $auto): string
    {
        $baseCode = strtoupper(trim((string) $requestedCode));
        if ($auto || $baseCode === '') {
            $slug = strtoupper(Str::slug($name, '-'));
            $baseCode = $prefix.'-'.($slug !== '' ? $slug : now()->format('YmdHis'));
        }

        $baseCode = Str::limit($baseCode, 95, '');
        if (! $auto && $this->codeExists($baseCode)) {
            throw ValidationException::withMessages(['code' => 'Kode voucher atau promo sudah digunakan.']);
        }

        if ($auto) {
            $candidate = $baseCode;
            $suffix = 2;
            while ($this->codeExists($candidate)) {
                $candidate = Str::limit($baseCode, 90, '').'-'.$suffix++;
            }
            $baseCode = $candidate;
        }

        return $baseCode;
    }

    private function resolveUpdateCode(
        string $name,
        ?string $currentCode,
        array $data,
        string $prefix,
        string $ignoreType,
        int $ignoreId
    ): string {
        $code = strtoupper(trim((string) ($data['code'] ?? $currentCode)));
        if ($code === '') {
            return $this->resolveCode($name, null, $prefix, true);
        }

        if ($this->codeExists($code, $ignoreType, $ignoreId)) {
            throw ValidationException::withMessages(['code' => 'Kode voucher atau promo sudah digunakan.']);
        }

        return Str::limit($code, 95, '');
    }

    private function codeExists(string $code, ?string $ignoreType = null, ?int $ignoreId = null): bool
    {
        return Voucher::where('code', $code)
            ->when($ignoreType === 'voucher', fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()
            || Promotion::where('code', $code)
                ->when($ignoreType === 'promotion', fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists();
    }

    private function codesExist(array $codes): bool
    {
        return Voucher::whereIn('code', $codes)->exists()
            || Promotion::whereIn('code', $codes)->exists();
    }

    private function voucherCodes(string $baseCode, int $quantity): array
    {
        if ($quantity === 1) {
            return [$baseCode];
        }

        return collect(range(1, $quantity))
            ->map(fn ($number) => $baseCode.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT))
            ->all();
    }

    private function parseDateRange(?string $range): array
    {
        if (! $range) {
            return [null, null];
        }

        $parts = array_map('trim', explode(' - ', $range, 2));

        return [
            Carbon::parse($parts[0]),
            isset($parts[1]) ? Carbon::parse($parts[1]) : Carbon::parse($parts[0])->endOfDay(),
        ];
    }

    private function ensureManagementAccess(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'admin-gudang', 'owner'], true), 403);
    }
}
