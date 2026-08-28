@extends('layouts.master')

@section('title', 'Purchase Order')

@section('container')
    <section class="content-header">
        <h1>Purchase Order <small>Gudang → Supplier</small></h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        @if (auth()->user()->role !== 'owner')
                            <a href="{{ route('pembelian.create') }}" class="btn btn-md bg-green">
                                <i class="fa fa-plus"></i> Buat PO Baru
                            </a>
                        @endif
                        {{-- <a href="{{ route('refundPembelian.index') }}" class="btn btn-md bg-green"> --}}
                            {{-- <i class="fa fa-refresh"></i> Refund PO --}}
                        {{-- </a> --}}
                    </div>
                    <div class="box-body table-responsive">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th width="40">No</th>
                                    <th>Kode PO</th>
                                    <th>Tanggal</th>
                                    <th>Supplier</th>
                                    <th>Items</th>
                                    <!--<th>Total</th>-->
                                    <th width="120">Status PO</th>
                                    <!--<th width="150">ACC Owner</th>-->
                                    <!--<th width="120">Status Bayar</th>-->
                                    <th width="200">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pembelians as $value)
                                    @php
                                        $payStatus = $value->pembelianTransaction?->status ?? 'unpaid';
                                        $payBadge = match ($payStatus) {
                                            'paid' => 'success',
                                            'partial' => 'warning',
                                            default => 'danger',
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td><strong>{{ $value->code }}</strong></td>
                                        <td>{{ $value->created_at->setTimezone('Asia/Jakarta')->translatedFormat('d F Y - H:i') }} WIB</td>
                                        <td>{{ $value->supplier?->name }}</td>
                                        <td>
                                            @php $totalItems = $value->pembelianProducts->count(); @endphp
                                            <ul class="list-unstyled" style="margin:0">
                                                @foreach ($value->pembelianProducts as $index => $item)
                                                    <li class="item-pembelian-{{ $value->id }} @if($index >= 3) extra-item-{{ $value->id }} @endif"
                                                        @if($index >= 3) style="display:none" @endif>
                                                        <small>
                                                            {{ $item->product?->code }} | {{ $item->product?->name }} × {{ $item->qty }}
                                                            @php $k = $item->product?->konversiDisplay($item->qty); @endphp
                                                            @if($k && $k !== '-')
                                                                <span class="label label-info">{{ $k }}</span>
                                                            @endif
                                                        </small>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        
                                            @if($totalItems > 3)
                                                <a href="javascript:void(0)"
                                                   class="btn-toggle-items"
                                                   data-target="{{ $value->id }}"
                                                   data-state="closed"
                                                   style="display:inline-block;margin-top:4px;">
                                                    <span class="label label-default">
                                                        Selengkapnya ({{ $totalItems - 3 }})
                                                    </span>
                                                </a>
                                            @endif
                                        </td>
                                        <!--<td>{{$value->total}}</td>-->
                                        <td>
                                            @if ($value->is_published)
                                                <span class="label label-success">PUBLISHED</span>
                                            @else
                                                <span class="label label-default">DRAFT</span>
                                            @endif
                                        </td>
                                        {{--<td>
                                            <span class="label label-{{ $value->owner_approval_status === 'approved' ? 'success' : ($value->owner_approval_status === 'rejected' ? 'danger' : 'warning') }}">
                                                {{ strtoupper($value->owner_approval_status ?? 'pending') }}
                                            </span>
                                            @if ($value->ownerApprovedBy)
                                                <br><small>{{ $value->ownerApprovedBy->name }}</small>
                                            @endif
                                        </td>--}}
                                        <!--<td>-->
                                        <!--    <span class="label label-{{ $payBadge }}">-->
                                        <!--        {{ strtoupper($payStatus) }}-->
                                        <!--    </span>-->
                                        <!--    @if ($value->pembelianTransaction?->amount > 0)-->
                                        <!--        <br><small>@currency($value->pembelianTransaction?->amount)</small>-->
                                        <!--    @endif-->
                                        <!--</td>-->
                                        <td>
                                            {{-- Bayar --}}
                                            <!--<a href="{{ route('pembelian.pembayaran.edit', $value->id) }}"-->
                                            <!--    class="btn btn-xs btn-default"-->
                                            <!--    title="Pembayaran">-->
                                            <!--    <i class="fa fa-credit-card"></i> Pembayaran-->
                                            <!--</a>-->

                                            @if (in_array(auth()->user()->role, ['owner', 'superadmin']) && $value->owner_approval_status === 'pending')
                                                <form action="{{ route('pembelian.owner-approve', $value->id) }}" method="post" style="display:inline">
                                                    @csrf
                                                    <button class="btn btn-xs btn-success" title="ACC Owner">
                                                        <i class="fa fa-check"></i> ACC
                                                    </button>
                                                </form>
                                                <form action="{{ route('pembelian.owner-reject', $value->id) }}" method="post" style="display:inline">
                                                    @csrf
                                                    <button class="btn btn-xs btn-danger" title="Tolak Owner">
                                                        <i class="fa fa-times"></i> Tolak
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($value->canBeEditedBy(auth()->user()))
                                                <a href="{{ route('pembelian.edit', $value->id) }}"
                                                    class="btn btn-xs btn-warning" title="Edit">
                                                    <i class="fa fa-pencil"></i>
                                                </a>
                                                @if (auth()->user()->role !== 'owner')
                                                    <form action="{{ route('pembelian.destroy', $value->id) }}" method="post"
                                                        style="display:inline">
                                                        @method('delete')
                                                        @csrf
                                                        <button class="btn btn-xs btn-danger"
                                                            onclick="return confirm('Hapus PO {{ $value->code }}?')"
                                                            title="Hapus">
                                                            <i class="fa fa-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif

                                            {{-- Export PO --}}
                                            <a href="{{ route('laporan.pembelian', $value->id) }}"
                                                class="btn btn-xs btn-success" title="Export PO">
                                                <i class="fa fa-file-excel-o"></i> PO
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
@section('page-script')
<script>
    $(document).on('click', '.btn-toggle-items', function () {
        var id = $(this).data('target');
        var state = $(this).data('state');
        var $extra = $('.extra-item-' + id);
        var $badge = $(this).find('.label');

        if (state === 'closed') {
            $extra.show();
            $badge.text('Tutup');
            $(this).data('state', 'open');
        } else {
            $extra.hide();
            $badge.text('Selengkapnya (' + $extra.length + ')');
            $(this).data('state', 'closed');
        }
    });
</script>
@endsection
