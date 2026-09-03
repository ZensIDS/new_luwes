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
        $marginAmount = $rule->margin_type === 'percentage'
            ? $this->money($hargaAkhir * ((float) $rule->margin_value / 100))
            : $this->money($rule->margin_value);
        $hargaAktif = $this->money($hargaAkhir + $marginAmount);
        $storeDiscount = $this->discountAmount($hargaAktif, $rule->disc_toko_type, $rule->disc_toko_value);

        return [
            'hpp' => $hpp,
            'harga_akhir' => $hargaAkhir,
            'disc_brand_type' => $rule->disc_brand_type,
            'disc_brand_value' => (float) $rule->disc_brand_value,
            'disc_brand_amount' => $brandAmount,
            'margin_type' => $rule->margin_type,
            'margin_value' => (float) $rule->margin_value,
            'margin_amount' => $marginAmount,
            'harga_aktif' => $hargaAktif,
            'disc_toko_type' => $rule->disc_toko_type,
            'disc_toko_value' => (float) $rule->disc_toko_value,
            'disc_toko_amount' => $storeDiscount,
            'price' => max(0, $hargaAktif - $storeDiscount),
        ];
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

        if ($type === 'percentage') {
            $value = min(100, $value);
            return min($base, $this->money($base * $value / 100));
        }

        return min($base, $this->money($value));
    }

    public function money(float|int|null $value): float
    {
        return (float) round((float) ($value ?? 0), 0, PHP_ROUND_HALF_UP);
    }
}
