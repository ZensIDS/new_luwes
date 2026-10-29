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

        // Halaman hanya render shell tabel; data diambil server-side lewat getIndexData().
        return view('vouchers.index');
    }

    /**
     * Server-side DataTables untuk daftar Voucher & Rafaksi (tab "voucher") dan Bundling (tab "bundle").
     *
     * Voucher dikelompokkan per kampanye (kode dasar tanpa suffix -001, nama, jenis, nilai, masa berlaku, dst)
     * LANGSUNG di SQL, lalu digabung (UNION) dengan promo rafaksi. Hanya satu halaman (maks 100 baris)
     * yang dimuat relasinya, jadi puluhan ribu kode voucher tetap ringan.
     *
     * Search berbasis AWALAN (LIKE 'kata%'): nama, kode, serta nama/kode produk.
     */
    public function getIndexData(Request $request)
    {
        $this->ensureManagementAccess();

        $tab = $request->input('tab') === 'bundle' ? 'bundle' : 'voucher';
        $draw = (int) $request->input('draw');
        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 25);
        $length = $length > 0 ? min($length, 100) : 25;
        $searchValue = trim((string) $request->input('search.value', ''));
        $prefix = $searchValue !== '' ? addcslashes($searchValue, '\\%_') . '%' : null;

        $hasOrder = $request->has('order.0.column');
        $orderDir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = [2 => 'c.name', 7 => 'c.start_at'];
        $orderBy = $hasOrder ? ($sortable[(int) $request->input('order.0.column')] ?? 'c.created_at') : 'c.created_at';
        if (! $hasOrder) {
            $orderDir = 'desc';
        }

        // Daftar ID per kelompok bisa panjang (ribuan kode voucher).
        DB::statement('SET SESSION group_concat_max_len = 1048576');

        $recordsTotal = DB::query()->fromSub($this->campaignUnion($tab, null), 'c')->count();
        $recordsFiltered = $prefix === null
            ? $recordsTotal
            : DB::query()->fromSub($this->campaignUnion($tab, $prefix), 'c')->count();

        $rows = DB::query()->fromSub($this->campaignUnion($tab, $prefix), 'c')
            ->orderBy($orderBy, $orderDir)
            // Tie-breaker unik agar urutan stabil antar halaman.
            ->orderBy('c.kind')
            ->orderBy('c.ids')
            ->offset($start)
            ->limit($length)
            ->get();

        $voucherIds = [];
        $promotionIds = [];
        foreach ($rows as $row) {
            $first = (int) explode(',', $row->ids)[0];
            if ($row->kind === 'voucher') {
                $voucherIds[] = $first;
            } else {
                $promotionIds[] = $first;
            }
        }

        $vouchers = $voucherIds
            ? Voucher::with(['product', 'products', 'outlet', 'outlets'])->whereIn('id', $voucherIds)->get()->keyBy('id')
            : collect();
        $promotions = $promotionIds
            ? Promotion::with(['outlet', 'outlets', 'products', 'bonuses'])->whereIn('id', $promotionIds)->get()->keyBy('id')
            : collect();

        $data = [];
        foreach ($rows as $row) {
            $ids = explode(',', $row->ids);
            $first = (int) $ids[0];
            $cells = $row->kind === 'voucher'
                ? $this->voucherRow($row, $ids, $vouchers->get($first))
                : $this->promotionRow($promotions->get($first));

            if ($cells) {
                $data[] = $cells;
            }
        }

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * Gabungan kelompok voucher (di-GROUP BY di SQL) + promo, dengan kolom seragam:
     * kind, ids, created_at, name, base_code, start_at, voucher_count, redeemed_count.
     * Tab "bundle" hanya berisi promo bundle.
     */
    private function campaignUnion(string $tab, ?string $prefix)
    {
        $productMatch = function ($sub) use ($prefix) {
            $sub->where('p.name', 'like', $prefix)->orWhere('p.code', 'like', $prefix);
        };

        $promotions = DB::table('promotions as pr')
            ->whereNull('pr.deleted_at')
            ->where('pr.type', $tab === 'bundle' ? 'bundle' : 'flash_sale')
            ->when($prefix !== null, fn ($query) => $query->where(function ($w) use ($prefix, $productMatch) {
                $w->where('pr.name', 'like', $prefix)
                    ->orWhere('pr.code', 'like', $prefix)
                    ->orWhereIn('pr.id', function ($sub) use ($productMatch) {
                        $sub->select('pp.promotion_id')
                            ->from('promotion_products as pp')
                            ->join('products as p', 'p.id', '=', 'pp.product_id')
                            ->whereNull('p.deleted_at')
                            ->where($productMatch);
                    });
            }))
            ->selectRaw("'promotion' as kind, CAST(pr.id AS CHAR) as ids, pr.created_at as created_at, pr.name as name, pr.code as base_code, pr.start_at as start_at, NULL as voucher_count, NULL as redeemed_count");

        if ($tab === 'bundle') {
            return $promotions;
        }

        $baseCode = 'CASE WHEN v.code REGEXP \'-[0-9]{3}$\' THEN LEFT(v.code, CHAR_LENGTH(v.code) - 4) ELSE v.code END';

        $vouchers = DB::table('vouchers as v')
            ->leftJoin('voucher_redemptions as vr', 'vr.voucher_id', '=', 'v.id')
            ->whereNull('v.deleted_at')
            ->when($prefix !== null, fn ($query) => $query->where(function ($w) use ($prefix, $productMatch) {
                $w->where('v.name', 'like', $prefix)
                    ->orWhere('v.code', 'like', $prefix)
                    ->orWhereIn('v.product_id', function ($sub) use ($productMatch) {
                        $sub->select('p.id')->from('products as p')->whereNull('p.deleted_at')->where($productMatch);
                    })
                    ->orWhereIn('v.id', function ($sub) use ($productMatch) {
                        $sub->select('vp.voucher_id')
                            ->from('voucher_products as vp')
                            ->join('products as p', 'p.id', '=', 'vp.product_id')
                            ->whereNull('p.deleted_at')
                            ->where($productMatch);
                    });
            }))
            ->selectRaw("'voucher' as kind, GROUP_CONCAT(v.id ORDER BY v.code SEPARATOR ',') as ids, MAX(v.created_at) as created_at, v.name as name, {$baseCode} as base_code, v.start_at as start_at, COUNT(DISTINCT v.id) as voucher_count, COUNT(vr.id) as redeemed_count")
            ->groupByRaw("{$baseCode}, v.name, v.type, v.value, v.min_purchase, v.max_discount_amount, v.start_at, v.end_at, v.outlet_id, v.product_id");

        return $vouchers->unionAll($promotions);
    }

    private function money($value): string
    {
        return 'Rp. ' . number_format((float) $value, 0, ',', '.');
    }

    private function voucherRow(object $row, array $ids, ?Voucher $voucher): ?array
    {
        if (! $voucher) {
            return null;
        }

        $count = (int) $row->voucher_count;
        $redeemed = (int) $row->redeemed_count;
        $productNames = $voucher->products->isNotEmpty()
            ? $voucher->products->pluck('name')->join(', ')
            : ($voucher->product?->name ?? 'Semua produk');
        $outletNames = $voucher->outlets->isNotEmpty()
            ? $voucher->outlets->pluck('name')->join(', ')
            : ($voucher->outlet?->name ?? 'Semua outlet');
        $status = $redeemed ? 'Sudah dipakai' : ($voucher->isActive() ? 'Aktif' : 'Tidak aktif');
        $baseCode = $row->base_code;

        $checkbox = filled($baseCode)
            ? '<input type="checkbox" class="voucher-print-checkbox" data-campaign-type="voucher"'
                . ' data-voucher-ids="' . e(implode(',', $ids)) . '" form="voucher-label-print-form"'
                . ' aria-label="Pilih voucher ' . e($voucher->name) . '">'
            : '';

        $nameHtml = '<strong>' . e($voucher->name) . '</strong>';
        if (filled($baseCode)) {
            $nameHtml .= '<br><small>' . e($baseCode) . '</small>';
        }
        if ($count > 1) {
            $nameHtml .= '<br><small class="text-muted">' . $count . ' kode voucher</small>';
        }

        $discount = $voucher->type === 'percentage' ? e($voucher->value) . '%' : $this->money($voucher->value);

        $aksi = '<a class="btn btn-warning btn-sm" href="' . e(route('campaign.edit', ['type' => 'voucher', 'id' => $voucher->id])) . '">Edit</a> '
            . '<a class="btn btn-info btn-sm" href="' . e(route('voucher.show', $voucher)) . '">Lihat</a> '
            . '<form action="' . e(route('voucher.destroy', $voucher)) . '" method="POST" style="display:inline">'
            . csrf_field() . method_field('DELETE')
            . '<button class="btn btn-danger btn-sm" onclick="return confirm(\'Hapus voucher ini?\')">Hapus</button></form>';

        return [
            $checkbox,
            '',
            $nameHtml,
            'Voucher',
            e($productNames),
            $discount,
            $redeemed . '/' . $count . ' pemakaian',
            $this->periodHtml($voucher),
            e($outletNames),
            $status,
            $aksi,
        ];
    }

    private function promotionRow(?Promotion $promotion): ?array
    {
        if (! $promotion) {
            return null;
        }

        $isRafaksi = $promotion->type === 'flash_sale';
        $productNames = $promotion->products->isNotEmpty() ? $promotion->products->pluck('name')->join(', ') : '—';
        $outletNames = $promotion->outlets->isNotEmpty()
            ? $promotion->outlets->pluck('name')->join(', ')
            : ($promotion->outlet?->name ?? 'Semua outlet');
        $status = $promotion->isActive() ? 'Aktif' : ($promotion->is_active ? 'Tidak aktif' : 'Nonaktif');

        $checkbox = filled($promotion->code)
            ? '<input type="checkbox" class="voucher-print-checkbox" data-campaign-type="promotion"'
                . ' data-voucher-ids="' . $promotion->id . '" form="voucher-label-print-form"'
                . ' aria-label="Pilih promo ' . e($promotion->name) . '"'
                . ($isRafaksi ? ' disabled title="Promo Rafaksi tidak dapat dicetak sebagai voucher"' : '') . '>'
            : '';

        $nameHtml = '<strong>' . e($promotion->name) . '</strong>';
        if ($promotion->code) {
            $nameHtml .= '<br><small>' . e(preg_replace('/-\d{3}$/', '', $promotion->code)) . '</small>';
        }

        if ($isRafaksi) {
            $discount = $promotion->discount_type === 'percentage'
                ? e($promotion->discount_value) . '%'
                : $this->money($promotion->discount_value);
        } else {
            $discount = $promotion->bundle_price > 0 ? 'Hemat ' . $this->money($promotion->bundle_price) : 'Bonus saja';
            if ($promotion->bonuses->isNotEmpty()) {
                $discount .= '<br><small>Bonus: ' . e($promotion->bonuses->pluck('name')->join(', ')) . '</small>';
            }
        }

        $aksi = '<a class="btn btn-warning btn-sm" href="' . e(route('campaign.edit', ['type' => 'promotion', 'id' => $promotion->id])) . '">Edit</a> '
            . '<form action="' . e(route('promotion.destroy', $promotion)) . '" method="POST" style="display:inline">'
            . csrf_field() . method_field('DELETE')
            . '<button class="btn btn-danger btn-sm" onclick="return confirm(\'Hapus promo ini?\')">Hapus</button></form>';

        return [
            $checkbox,
            '',
            $nameHtml,
            $isRafaksi ? 'Rafaksi' : 'Bundle + Bonus',
            e($productNames),
            $discount,
            e($promotion->used_qty . ($promotion->quota_qty ? '/' . $promotion->quota_qty : '')) . ' pemakaian',
            $this->periodHtml($promotion),
            e($outletNames),
            $status,
            $aksi,
        ];
    }

    private function periodHtml($model): string
    {
        return ($model->start_at?->format('d/m/Y H:i') ?? '—') . '<br>' . ($model->end_at?->format('d/m/Y H:i') ?? '—');
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
        $baseCode = Voucher::withCodePrefix((string) ($data['code'] ?? ''));
        if ($baseCode === '') {
            do {
                $baseCode = Voucher::CODE_PREFIX.'-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2)));
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
        if ($code !== strtoupper(trim((string) $voucher->code))) {
            $code = Voucher::withCodePrefix($code);
        }
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
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'admin-gudang', 'owner', 'staff-outlet'], true), 403);
    }

    private function voucherPayload(Voucher $voucher): array
    {
        return [
            'kind' => 'voucher',
            'id' => $voucher->id,
            'name' => $voucher->name,
            'code' => $voucher->code,
            'barcode' => $voucher->barcode,
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