@php
    // Kolom komponen harga: sama dengan tabel index (label => [kolom nilai, kolom tipe]).
    $componentColumns = [
        'Pajak' => ['pajak_value', 'pajak_type'],
        'Disc Reg' => ['disc_brand_value', 'disc_brand_type'],
        'Disc 1' => ['disc_tambahan_value', 'disc_tambahan_type'],
        'Disc 2' => ['disc_tambahan_2_value', 'disc_tambahan_2_type'],
        'Disc Toko' => ['disc_toko_value', 'disc_toko_type'],
        'Margin' => ['margin_value', 'margin_type'],
    ];
    $formatComponent = fn (float $value, ?string $type) => $type === 'percentage'
        ? rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') . '%'
        : 'Rp' . number_format($value, 0, ',', '.');
    $rp = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
@endphp
@foreach ($prices as $price)
    @php
        $product = $price->product;
        $hppKonversi = (float) ($product?->konversi_qty ?? 0);
        $adjustmentValue = (float) ($price->outlet_adjustment_value ?? 0);
        $stockQty = (int) ($price->outlet_stock_qty ?? 0);
    @endphp
    @if ($price->print_separator)
        <tr class="sep"><td colspan="14">{{ $price->print_separator }}</td></tr>
    @endif
    <tr>
        <td class="nw">{{ $product?->code ?: '—' }}</td>
        <td>{{ $product?->name }}</td>
        <td class="nw">
            {{ $rp($price->print_hpp ?? 0) }}
            <div class="small">/ {{ $product?->satuan ?? 'satuan kecil' }}</div>
            @if ($hppKonversi >= 1)
                <div class="small">{{ $rp(round(($price->print_hpp ?? 0) * $hppKonversi, 2)) }} / {{ $product?->satuan_besar ?? 'satuan besar' }}</div>
            @endif
        </td>
        @foreach ($componentColumns as $label => $columns)
            @php $componentValue = (float) ($price->{$columns[0]} ?? 0); @endphp
            <td class="c nw {{ $componentValue == 0 ? 'muted' : '' }}">
                {{ $componentValue == 0 ? '—' : $formatComponent($componentValue, $price->{$columns[1]}) }}
            </td>
        @endforeach
        <td class="c nw"><strong>{{ $rp($price->print_price_net ?? 0) }}</strong></td>
        <td class="c nw {{ $adjustmentValue == 0 ? 'muted' : '' }}">
            {{ $adjustmentValue == 0 ? '—' : $formatComponent($adjustmentValue, $price->outlet_adjustment_type) }}
        </td>
        <td>{{ $price->note ?: '—' }}</td>
        <td class="nw">{{ $price->updated_at?->format('d/m/Y H:i') ?? '—' }}</td>
        <td class="nw {{ $stockQty <= 0 ? 'neg' : '' }}">
            {{ $stockQty }} {{ $product?->satuan ?? '' }}
            @if ($stockQty > 0 && $product?->konversi_qty && $product?->satuan_besar)
                <div class="small">{{ $product->konversiDisplay($stockQty) }}</div>
            @endif
        </td>
    </tr>
@endforeach