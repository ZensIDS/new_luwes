<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductMinimumAdjustment;
use Illuminate\Http\Request;

class ProductMinimumAdjustmentController extends Controller
{
    /**
     * Daftar produk untuk modal "Pengaturan Min Stok" di dashboard.
     * Dimuat lewat AJAX saat modal pertama kali dibuka (bukan di setiap load dashboard).
     */
    public function products()
    {
        abort_unless(in_array(auth()->user()?->role, ['admin-gudang', 'superadmin'], true), 403);

        $activeAdjustments = ProductMinimumAdjustment::query()
            ->activeOn()
            ->orderByDesc('active_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id');

        $rows = Product::select('id', 'code', 'name', 'min_stock')
            ->withSum('stocks', 'qty')
            ->orderBy('name')
            ->get()
            ->map(function ($p) use ($activeAdjustments) {
                $adj = $activeAdjustments->get($p->id)?->first();
                $effectiveMin = $adj
                    ? (int) ceil($p->min_stock * (1 + $adj->adjustment_percentage / 100))
                    : (int) $p->min_stock;

                return [
                    'id'            => $p->id,
                    'code'          => $p->code,
                    'name'          => $p->name,
                    'current_stock' => (int) ($p->stocks_sum_qty ?? 0),
                    'min_stock'     => $p->min_stock,
                    'effective_min' => $effectiveMin,
                    'active_from'   => $adj?->active_from?->format('d M Y'),
                    'active_until'  => $adj?->active_until?->format('d M Y'),
                ];
            });

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_ids'           => 'required|array|min:1',
            'product_ids.*'         => 'integer|exists:products,id',
            'adjustment_percentage' => 'required|integer|min:1|max:255',
            'active_from'           => 'required|date',
            'active_until'          => 'nullable|date|after_or_equal:active_from',
        ], [
            'product_ids.required' => 'Produk harus dipilih.',
            'product_ids.array' => 'Produk harus berupa array.',
            'product_ids.min' => 'Minimal pilih 1 produk.',
            'product_ids.*.integer' => 'ID produk harus berupa angka.',
            'product_ids.*.exists' => 'Produk yang dipilih tidak ditemukan.',
            'adjustment_percentage.required' => 'Persentase penyesuaian harus diisi.',
            'adjustment_percentage.integer' => 'Persentase penyesuaian harus berupa angka.',
            'adjustment_percentage.min' => 'Persentase penyesuaian minimal 1.',
            'adjustment_percentage.max' => 'Persentase penyesuaian maksimal 255.',
            'active_from.required' => 'Tanggal mulai berlaku harus diisi.',
            'active_from.date' => 'Tanggal mulai berlaku harus berupa tanggal yang valid.',
            'active_until.date' => 'Tanggal berakhir harus berupa tanggal yang valid.',
            'active_until.after_or_equal' => 'Tanggal berakhir harus sama atau setelah tanggal mulai berlaku.',
        ]);

        $activeUntilBound = $data['active_until'] ?? '9999-12-31';

        // Collect product IDs that already have an overlapping adjustment.
        // Satu query untuk semua produk (sebelumnya exists() per produk).
        $overlappingSet = ProductMinimumAdjustment::whereIn('product_id', $data['product_ids'])
            ->where('active_from', '<=', $activeUntilBound)
            ->where(function ($q) use ($data) {
                $q->whereNull('active_until')
                    ->orWhere('active_until', '>=', $data['active_from']);
            })
            ->distinct()
            ->pluck('product_id')
            ->flip();

        $skippedIds = [];
        foreach ($data['product_ids'] as $productId) {
            if ($overlappingSet->has((int) $productId)) {
                $skippedIds[] = $productId;
            }
        }

        $skippedSet = array_flip($skippedIds);
        $saved = 0;
        foreach ($data['product_ids'] as $productId) {
            if (isset($skippedSet[$productId])) {
                continue;
            }

            ProductMinimumAdjustment::create([
                'product_id'            => $productId,
                'adjustment_percentage' => $data['adjustment_percentage'],
                'active_from'           => $data['active_from'],
                'active_until'          => $data['active_until'] ?? null,
                'created_by'            => auth()->id(),
            ]);
            $saved++;
        }

        return response()->json([
            'success'     => true,
            'message'     => "Adjustment disimpan untuk {$saved} produk.",
            'skipped'     => count($skippedIds),
            'skipped_ids' => $skippedIds,
        ]);
    }
}