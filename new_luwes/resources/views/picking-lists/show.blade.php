@extends('layouts.master')
@section('title', 'Picking List Detail')
@section('container')
    <section class="content-header">
        <h1>Picking List: {{ $pickingList->code }}</h1>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Informasi</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-bordered">
                            <tr>
                                <th width="200">Request Order</th>
                                <td>{{ $pickingList->requestOrder->code }}</td>
                            </tr>
                            <tr>
                                <th>Owner/Outlet</th>
                                <td>{{ $pickingList->requestOrder->owner->name }}</td>
                            </tr>
                            <tr>
                                <th>Picker</th>
                                <td>{{ $pickingList->picker_name ?? $pickingList->picker?->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    @if ($pickingList->status == 'draft')
                                        <span class="label label-default">Draft</span>
                                    @elseif ($pickingList->status == 'in_progress')
                                        <span class="label label-warning">In Progress</span>
                                    @elseif ($pickingList->status == 'completed')
                                        <span class="label label-success">Completed</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Started At</th>
                                <td>{{ $pickingList->started_at ? $pickingList->started_at->format('d-m-Y H:i') : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <th>Completed At</th>
                                <td>{{ $pickingList->completed_at ? $pickingList->completed_at->format('d-m-Y H:i') : '-' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">Items to Pick</h3>
                    </div>
                    <div class="box-body table-responsive text-nowrap">
                        <table class="table table-bordered table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Product</th>
                                    <th>Location</th>
                                    <th>SKU</th>
                                    <th>Qty to Pick</th>
                                    <th>Konversi</th>
                                    <th>Qty Picked</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pickingList->items as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $item->product->name }}</td>
                                        <td>{{ $item->location ?? $item->product?->lokasi }}</td>
                                        <td>{{ $item->sku ?? '-' }}</td>
                                        <td>{{ $item->qty_to_pick }}</td>
                                        <td>{{ $item->product?->konversiDisplay($item->qty_to_pick) ?? '-' }}</td>
                                        <td>{{ $item->qty_picked }}</td>
                                        <td>
                                            @if ($item->is_picked)
                                                <span class="label label-success">✓ Picked</span>
                                            @else
                                                <span class="label label-warning">Pending</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer">
                        @if (!isset($pickingList->deliveryOrder))
                            @if ($pickingList->status == 'draft')
                                <form action="{{ route('picking-lists.start', $pickingList->id) }}" method="post"
                                    style="display: inline;">
                                    @csrf
                                    <button class="btn-sm btn btn-success">Start Picking</button>
                                </form>
                            @elseif ($pickingList->status == 'in_progress')
                                <a class="btn-sm btn btn-warning"
                                    href="{{ route('picking-lists.pick', $pickingList->id) }}">Continue</a>
                            @elseif ($pickingList->status == 'completed')
                                <form action="{{ route('delivery-orders.generate', $pickingList->id) }}"
                                    method="post" style="display: inline;">
                                    @csrf
                                    <button class="btn-sm btn btn-primary">Generate DO & Send to outlet</button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
