<div class="table-responsive text-nowrap">
    <table class="table table-sm table-bordered">
        <tr>
            <th>Product</th>
            <th>Banyak</th>
            <th>Harga Jual</th>
            <th>Sub total</th>
        </tr>
        @php
            // Filter Per Brand: hanya barang yang namanya diawali / brand-nya diawali teks filter yang ditampilkan.
            $brandFilter = trim((string) ($brandFilter ?? ''));
            $allItems = $value->displayItems();
            $shownItems = $brandFilter === '' ? $allItems : collect($allItems)->filter(function ($item) use ($brandFilter) {
                return mb_stripos((string) ($item->product?->name ?? ''), $brandFilter) === 0
                    || mb_stripos((string) ($item->product?->brand ?? ''), $brandFilter) === 0;
            })->values();
            $brandSubtotal = 0;
            // Total nota selalu dari SEMUA barang (dipakai fallback grand total), bukan hanya barang yang tampil.
            $totalCost = collect($allItems)->sum(fn ($i) => $i->subtotal ?? ($i->qty * $i->price));
            // Kresek bukan barang brand: disembunyikan saat filter brand aktif.
            $kreseks = $brandFilter === '' ? $value->kreseks : [];
        @endphp
        @foreach ($shownItems as $item)
            <tr>
                <td>{{ $item->serial_number ? $item->serial_number : $item->product?->code }} - {{ $item->product?->name ?? '(produk dihapus)' }}</td>
                <td>{{ $item->qty }}</td>
                <td>@currency($item->price)</td>
                <td>@currency($item->subtotal ?? ($item->qty * $item->price))</td>
            </tr>
        @php
            $brandSubtotal += $item->subtotal ?? ($item->qty * $item->price);
        @endphp
        @endforeach
        @if ($brandFilter !== '')
            <tr class="info">
                <th colspan="3">Subtotal brand "{{ $brandFilter }}" ({{ $shownItems->count() }} dari {{ count($allItems) }} barang di nota ini)</th>
                <th>@currency($brandSubtotal)</th>
            </tr>
            <tr>
                <td colspan="4" class="text-muted small">Total di bawah adalah total seluruh nota (semua barang), bukan hanya brand yang difilter.</td>
            </tr>
        @endif
        @foreach ($kreseks as $kresek)
            <tr>
                <td>{{ $kresek->name }}</td>
                <td>{{ $kresek->qty }}</td>
                <td>@currency($kresek->price)</td>
                <td>@currency($kresek->subtotal)</td>
            </tr>
        @endforeach
        <tr>
            <th>Disc Toko : @currency($value->discount_total ?? $value->discount)</th>
            <th>Promo : @currency($value->promotion_total ?? 0)</th>
            <th>Voucher : @currency($value->voucher_cashback_total)</th>
            <th class="text-right">Subtotal : @currency($value->subtotal ?? $totalCost)</th>
        </tr>
        @if (($value->kresek_total ?? 0) > 0)
            <tr>
                <th colspan="4" class="text-right">Kresek : +@currency($value->kresek_total)</th>
            </tr>
        @endif
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