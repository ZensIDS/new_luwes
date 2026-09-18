<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoucherRequest;
use App\Models\Promotion;
use App\Models\Voucher;
use App\Support\OutletAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureManagementAccess();
        $query = Voucher::withCount('redemptions')->with(['product', 'products', 'outlets'])->latest();
        if ($request->wantsJson()) {
            $vouchers = $query
                ->whereDoesntHave('redemptions')
                ->where(function ($query) {
                    $query->whereNull('start_at')->orWhere('start_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('end_at')->orWhere('end_at', '>=', now());
                })
                ->get()
                ->filter(fn (Voucher $voucher) => $voucher->isActive())
                ->values();

            return response()->json($vouchers);
        }

        $campaigns = $this->groupVouchers($query->get())
            ->each(fn (Voucher $voucher) => $voucher->setAttribute('campaign_kind', 'voucher'));
        $campaigns = $campaigns->concat(
            Promotion::with(['outlet', 'outlets', 'products', 'bonuses'])
                ->latest()
                ->get()
                ->each(fn (Promotion $promotion) => $promotion->setAttribute('campaign_kind', 'promotion'))
        )->sortByDesc('created_at')->values();

        return view('vouchers.index', ['campaigns' => $campaigns]);
    }

    public function lookup(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:100',
            'outlet_id' => 'nullable|integer|exists:outlets,id',
        ]);
        $outletId = $request->filled('outlet_id') ? OutletAccess::id($request, false) : null;
        $voucher = Voucher::with(['product', 'products', 'outlets'])
            ->where('code', strtoupper(trim($request->code)))
            ->when($outletId, fn ($query) => $query->where(function ($scopeQuery) use ($outletId) {
                $scopeQuery->where(function ($legacy) use ($outletId) {
                    $legacy->whereNull('outlet_id')->orWhere('outlet_id', $outletId);
                })->whereDoesntHave('outlets')
                    ->orWhereHas('outlets', fn ($outlets) => $outlets->whereKey($outletId));
            }))
            ->first();

        if (! $voucher || ! $voucher->isActive() || $voucher->redemptions()->exists()) {
            $promotionQuery = Promotion::with(['promotionProducts.product', 'bonuses', 'outlets'])
                ->where('code', strtoupper(trim($request->code)));
            if ($outletId) {
                $promotionQuery->activeFor($outletId);
            }
            $promotion = $promotionQuery->first();

            if ($promotion && $promotion->isActive()) {
                return response()->json($this->promotionPayload($promotion));
            }

            return response()->json(['message' => 'Voucher atau promo tidak ditemukan, sudah digunakan, atau tidak aktif.'], 404);
        }

        return response()->json($this->voucherPayload($voucher));
    }

    public function options(Request $request)
    {
        $outletId = OutletAccess::id($request, false);
        $now = now();

        $vouchers = Voucher::with(['product', 'products', 'outlets'])
            ->whereDoesntHave('redemptions')
            ->when($outletId, fn ($query) => $query->where(function ($scopeQuery) use ($outletId) {
                $scopeQuery->where(function ($legacy) use ($outletId) {
                    $legacy->whereNull('outlet_id')->orWhere('outlet_id', $outletId);
                })->whereDoesntHave('outlets')
                    ->orWhereHas('outlets', fn ($outlets) => $outlets->whereKey($outletId));
            }))
            ->where(function ($query) use ($now) {
                $query->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->orderBy('code')
            ->get()
            ->filter(fn (Voucher $voucher) => $voucher->isActive($now))
            ->values()
            ->map(fn (Voucher $voucher) => $this->voucherPayload($voucher))
            ->all();

        $promotions = $outletId
            ? Promotion::with(['promotionProducts.product', 'bonuses', 'outlets'])
                ->activeFor($outletId, $now)
                ->get()
                ->map(fn (Promotion $promotion) => $this->promotionPayload($promotion))
                ->values()
                ->all()
            : [];

        return response()->json([
            'vouchers' => $vouchers,
            'promotions' => $promotions,
        ]);
    }

    public function create()
    {
        $this->ensureManagementAccess();

        return redirect()->route('campaign.create', ['type' => 'voucher']);
    }

    public function store(VoucherRequest $request)
    {
        $data = $request->validated();
        [$startAt, $endAt] = $this->parseDateRange($request->input('daterange'));
        $baseCode = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($baseCode === '') {
            do {
                $baseCode = 'VCR-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2)));
            } while (Voucher::where('code', $baseCode)->exists());
        }
        $quantity = (int) ($data['quantity'] ?? 1);
        $codes = $this->generatedCodes($baseCode, $quantity);

        if (Voucher::whereIn('code', $codes)->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode voucher atau variannya sudah digunakan.']);
        }

        DB::transaction(function () use ($data, $codes, $startAt, $endAt) {
            foreach ($codes as $code) {
                $voucher = Voucher::create([
                    'name' => $data['name'],
                    'code' => $code,
                    'type' => $data['type'],
                    'jenis' => 'keseluruhan',
                    'limit' => 1,
                    'value' => $data['value'],
                    'min_purchase' => $data['min_purchase'] ?? 0,
                    'max_discount_amount' => $data['max_discount_amount'] ?? null,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'desc' => $data['desc'] ?? null,
                    'product_id' => empty($data['product_ids']) ? ($data['product_id'] ?? null) : null,
                    'kasir_id' => $data['kasir_id'] ?? null,
                    'outlet_id' => empty($data['outlet_ids']) ? ($data['outlet_id'] ?? null) : null,
                ]);

                $voucher->products()->sync($data['product_ids'] ?? []);
                $voucher->outlets()->sync($data['outlet_ids'] ?? []);
            }
        });

        return redirect()->route('voucher.index')->with('toast_success', "{$quantity} voucher berhasil dibuat.");
    }

    public function show(Voucher $voucher)
    {
        $this->ensureManagementAccess();
        $voucher->load(['product', 'products', 'outlet', 'outlets'])->loadCount('redemptions');
        $vouchers = Voucher::with(['product', 'products', 'outlet', 'outlets'])
            ->withCount('redemptions')
            ->get()
            ->filter(fn (Voucher $item) => $this->voucherGroupKey($item) === $this->voucherGroupKey($voucher))
            ->sortBy('code')
            ->values();

        return view('vouchers.show', ['voucher' => $voucher, 'vouchers' => $vouchers]);
    }

    public function edit(Voucher $voucher)
    {
        $this->ensureManagementAccess();

        return redirect()->route('campaign.edit', ['type' => 'voucher', 'id' => $voucher->id]);
    }

    public function update(VoucherRequest $request, Voucher $voucher)
    {
        $data = $request->validated();
        $code = strtoupper(trim((string) ($data['code'] ?? $voucher->code)));
        if (Voucher::where('code', $code)->where('id', '!=', $voucher->id)->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode voucher sudah digunakan.']);
        }
        [$startAt, $endAt] = $this->parseDateRange($request->input('daterange'));

        $voucher->update([
            'name' => $data['name'],
            'code' => $code,
            'type' => $data['type'],
            'value' => $data['value'],
            'min_purchase' => $data['min_purchase'] ?? 0,
            'max_discount_amount' => $data['max_discount_amount'] ?? null,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'desc' => $data['desc'] ?? null,
            'product_id' => empty($data['product_ids']) ? ($data['product_id'] ?? null) : null,
            'kasir_id' => $data['kasir_id'] ?? null,
            'outlet_id' => empty($data['outlet_ids']) ? ($data['outlet_id'] ?? null) : null,
        ]);

        $voucher->products()->sync($data['product_ids'] ?? []);
        $voucher->outlets()->sync($data['outlet_ids'] ?? []);

        return redirect()->route('voucher.index')->with('toast_success', 'Voucher berhasil diperbarui.');
    }

    public function destroy(Voucher $voucher)
    {
        $this->ensureManagementAccess();
        if ($voucher->redemptions()->exists()) {
            return redirect()->back()->with('toast_error', 'Voucher yang sudah digunakan tidak dapat dihapus.');
        }
        $voucher->delete();

        return redirect()->route('voucher.index')->with('toast_success', 'Voucher berhasil dihapus.');
    }

    private function generatedCodes(string $baseCode, int $quantity): array
    {
        if ($quantity === 1) {
            return [$baseCode];
        }

        return collect(range(1, $quantity))
            ->map(fn ($number) => $baseCode.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT))
            ->all();
    }

    private function groupVouchers($vouchers)
    {
        return $vouchers
            ->groupBy(fn (Voucher $voucher) => $this->voucherGroupKey($voucher))
            ->map(function ($group) {
                $voucher = $group->sortBy('code')->first();
                $voucher->setAttribute('voucher_group_items', $group->sortBy('code')->values());
                $voucher->setAttribute('voucher_group_count', $group->count());

                return $voucher;
            })
            ->values();
    }

    private function voucherGroupKey(Voucher $voucher): string
    {
        $baseCode = preg_replace('/-\d{3}$/', '', (string) $voucher->code);

        return implode('|', [
            $baseCode,
            $voucher->name,
            $voucher->type,
            $voucher->value,
            $voucher->min_purchase,
            $voucher->max_discount_amount,
            optional($voucher->start_at)->toIso8601String(),
            optional($voucher->end_at)->toIso8601String(),
            $voucher->outlet_id,
            $voucher->product_id,
        ]);
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

    private function voucherPayload(Voucher $voucher): array
    {
        return [
            'kind' => 'voucher',
            'id' => $voucher->id,
            'name' => $voucher->name,
            'code' => $voucher->code,
            'type' => $voucher->type,
            'value' => $voucher->value,
            'min_purchase' => $voucher->min_purchase,
            'max_discount_amount' => $voucher->max_discount_amount,
            'outlet_id' => $voucher->outlet_id,
            'product_id' => $voucher->product_id,
            'product_name' => $voucher->product?->name,
            'outlet_ids' => $voucher->outlets->pluck('id')->values()->all(),
            'outlet_names' => $voucher->outlets->pluck('name')->values()->all(),
            'product_ids' => $voucher->products->pluck('id')->values()->all(),
            'product_names' => $voucher->products->pluck('name')->values()->all(),
            'start_at' => $voucher->start_at,
            'end_at' => $voucher->end_at,
        ];
    }

    private function promotionPayload(Promotion $promotion): array
    {
        return [
            'kind' => 'promotion',
            'id' => $promotion->id,
            'name' => $promotion->name,
            'code' => $promotion->code,
            'type' => $promotion->type,
            'discount_type' => $promotion->discount_type,
            'discount_value' => $promotion->discount_value,
            'bundle_price' => $promotion->bundle_price,
            'bundle_discount' => $promotion->bundle_price,
            'max_qty' => $promotion->max_qty,
            'quota_qty' => $promotion->quota_qty,
            'min_purchase' => $promotion->min_purchase,
            'outlet_id' => $promotion->outlet_id,
            'start_at' => $promotion->start_at,
            'end_at' => $promotion->end_at,
            'products' => $promotion->promotionProducts->map(fn ($rule) => [
                'id' => $rule->product_id,
                'name' => $rule->product?->name,
                'required_qty' => (float) $rule->required_qty,
            ])->values()->all(),
            'bonuses' => $promotion->bonuses->map(fn ($bonus) => [
                'name' => $bonus->name,
                'qty' => (int) $bonus->qty,
            ])->values()->all(),
            'outlet_ids' => $promotion->outlets->pluck('id')->values()->all(),
            'outlet_names' => $promotion->outlets->pluck('name')->values()->all(),
        ];
    }
}
