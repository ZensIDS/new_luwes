@extends('layouts.master')
@section('title', 'Picking Lists')
@section('container')
    <section class="content-header">
        <h1>PICKING & PACKING</h1>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <div class="pull-right" style="display:flex; align-items:center; gap:8px;">
                            <label class="control-label" style="margin:0;">Filter Outlet:</label>
                            <select id="outlet-filter" class="select2" style="min-width:220px;">
                                <option value="">-- Semua Outlet --</option>
                                @foreach ($outlets as $outlet)
                                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="box-body table-responsive">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <td>No</td>
                                    <td>Kode Picking</td>
                                    <td>Request Order</td>
                                    <td>Owner</td>
                                    <td>Picker</td>
                                    <td>Status</td>
                                    <td>Items</td>
                                    <td>Aksi</td>
                                </tr>
                            </thead>
                            @foreach ($pickingLists as $value)
                                <tr data-outlet="{{ $value->requestOrder->owner_id ?? '' }}">
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $value->code }}</td>
                                    <td>{{ $value->requestOrder->code }}</td>
                                    <td>{{ $value->requestOrder->owner->name }}</td>
                                    <td>{{ $value->picker->name ?? '-' }}</td>
                                    <td>
                                        @if ($value->status == 'draft')
                                            <span class="label label-default">Draft</span>
                                        @elseif ($value->status == 'in_progress')
                                            <span class="label label-warning">In Progress</span>
                                        @elseif ($value->status == 'completed')
                                            <span class="label label-success">Completed</span>
                                        @endif
                                    </td>
                                    <td>
                                        <ul class="list-unstyled" style="margin:0">
                                            @foreach ($value->items as $item)
                                                <li>
                                                    <small>
                                                        {{ $item->product?->code }} | {{ $item->product?->name }} × {{ $item->qty_to_pick }}
                                                        @php $k = $item->product?->konversiDisplay($item->qty_to_pick); @endphp
                                                        @if($k && $k !== '-')
                                                            <span class="label label-info">{{ $k }}</span>
                                                        @endif
                                                    </small>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </td>
                                    <td>
                                        <a class="btn-xs btn btn-default" href="{{ route('picking-lists.show', $value->id) }}"><i class="fa fa-eye"></i> Detail</a>
                                        @if (!isset($value->deliveryOrder))
                                            @if ($value->status == 'draft')
                                                <form action="{{ route('picking-lists.start', $value->id) }}" method="post"
                                                    style="display: inline;">
                                                    @csrf
                                                    <button class="btn-xs btn btn-success">Start Picking</button>
                                                </form>
                                            @elseif ($value->status == 'in_progress')
                                                <a class="btn-xs btn btn-warning"
                                                    href="{{ route('picking-lists.pick', $value->id) }}">Continue</a>
                                            @elseif ($value->status == 'completed')
                                                <form action="{{ route('delivery-orders.generate', $value->id) }}"
                                                    method="post" style="display: inline;">
                                                    @csrf
                                                    <button class="btn-xs btn btn-primary">Generate DO & Send to outlet</button>
                                                </form>
                                            @endif
                                        @endif
                                        <a class=" btn-xs btn btn-success" href="{{ route('laporan.pickinglist', $value->id) }}"><i class="fa fa-file-excel-o"></i> Export</a>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
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
</script>
@endsection
