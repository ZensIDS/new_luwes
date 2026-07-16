@extends('layouts.master')

@section('title', 'Request Orders')

@section('container')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>
            OUTLET REQUESTS STOCK
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        @if (auth()->user()->role !== 'admin-gudang')
                        <a href="{{ route('request-orders.create') }}" class="btn btn-md bg-green">Tambah</a>
                        @endif
                        <div class="pull-right" style="display:flex; align-items:center; gap:8px;">
                            <label class="control-label" style="margin:0;">Filter Outlet:</label>
                            <select id="outlet-filter" class="select2" style="min-width:220px;">
                                <option value="">-- Semua Outlet --</option>
                                @foreach ($outlets as $outlet)
                                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div><!-- /.box-header -->
                    <div class="box-body table-responsive">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <td>No</td>
                                    <td>Kode Request</td>
                                    <td>Owner (Outlet)</td>
                                    <td>Requested By</td>
                                    <td>Tanggal Request</td>
                                    <td>Status</td>
                                    <td>Items</td>
                                    <td>Aksi</td>
                                </tr>
                            </thead>
                            @foreach ($requests as $value)
                                <tr data-outlet="{{ $value->owner_id ?? '' }}">
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $value->code }}</td>
                                    <td>{{ $value->owner->name ?? '-' }}</td>
                                    <td>{{ $value->requestedBy->name ?? '-' }}</td>
                                    <td>{{ $value->request_date->format('d-m-Y') }}</td>
                                    <td>
                                        @if ($value->status == 'pending')
                                            <span class="label label-warning">Pending</span>
                                        @elseif ($value->status == 'approved')
                                            <span class="label label-success">Approved</span>
                                        @elseif ($value->status == 'partial')
                                            <span class="label label-info">Partial</span>
                                        @elseif ($value->status == 'rejected')
                                            <span class="label label-danger">Rejected</span>
                                        @else
                                            <span class="label label-default">{{ $value->status }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php $totalItems = $value->items->count(); @endphp
                                        <ul class="list-unstyled" style="margin:0">
                                            @foreach ($value->items as $index => $item)
                                                <li class="item-ro-{{ $value->id }} @if($index >= 3) extra-item-ro-{{ $value->id }} @endif"
                                                    @if($index >= 3) style="display:none" @endif>
                                                    <small>
                                                        {{ $item->product->code ?? 'Code' }} | {{ $item->product->name ?? 'Produk' }}: {{ $item->qty_requested }}
                                                        @php $k = $item->product?->konversiDisplay($item->qty_requested); @endphp
                                                        @if($k && $k !== '-')
                                                            <span class="label label-info">{{ $k }}</span>
                                                        @endif
                                                        @if (!empty($item->notes))
                                                            <span class="text-muted">– {{ $item->notes }}</span>
                                                        @endif
                                                    </small>
                                                </li>
                                            @endforeach
                                        </ul>

                                        @if($totalItems > 3)
                                            <a href="javascript:void(0)"
                                                class="btn-toggle-ro-items"
                                                data-target="{{ $value->id }}"
                                                data-state="closed"
                                                style="display:inline-block; margin-top:4px;">
                                                <span class="label label-default">
                                                    Selengkapnya ({{ $totalItems - 3 }})
                                                </span>
                                            </a>
                                        @endif
                                    </td>
                                    <td>
                                        {{-- @if ($value->status == 'pending') --}}
                                            {{-- <a class="btn-xs btn btn-warning" href="{{ route('request-orders.edit', $value->id) }}">Edit</a> --}}
                                            {{-- <form action="{{ route('request-orders.destroy', $value->id) }}" method="post" style="display: inline;"> --}}
                                                {{-- @method('delete') --}}
                                                {{-- @csrf --}}
                                                {{-- <button class="border-0 btn-xs btn btn-danger" onclick="return confirm('Are you sure?')">Hapus</button> --}}
                                            {{-- </form> --}}
                                        {{-- @else --}}
                                            <!-- optional print if needed -->
                                            {{-- <a class="btn-xs btn btn-primary" href="{{ route('request-orders.print', $value->id) }}">Print</a> --}}
                                        {{-- @endif --}}
                                        @if (($value->status == 'approved' || $value->status == 'partial') && !isset($value->pickingList))
                                            <form action="{{ route('picking-lists.generate', $value->id) }}" method="post">
                                                @csrf
                                                <button class="btn btn-xs btn-primary">
                                                    <i class="fa fa-list"></i> Generate Picking List
                                                </button>
                                            </form>
                                        @endif
                                        @if (auth()->user()->role == 'staff-outlet')
                                        <a class="btn-xs btn btn-default" href="{{ route('request-orders.show', $value->id) }}"><i class="fa fa-eye"></i> Detail</a>
                                        @else
                                        @if ($value->status == 'approved' || $value->status == 'partial')
                                        <a class="btn-xs btn btn-default" href="{{ route('request-orders.show', $value->id) }}"><i class="fa fa-eye"></i> Detail</a>
                                        @else
                                        <a class="btn-xs btn btn-default" href="{{ route('request-orders.process', $value->id) }}"><i class="fa fa-eye"></i> Detail</a>
                                        @endif
                                        @endif
                                        <a class=" btn-xs btn btn-success" href="{{ route('laporan.request-order', $value->id) }}"><i class="fa fa-file-excel-o"></i> Export</a>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div><!-- /.box-body -->
                </div><!-- /.box -->
            </div><!-- /.col -->
        </div><!-- /.row -->
    </section><!-- /.content -->
@endsection

@section('page-script')
<script>
    $(function () {
        var selectedOutlet = '';

        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            if (!selectedOutlet) return true;
            var row = $('#example1').DataTable().row(dataIndex).node();
            return String($(row).data('outlet')) === selectedOutlet;
        });

        $('#outlet-filter').on('change', function () {
            selectedOutlet = $(this).val();
            $('#example1').DataTable().draw();
        });
    });
    $(document).on('click', '.btn-toggle-ro-items', function() {
        var id     = $(this).data('target');
        var state  = $(this).data('state');
        var $extra = $('.extra-item-ro-' + id);
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
