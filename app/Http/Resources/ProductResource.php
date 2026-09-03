<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        // Sort stocks by status and serial_number
        $sortedStocks = $this->stocks->sortBy('status')->sortBy('serial_number');
        $ownerStocks = $this->relationLoaded('ownerStocks')
            ? $this->ownerStocks->where('qty', '>', 0)->values()
            : collect();
        $isOutletContext = $this->relationLoaded('ownerStocks') && $ownerStocks->isNotEmpty();
        $priceRule = $this->relationLoaded('outletPrices') ? $this->outletPrices->first() : null;
        $displayPrice = $this->harga_jual;
        if ($isOutletContext && $ownerStocks->first()) {
            $displayPrice = app(\App\Services\PriceCalculator::class)
                ->calculateItem((float) ($ownerStocks->first()->hpp ?? $this->harga_beli ?? 0), $priceRule, $this->resource)['price'];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'barcode' => $this->code,
            'desc' => $this->desc,
            'image' => $this->pic,
            'code' => $this->code,
            'brand' => $this->brand,
            'model' => $this->model,
            'harga_jual' => $displayPrice,
            'image_url' => asset($this->pic),
            'is_serialized' => $this->is_serialized,
            'total_stock' => $isOutletContext ? $ownerStocks->sum('qty') : $this->total_stock,
            'outlet_stock' => $isOutletContext ? $ownerStocks->sum('qty') : null,
            'price_rule' => $priceRule ? [
                'disc_brand_type' => $priceRule->disc_brand_type,
                'disc_brand_value' => $priceRule->disc_brand_value,
                'margin_type' => $priceRule->margin_type,
                'margin_value' => $priceRule->margin_value,
                'disc_toko_type' => $priceRule->disc_toko_type,
                'disc_toko_value' => $priceRule->disc_toko_value,
            ] : null,
            'owner_stocks' => $ownerStocks->map(fn ($ownerStock) => [
                'id' => $ownerStock->id,
                'qty' => $ownerStock->qty,
                'available' => $ownerStock->qty > 0,
                'hpp' => $ownerStock->hpp,
                'batch_number' => $ownerStock->batch_number,
                'expired_at' => optional($ownerStock->expired_at)->toDateString(),
                'serial_number' => $ownerStock->stock?->serial_number,
            ])->values(),
            'stocks' => $sortedStocks->map(fn ($stock) => [
                'id' => $stock->id,
                'status' => $stock->status,
                'serial_number' => $stock->serial_number,
                'qty' => $stock->qty,
                'expired_at' => optional($stock->expired_at)->toDateString(),
                'available' => $stock->qty > 0,
            ])->values(), // values() to reset keys after sorting
        ];
    }
}
