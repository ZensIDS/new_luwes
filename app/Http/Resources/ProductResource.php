<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        $now = Carbon::now();

        // Sort stocks by status and serial_number
        $sortedStocks = $this->stocks->sortBy('status')->sortBy('serial_number');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'barcode' => $this->code,
            'desc' => $this->desc,
            'image' => $this->pic,
            'code' => $this->barcode,
            'brand' => $this->brand,
            'model' => $this->model,
            'harga_jual' => $this->harga_jual,
            'image_url' => asset($this->pic),
            'is_serialized' => $this->is_serialized,
            'total_stock' => $this->total_stock,
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
