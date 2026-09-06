<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromotionRequest;
use App\Models\Product;
use App\Models\Promotion;
use App\Support\OutletAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromotionController extends Controller
{
    public function index()
    {
        $this->ensureManagementAccess();

        return view('promotions.index', [
            'promotions' => Promotion::with(['outlet', 'products'])
                ->latest()
                ->paginate(50),
        ]);
    }

    public function create()
    {
        $this->ensureManagementAccess();

        return view('promotions.form', $this->formData(new Promotion([
            'type' => 'flash_sale',
            'discount_type' => 'percentage',
            'discount_value' => 0,
            'is_active' => true,
            'stackable' => false,
            'priority' => 100,
        ]), false));
    }

    public function store(PromotionRequest $request)
    {
        $this->ensureManagementAccess();
        $data = $request->validated();
        [$startAt, $endAt] = $this->parseDateRange($request->input('daterange'));

        DB::transaction(function () use ($data, $startAt, $endAt) {
            $promotion = Promotion::create($this->promotionData($data, $startAt, $endAt));
            $this->syncProducts($promotion, $data['products']);
        });

        return redirect()->route('promotion.index')->with('toast_success', 'Promo berhasil dibuat.');
    }

    public function edit(Promotion $promotion)
    {
        $this->ensureManagementAccess();

        return view('promotions.form', $this->formData($promotion->load('promotionProducts'), true));
    }

    public function update(PromotionRequest $request, Promotion $promotion)
    {
        $this->ensureManagementAccess();
        $data = $request->validated();
        [$startAt, $endAt] = $this->parseDateRange($request->input('daterange'));

        DB::transaction(function () use ($data, $startAt, $endAt, $promotion) {
            $promotion->update($this->promotionData($data, $startAt, $endAt));
            $this->syncProducts($promotion, $data['products']);
        });

        return redirect()->route('promotion.index')->with('toast_success', 'Promo berhasil diperbarui.');
    }

    public function destroy(Promotion $promotion)
    {
        $this->ensureManagementAccess();
        if ($promotion->applications()->exists()) {
            return redirect()->back()->with('toast_error', 'Promo yang sudah dipakai tidak dapat dihapus. Nonaktifkan promo tersebut.');
        }

        $promotion->delete();

        return redirect()->route('promotion.index')->with('toast_success', 'Promo berhasil dihapus.');
    }

    private function formData(Promotion $promotion, bool $isEdit): array
    {
        $selectedProducts = $promotion->relationLoaded('promotionProducts')
            ? $promotion->promotionProducts->pluck('required_qty', 'product_id')->all()
            : [];

        return [
            'promotion' => $promotion,
            'selectedProducts' => $selectedProducts,
            'products' => Product::orderBy('name')->get(['id', 'code', 'name']),
            'outlets' => OutletAccess::outlets(),
            'isEdit' => $isEdit,
        ];
    }

    private function promotionData(array $data, ?Carbon $startAt, ?Carbon $endAt): array
    {
        return [
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'type' => $data['type'],
            'discount_type' => $data['type'] === 'bundle' ? 'nominal' : $data['discount_type'],
            'discount_value' => $data['type'] === 'bundle' ? ($data['bundle_price'] ?? 0) : ($data['discount_value'] ?? 0),
            'bundle_price' => $data['bundle_price'] ?? null,
            'max_qty' => $data['max_qty'] ?? null,
            'quota_qty' => $data['quota_qty'] ?? null,
            'min_purchase' => $data['min_purchase'] ?? 0,
            'outlet_id' => $data['outlet_id'] ?? null,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'priority' => $data['priority'] ?? 100,
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
