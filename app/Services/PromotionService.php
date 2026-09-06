<?php

namespace App\Services;

use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PromotionService
{
    public function activeForOutlet(int $outletId, ?Carbon $at = null, bool $lock = false): Collection
    {
        $query = Promotion::with('promotionProducts.product')
            ->activeFor($outletId, $at);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * Apply cashier-selected promotions to already-priced POS allocations.
     *
     * Promotions are never applied just because they are active. The cashier
     * must select their codes in the POS before they are included here.
     */
    public function calculate(
        array $allocations,
        int $outletId,
        ?Carbon $at = null,
        bool $lock = false,
        array $promotionCodes = []
    ): array
    {
        $promotions = $this->selectedForOutlet($outletId, $promotionCodes, $at, $lock);

        return $this->apply($allocations, $promotions, $lock);
    }

    public function selectedForOutlet(int $outletId, array $promotionCodes, ?Carbon $at = null, bool $lock = false): Collection
    {
        $codes = collect($promotionCodes)
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return collect();
        }

        $query = Promotion::with('promotionProducts.product')
            ->activeFor($outletId, $at)
            ->whereIn('code', $codes->all());

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    public function apply(array $allocations, Collection $promotions, bool $persistUsage = false): array
    {
        $availableQty = [];
        foreach ($allocations as $index => &$allocation) {
            $allocation['base_line_total'] = (int) ($allocation['base_line_total'] ?? $allocation['line_total'] ?? 0);
            $allocation['line_total'] = (int) ($allocation['line_total'] ?? $allocation['base_line_total']);
            $allocation['promotion_discount'] = (int) ($allocation['promotion_discount'] ?? 0);
            $allocation['promotion_details'] = $allocation['promotion_details'] ?? [];
            $availableQty[$index] = (int) $allocation['qty'];
        }
        unset($allocation);

        $applications = [];
        foreach ($promotions as $promotion) {
            // Keep a local availability map so a stackable promotion can be
            // applied after another promotion without reusing units twice
            // inside the same promotion.
            $promotionAvailableQty = $availableQty;
            $result = match ($this->normalizedType($promotion->type)) {
                'flash_sale' => $this->applyFlashSale($allocations, $promotionAvailableQty, $promotion),
                'bundle' => $this->applyBundle($allocations, $promotionAvailableQty, $promotion),
                default => ['amount' => 0, 'basis' => 0, 'quantity' => 0, 'details' => []],
            };

            if ($result['amount'] <= 0) {
                continue;
            }

            if (! $promotion->stackable) {
                $availableQty = $promotionAvailableQty;
            }

            if ($persistUsage) {
                $promotion->increment('used_qty', (int) $result['quantity']);
            }

            $applications[] = [
                'promotion_id' => $promotion->id,
                'type' => $promotion->type,
                'name' => $promotion->name,
                'code' => $promotion->code,
                'basis_amount' => $result['basis'],
                'amount' => $result['amount'],
                'quantity' => $result['quantity'],
                'details' => $result['details'],
            ];
        }

        return [
            'allocations' => array_values($allocations),
            'base_subtotal' => array_sum(array_column($allocations, 'base_line_total')),
            'subtotal' => array_sum(array_column($allocations, 'line_total')),
            'promotion_total' => array_sum(array_column($allocations, 'promotion_discount')),
            'applications' => $applications,
        ];
    }

    private function applyFlashSale(array &$allocations, array &$availableQty, Promotion $promotion): array
    {
        $amount = 0;
        $basis = 0;
        $quantity = 0;
        $details = [];
        $quotaRemaining = $promotion->quota_qty === null
            ? PHP_INT_MAX
            : max(0, (int) $promotion->quota_qty - (int) $promotion->used_qty);

        foreach ($promotion->promotionProducts as $target) {
            if ($quotaRemaining <= 0) {
                break;
            }

            $targetLimit = $promotion->max_qty === null
                ? PHP_INT_MAX
                : (int) $promotion->max_qty;
            foreach ($allocations as $index => &$allocation) {
                if ($targetLimit <= 0 || $quotaRemaining <= 0 || $availableQty[$index] <= 0) {
                    continue;
                }
                if ((int) $allocation['product']->id !== (int) $target->product_id) {
                    continue;
                }

                $qty = min($availableQty[$index], $targetLimit, $quotaRemaining);
                $unitPrice = (int) $allocation['price']['price'];
                $discountPerUnit = $this->flashDiscountPerUnit($unitPrice, $promotion);
                if ($discountPerUnit <= 0) {
                    continue;
                }

                $lineDiscount = $discountPerUnit * $qty;
                $this->applyLineDiscount($allocation, $availableQty, $index, $qty, $lineDiscount, $promotion);
                $amount += $lineDiscount;
                $basis += $unitPrice * $qty;
                $quantity += $qty;
                $targetLimit -= $qty;
                $quotaRemaining -= $qty;
                $details[] = [
                    'product_id' => $target->product_id,
                    'quantity' => $qty,
                    'basis_amount' => $unitPrice * $qty,
                    'amount' => $lineDiscount,
                ];
            }
            unset($allocation);
        }

        return compact('amount', 'basis', 'quantity', 'details');
    }

    private function applyBundle(array &$allocations, array &$availableQty, Promotion $promotion): array
    {
        $amount = 0;
        $basis = 0;
        $quantity = 0;
        $details = [];
        $bundleCount = $this->bundleCount($allocations, $availableQty, $promotion);
        if ($promotion->max_qty !== null) {
            $bundleCount = min($bundleCount, (int) $promotion->max_qty);
        }
        if ($promotion->quota_qty !== null) {
            $bundleCount = min($bundleCount, max(0, (int) $promotion->quota_qty - (int) $promotion->used_qty));
        }

        for ($bundleNumber = 0; $bundleNumber < $bundleCount; $bundleNumber++) {
            $bundleLines = [];
            foreach ($promotion->promotionProducts as $target) {
                $remaining = (int) ceil((float) $target->required_qty);
                foreach ($allocations as $index => $allocation) {
                    if ($remaining <= 0 || $availableQty[$index] <= 0) {
                        continue;
                    }
                    if ((int) $allocation['product']->id !== (int) $target->product_id) {
                        continue;
                    }

                    $qty = min($availableQty[$index], $remaining);
                    $bundleLines[] = [
                        'index' => $index,
                        'qty' => $qty,
                        'base' => (int) $allocation['price']['price'] * $qty,
                    ];
                    $remaining -= $qty;
                }
                if ($remaining > 0) {
                    $bundleLines = [];
                    break 2;
                }
            }

            $bundleBasis = array_sum(array_column($bundleLines, 'base'));
            // For a bundle, bundle_price stores the nominal discount for each
            // bundle. It is intentionally capped at the bundle's normal
            // subtotal so a promotion can never produce a negative total.
            $bundleDiscount = min($bundleBasis, $this->money($promotion->bundle_price));
            $bundlePrice = max(0, $bundleBasis - $bundleDiscount);
            if ($bundleDiscount <= 0 || $bundleBasis <= 0) {
                break;
            }

            $remainingDiscount = $bundleDiscount;
            foreach ($bundleLines as $position => $line) {
                $lineDiscount = $position === count($bundleLines) - 1
                    ? $remainingDiscount
                    : (int) round($bundleDiscount * $line['base'] / $bundleBasis, 0, PHP_ROUND_HALF_UP);
                $lineDiscount = min($line['base'], max(0, $lineDiscount));
                $this->applyLineDiscount(
                    $allocations[$line['index']],
                    $availableQty,
                    $line['index'],
                    $line['qty'],
                    $lineDiscount,
                    $promotion
                );
                $remainingDiscount -= $lineDiscount;
            }

            $amount += $bundleDiscount;
            $basis += $bundleBasis;
            $quantity++;
            $details[] = [
                'bundle_number' => $bundleNumber + 1,
                'products' => $promotion->promotionProducts->map(fn ($target) => [
                    'product_id' => $target->product_id,
                    'required_qty' => (float) $target->required_qty,
                ])->values()->all(),
                'basis_amount' => $bundleBasis,
                'bundle_discount' => $bundleDiscount,
                'customer_total' => $bundlePrice,
                'amount' => $bundleDiscount,
            ];
        }

        return compact('amount', 'basis', 'quantity', 'details');
    }

    private function bundleCount(array $allocations, array $availableQty, Promotion $promotion): int
    {
        $counts = [];
        foreach ($promotion->promotionProducts as $target) {
            $available = 0;
            foreach ($allocations as $index => $allocation) {
                if ((int) $allocation['product']->id === (int) $target->product_id) {
                    $available += $availableQty[$index];
                }
            }
            $required = max(1, (float) $target->required_qty);
            $counts[] = (int) floor($available / $required);
        }

        return empty($counts) ? 0 : min($counts);
    }

    private function flashDiscountPerUnit(int $unitPrice, Promotion $promotion): int
    {
        $value = max(0, (float) $promotion->discount_value);

        return match ($this->normalizedType($promotion->discount_type)) {
            'percentage' => min($unitPrice, (int) round($unitPrice * min(100, $value) / 100, 0, PHP_ROUND_HALF_UP)),
            'fixed_price' => max(0, $unitPrice - (int) round($value, 0, PHP_ROUND_HALF_UP)),
            default => min($unitPrice, (int) round($value, 0, PHP_ROUND_HALF_UP)),
        };
    }

    private function normalizedType(?string $type): string
    {
        return strtolower(trim((string) $type));
    }

    private function money(float|int|null $value): int
    {
        return (int) round((float) ($value ?? 0), 0, PHP_ROUND_HALF_UP);
    }

    private function applyLineDiscount(
        array &$allocation,
        array &$availableQty,
        int $index,
        int $qty,
        int $amount,
        Promotion $promotion
    ): void {
        if ($qty <= 0 || $amount <= 0) {
            return;
        }

        $allocation['line_total'] = max(0, (int) $allocation['line_total'] - $amount);
        $allocation['promotion_discount'] += $amount;
        $allocation['promotion_details'][] = [
            'promotion_id' => $promotion->id,
            'promotion_name' => $promotion->name,
            'promotion_code' => $promotion->code,
            'quantity' => $qty,
            'amount' => $amount,
        ];
        $availableQty[$index] = max(0, $availableQty[$index] - $qty);
    }
}
