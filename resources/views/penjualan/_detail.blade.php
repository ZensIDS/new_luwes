<div class="table-responsive text-nowrap">
    <table class="table table-sm table-bordered">
        <tr>
            <th>Product</th>
            <th>Banyak</th>
            <th>Harga Jual</th>
            <th>Sub total</th>
        </tr>
        @php $totalCost = 0; @endphp
        @foreach ($value->displayItems() as $item)
            <tr>
                <td>{{ $item->serial_number ? $item->serial_number : $item->product?->code }} - {{ $item->product?->name ?? '(produk dihapus)' }}</td>
                <td>{{ $item->qty }}</td>
                <td>@currency($item->price)</td>
                <td>@currency($item->subtotal ?? ($item->qty * $item->price))</td>
            </tr>
        @php $totalCost += $item->subtotal ?? ($item->qty * $item->price); @endphp
        @endforeach
        <tr>
            <th>Disc Toko : @currency($value->discount_total ?? $value->discount)</th>
            <th>Promo : @currency($value->promotion_total ?? 0)</th>
            <th>Voucher : @currency($value->voucher_total ?? 0)</th>
            <th class="text-right">Subtotal : @currency($value->subtotal ?? $totalCost)</th>
        </tr>
        <tr>
            <th colspan="4" class="text-right">Grand Total : @currency($value->grand_total ?? ($totalCost - $value->discount - $value->voucher?->value))</th>
        </tr>
        <tr>
            <th colspan="3" class="text-right">Dibayar</th>
            <th class="text-right">@currency($value->paid_amount ?? $value->total ?? 0)</th>
        </tr>
        <tr>
            <th colspan="3" class="text-right">Kembalian</th>
            <th class="text-right">@currency($value->change_amount ?? 0)</th>
        </tr>
    </table>
</div>