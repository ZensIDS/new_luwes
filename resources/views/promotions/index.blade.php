@extends('layouts.master')

@section('title', 'Promo Flash Sale & Bundle')

@section('container')
<section class="content-header"><h1>Promo Flash Sale &amp; Bundle</h1></section>
<section class="content">
    <div class="box">
        <div class="box-header">
            <a href="{{ route('campaign.create', ['type' => 'flash_sale']) }}" class="btn btn-success"><i class="fa fa-plus"></i> Buat voucher / promo</a>
        </div>
        <div class="box-body table-responsive">
            <table id="promotions-table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama / Kode</th>
                        <th>Tipe</th>
                        <th>Produk</th>
                        <th>Periode</th>
                        <th>Outlet</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($promotions as $promotion)
                        @php
                            $status = $promotion->isActive() ? 'Aktif' : ($promotion->is_active ? 'Tidak aktif' : 'Nonaktif');
                        @endphp
                        <tr>
                            <td>{{ $promotions->firstItem() + $loop->index }}</td>
                            <td>
                                <strong>{{ $promotion->name }}</strong>
                                @if ($promotion->code)<br><small>{{ $promotion->code }}</small>@endif
                            </td>
                            <td>
                                {{ $promotion->type === 'flash_sale' ? 'Rafaksi' : 'Bundle / bonus' }}
                                @if ($promotion->type === 'flash_sale')
                                    <br><small>{{ $promotion->discount_type === 'percentage' ? $promotion->discount_value.'%' : ($promotion->discount_type === 'fixed_price' ? 'Harga Rp'.number_format($promotion->discount_value, 0, ',', '.') : '-Rp'.number_format($promotion->discount_value, 0, ',', '.')) }}</small>
                                @else
                                    <br><small>{{ $promotion->bundle_price > 0 ? 'Hemat Rp'.number_format($promotion->bundle_price, 0, ',', '.') : 'Bonus: '.$promotion->bonuses->pluck('name')->join(', ') }}</small>
                                @endif
                            </td>
                            <td>{{ $promotion->products->pluck('name')->join(', ') }}</td>
                            <td>{{ $promotion->start_at?->format('d/m/Y H:i') ?? '-' }}<br>{{ $promotion->end_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td>{{ $promotion->outlets->isNotEmpty() ? $promotion->outlets->pluck('name')->join(', ') : ($promotion->outlet?->name ?? 'All outlets') }}</td>
                            <td>{{ $status }}<br><small>Terpakai: {{ $promotion->used_qty }}{{ $promotion->quota_qty ? '/'.$promotion->quota_qty : '' }}</small></td>
                            <td class="text-nowrap">
                                <a class="btn btn-warning btn-sm" href="{{ route('promotion.edit', $promotion) }}">Edit</a>
                                <form action="{{ route('promotion.destroy', $promotion) }}" method="POST" style="display:inline">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm" onclick="return confirm('Hapus promo ini?')">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $promotions->links() }}
        </div>
    </div>
</section>
@endsection

@section('page-script')
<script>
$(function () { $('#promotions-table').DataTable({ paging: false }); });
</script>
@endsection
