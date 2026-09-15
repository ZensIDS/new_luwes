<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromotionRequest;
use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PromotionController extends Controller
{
    public function index()
    {
        $this->ensureManagementAccess();

        return redirect()->route('voucher.index');
    }

    public function create()
    {
        $this->ensureManagementAccess();

        return redirect()->route('campaign.create', ['type' => 'flash_sale']);
    }

    public function store(PromotionRequest $request)
    {
        $this->ensureManagementAccess();
        $data = $request->validated();
        [$startAt, $endAt] = $this->parseDateRange($request->input('daterange'));

        if (empty($data['code'])) {
            do {
                $data['code'] = 'PROMO-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2)));
            } while (Promotion::where('code', $data['code'])->exists());
        }

        DB::transaction(function () use ($data, $startAt, $endAt) {
            $promotion = Promotion::create($this->promotionData($data, $startAt, $endAt));
            $this->syncProducts($promotion, $data['products']);
            $this->syncOutlets($promotion, $data['outlet_ids'] ?? []);
            $this->syncBonuses($promotion, $data['bonuses'] ?? []);
        });

        return redirect()->route('voucher.index')->with('toast_success', 'Promo berhasil dibuat.');
    }

    public function edit(Promotion $promotion)
    {
        $this->ensureManagementAccess();

        return redirect()->route('campaign.edit', ['type' => 'promotion', 'id' => $promotion->id]);
    }

    public function update(PromotionRequest $request, Promotion $promotion)
    {
        $this->ensureManagementAccess();
        $data = $request->validated();
        [$startAt, $endAt] = $this->parseDateRange($request->input('daterange'));

        if (empty($data['code'])) {
            $data['code'] = $promotion->code ?: 'PROMO-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2)));
        }

        DB::transaction(function () use ($data, $startAt, $endAt, $promotion) {
            $promotion->update($this->promotionData($data, $startAt, $endAt));
            $this->syncProducts($promotion, $data['products']);
            $this->syncOutlets($promotion, $data['outlet_ids'] ?? []);
            $this->syncBonuses($promotion, $data['bonuses'] ?? []);
        });

        return redirect()->route('voucher.index')->with('toast_success', 'Promo berhasil diperbarui.');
    }

    public function destroy(Promotion $promotion)
    {
        $this->ensureManagementAccess();
        if ($promotion->applications()->exists()) {
            return redirect()->back()->with('toast_error', 'Promo yang sudah dipakai tidak dapat dihapus. Nonaktifkan promo tersebut.');
        }

        $promotion->delete();

        return redirect()->route('voucher.index')->with('toast_success', 'Promo berhasil dihapus.');
    }

    private function promotionData(array $data, ?Carbon $startAt, ?Carbon $endAt): array
    {
        return [
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'type' => $data['type'],
            'discount_type' => $data['type'] === 'bundle' ? 'nominal' : ($data['discount_type'] ?? 'percentage'),
            'discount_value' => $data['type'] === 'bundle' ? ($data['bundle_price'] ?? 0) : ($data['discount_value'] ?? 0),
            'bundle_price' => $data['bundle_price'] ?? null,
            'max_qty' => $data['max_qty'] ?? null,
            'quota_qty' => $data['quota_qty'] ?? null,
            'min_purchase' => $data['min_purchase'] ?? 0,
            'outlet_id' => empty($data['outlet_ids'] ?? []) ? ($data['outlet_id'] ?? null) : null,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'priority' => 100,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'stackable' => (bool) ($data['stackable'] ?? false),
            'desc' => $data['desc'] ?? null,
            'created_by' => auth()->id(),
        ];
    }

    private function syncProducts(Promotion $promotion, array $products): void
    {
        $promotion->promotionProducts()->delete();
        foreach ($products as $productId => $requiredQty) {
            $promotion->promotionProducts()->create([
                'product_id' => (int) $productId,
                'required_qty' => $requiredQty,
            ]);
        }
    }

    private function syncOutlets(Promotion $promotion, array $outlets): void
    {
        $promotion->outlets()->sync($outlets);
    }

    private function syncBonuses(Promotion $promotion, array $bonuses): void
    {
        $promotion->bonuses()->delete();
        foreach ($bonuses as $bonus) {
            $promotion->bonuses()->create([
                'name' => $bonus['name'],
                'qty' => $bonus['qty'],
            ]);
        }
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
