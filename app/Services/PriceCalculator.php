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
                'pajak_type' => 'nominal',
                'pajak_value' => 0,
                'pajak_amount' => 0,
                'hpp_setelah_pajak' => $hpp,
                'harga_akhir' => $hpp,
                'disc_brand_type' => 'nominal',
                'disc_brand_value' => 0,
                'disc_brand_amount' => 0,
                'disc_tambahan_type' => 'nominal',
                'disc_tambahan_value' => 0,
                'disc_tambahan_amount' => 0,
                'harga_dasar' => $hpp,
                'margin_type' => 'nominal',
                'margin_value' => $margin,
                'margin_amount' => $margin,
                'harga_aktif' => $active,
                'disc_toko_type' => 'nominal',
                'disc_toko_value' => 0,
                'disc_toko_amount' => 0,
                'outlet_adjustment_type' => 'nominal',
                'outlet_adjustment_value' => 0,
                'outlet_surcharge' => 0,
                'price' => $active,
            ];
        }

        $taxType = $rule->pajak_type ?: 'nominal';
        $taxValue = (float) ($rule->pajak_value ?? 0);
        $taxAmount = $this->additionAmount($hpp, $taxType, $taxValue);
        $hppSetelahPajak = $this->money($hpp + $taxAmount);

        $brandType = $rule->disc_brand_type ?: 'nominal';
        $brandValue = (float) ($rule->disc_brand_value ?? 0);
        $brandAmount = $this->discountAmount($hppSetelahPajak, $brandType, $brandValue);
        $hargaAkhir = max(0, $this->money($hppSetelahPajak - $brandAmount));

        $additionalType = $rule->disc_tambahan_type ?: 'nominal';
        $additionalValue = (float) ($rule->disc_tambahan_value ?? 0);
        $additionalAmount = $this->discountAmount($hargaAkhir, $additionalType, $additionalValue);
        $hargaDasar = max(0, $this->money($hargaAkhir - $additionalAmount));

        $marginAmount = $this->isPercentage($rule->margin_type)
            ? $this->money($hargaDasar * ((float) $rule->margin_value / 100))
            : $this->money($rule->margin_value);
        $hargaAktif = $this->money($hargaDasar + $marginAmount);

        $storeType = $rule->disc_toko_type ?: 'nominal';
        $storeValue = (float) ($rule->disc_toko_value ?? 0);
        $storeDiscount = $this->discountAmount($hargaAktif, $storeType, $storeValue);
        $hargaSebelumPenyesuaian = max(0, $this->money($hargaAktif - $storeDiscount));

        $adjustmentType = $rule->outlet_adjustment_type ?: 'nominal';
        $adjustmentValue = (float) ($rule->outlet_adjustment_value ?? 0);
        $adjustmentAmount = $this->additionAmount($hargaSebelumPenyesuaian, $adjustmentType, $adjustmentValue);
        $hargaJual = $this->money($hargaSebelumPenyesuaian + $adjustmentAmount);

        return [
            'hpp' => $hpp,
            'pajak_type' => $taxType,
            'pajak_value' => $taxValue,
            'pajak_amount' => $taxAmount,
            'hpp_setelah_pajak' => $hppSetelahPajak,
            'harga_akhir' => $hargaAkhir,
            'disc_brand_type' => $brandType,
            'disc_brand_value' => $brandValue,
            'disc_brand_amount' => $brandAmount,
            'disc_tambahan_type' => $additionalType,
            'disc_tambahan_value' => $additionalValue,
            'disc_tambahan_amount' => $additionalAmount,
            'harga_dasar' => $hargaDasar,
            'margin_type' => $rule->margin_type,
            'margin_value' => (float) $rule->margin_value,
            'margin_amount' => $marginAmount,
            'harga_aktif' => $hargaAktif,
            'disc_toko_type' => $storeType,
            'disc_toko_value' => $storeValue,
            'disc_toko_amount' => $storeDiscount,
            'outlet_adjustment_type' => $adjustmentType,
            'outlet_adjustment_value' => $adjustmentValue,
            'outlet_surcharge' => $adjustmentAmount,
            'price' => max(0, $hargaJual),
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

        if ($this->isPercentage($type)) {
            $value = min(100, $value);
            return min($base, $this->money($base * $value / 100));
        }

        return min($base, $this->money($value));
    }

    private function additionAmount(float $base, ?string $type, float $value): float
    {
        $value = max(0, (float) $value);

        return $this->isPercentage($type)
            ? $this->money($base * $value / 100)
            : $this->money($value);
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
