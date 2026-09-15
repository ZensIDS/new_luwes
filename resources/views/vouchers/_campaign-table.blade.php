<div class="table-responsive">
    <table id="{{ $tableId }}" class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama / Kode</th>
                <th>Jenis</th>
                <th>Produk</th>
                <th>Potongan</th>
                <th>Jumlah / kuota promo</th>
                <th>Masa berlaku</th>
                <th>Outlet</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($campaigns as $campaign)
                @php
                    $isVoucher = $campaign->campaign_kind === 'voucher';
                    $productNames = $isVoucher
                        ? ($campaign->products->isNotEmpty() ? $campaign->products->pluck('name')->join(', ') : ($campaign->product?->name ?? 'Semua produk'))
                        : ($campaign->products->isNotEmpty() ? $campaign->products->pluck('name')->join(', ') : '—');
                    $outletNames = $campaign->outlets->isNotEmpty()
                        ? $campaign->outlets->pluck('name')->join(', ')
                        : ($campaign->outlet?->name ?? 'Semua outlet');
                    $discountType = $isVoucher ? $campaign->type : $campaign->discount_type;
                    $discountValue = $isVoucher ? $campaign->value : $campaign->discount_value;
                    $status = $isVoucher
                        ? ($campaign->redemptions_count ? 'Sudah dipakai' : ($campaign->isActive() ? 'Aktif' : 'Tidak aktif'))
                        : ($campaign->isActive() ? 'Aktif' : ($campaign->is_active ? 'Tidak aktif' : 'Nonaktif'));
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>
                        <strong>{{ $campaign->name }}</strong>
                        @if ($campaign->code)<br><small>{{ $campaign->code }}</small>@endif
                    </td>
                    <td>{{ $isVoucher ? 'Voucher' : ($campaign->type === 'flash_sale' ? 'Flash Sale' : 'Bundle + Bonus') }}</td>
                    <td>{{ $productNames }}</td>
                    <td>
                        @if ($isVoucher || $campaign->type === 'flash_sale')
                            @if ($discountType === 'percentage')
                                {{ $discountValue }}%
                            @else
                                @currency($discountValue)
                            @endif
                        @else
                            @if ($campaign->bundle_price > 0)
                                Hemat @currency($campaign->bundle_price)
                            @else
                                Bonus saja
                            @endif
                            @if ($campaign->bonuses->isNotEmpty())<br><small>Bonus: {{ $campaign->bonuses->pluck('name')->join(', ') }}</small>@endif
                        @endif
                    </td>
                    <td>
                        @if ($isVoucher)
                            {{ $campaign->redemptions_count }}/1 pemakaian
                        @else
                            {{ $campaign->used_qty }}{{ $campaign->quota_qty ? '/'.$campaign->quota_qty : '' }} pemakaian
                        @endif
                    </td>
                    <td>{{ $campaign->start_at?->format('d/m/Y H:i') ?? '—' }}<br>{{ $campaign->end_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ $outletNames }}</td>
                    <td>{{ $status }}</td>
                    <td class="text-nowrap">
                        <a class="btn btn-warning btn-sm" href="{{ route('campaign.edit', ['type' => $isVoucher ? 'voucher' : 'promotion', 'id' => $campaign->id]) }}">Edit</a>
                        @if ($isVoucher)
                            <a class="btn btn-info btn-sm" href="{{ route('voucher.show', $campaign) }}">Lihat</a>
                            <form action="{{ route('voucher.destroy', $campaign) }}" method="POST" style="display:inline">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm" onclick="return confirm('Hapus voucher ini?')">Hapus</button>
                            </form>
                        @else
                            <form action="{{ route('promotion.destroy', $campaign) }}" method="POST" style="display:inline">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm" onclick="return confirm('Hapus promo ini?')">Hapus</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center text-muted">{{ $emptyMessage }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
