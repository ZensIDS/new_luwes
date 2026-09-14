<?php

namespace App\Services;

use App\Models\OutletPrice;
use App\Models\Product;
use App\Models\Voucher;

class PriceCalculator
{
    public function calculateItem(float $hpp, ?OutletPrice $rule, ?Product $product = null): array
    {
        $hpp = $this->money($hpp);

        if (! $rule) {
            $active = $this->money($product?->harga_jual ?? $hpp);
            $margin = max(0, $active - $hpp);

            return [
                'hpp' => $hpp,
                'harga_akhir' => $hpp,
                'disc_brand_type' => 'nominal',
                'disc_brand_value' => 0,
                'disc_brand_amount' => 0,
                'disc_tambahan_type' => 'nominal',
                'disc_tambahan_value' => 0,
                'disc_tambahan_amount' => 0,
                'margin_type' => 'nominal',
                'margin_value' => $margin,
                'margin_amount' => $margin,
                'harga_aktif' => $active,
                'disc_toko_type' => 'nominal',
                'disc_toko_value' => 0,
                'disc_toko_amount' => 0,
                'price' => $active,
            ];
        }

        $brandAmount = $this->discountAmount($hpp, $rule->disc_brand_type, $rule->disc_brand_value);
        $hargaAkhir = max(0, $hpp - $brandAmount);

        // New price rules use the additional discount before margin. The
        // legacy disc_toko fields are still supported for old rules so old
        // price snapshots continue to calculate exactly as before.
        $hasAdditionalDiscount = $rule->disc_tambahan_type !== null;
        $additionalType = $hasAdditionalDiscount
            ? $rule->disc_tambahan_type
            : 'nominal';
        $additionalValue = $hasAdditionalDiscount
            ? (float) ($rule->disc_tambahan_value ?? 0)
            : 0;
        $additionalAmount = $hasAdditionalDiscount
            ? $this->discountAmount($hargaAkhir, $additionalType, $additionalValue)
            : 0;
        $marginBase = max(0, $hargaAkhir - $additionalAmount);
        $marginAmount = $this->isPercentage($rule->margin_type)
            ? $this->money($marginBase * ((float) $rule->margin_value / 100))
            : $this->money($rule->margin_value);
        $hargaAktif = $this->money($marginBase + $marginAmount);

        $legacyStoreDiscount = $hasAdditionalDiscount
            ? 0
            : $this->discountAmount($hargaAktif, $rule->disc_toko_type, $rule->disc_toko_value);
        $beautySurcharge = $this->isBeautyOutlet($rule)
            ? 100
            : 0;
        $hargaJual = $this->money($hargaAktif - $legacyStoreDiscount + $beautySurcharge);

        return [
            'hpp' => $hpp,
            'harga_akhir' => $hargaAkhir,
            'disc_brand_type' => $rule->disc_brand_type,
            'disc_brand_value' => (float) $rule->disc_brand_value,
            'disc_brand_amount' => $brandAmount,
            'disc_tambahan_type' => $additionalType,
            'disc_tambahan_value' => $additionalValue,
            'disc_tambahan_amount' => $additionalAmount,
            'margin_type' => $rule->margin_type,
            'margin_value' => (float) $rule->margin_value,
            'margin_amount' => $marginAmount,
            'harga_aktif' => $hargaAktif,
            'disc_toko_type' => $rule->disc_toko_type,
            'disc_toko_value' => (float) $rule->disc_toko_value,
            'disc_toko_amount' => $legacyStoreDiscount,
            'outlet_surcharge' => $beautySurcharge,
            'price' => max(0, $hargaJual),
        ];
    }

    private function isBeautyOutlet(OutletPrice $rule): bool
    {
        return $rule->relationLoaded('outlet')
            && strtolower(trim((string) $rule->outlet?->jenis_outlet)) === 'beauty';
    }

    public function voucherAmount(Voucher $voucher, float $base): float
    {
        $base = $this->money($base);

        if ($base < $this->money($voucher->min_purchase ?? 0)) {
            return 0;
        }

        $amount = $this->discountAmount($base, $voucher->type, $voucher->value);
        if ($voucher->max_discount_amount !== null) {
            $amount = min($amount, $this->money($voucher->max_discount_amount));
        }

        return min($base, max(0, $amount));
    }

    public function discountAmount(float $base, ?string $type, float $value): float
    {
        $base = $this->money($base);
        $value = max(0, (float) $value);

        if ($this->isPercentage($type)) {
            $value = min(100, $value);
            return min($base, $this->money($base * $value / 100));
        }

        return min($base, $this->money($value));
    }

    private function isPercentage(?string $type): bool
    {
        return strtolower(trim((string) $type)) === 'percentage';
    }

    public function money(float|int|null $value): float
    {
        return (float) round((float) ($value ?? 0), 0, PHP_ROUND_HALF_UP);
    }
}
