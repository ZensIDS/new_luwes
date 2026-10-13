@extends('layouts.master')

@section('title', 'Penjualan Melebihi Stok')

@section('container')
    <style>
        .oversold-filter-bar { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:12px; }
        .oversold-filter { margin:0; min-width:160px; }
        .oversold-filter label { display:block; margin-bottom:4px; font-size:12px; color:#666; }
        .oversold-table th, .oversold-table td { vertical-align:middle !important; }
        .oversold-summary span { margin-right:18px; }
        @media (max-width:767px) { .oversold-filter, .oversold-filter .form-control { width:100% !important; } }
    </style>

    <section class="content-header">
        <h1>Penjualan Melebihi Stok <small>Item yang terjual saat stok sistem tidak cukup</small></h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <form method="GET" action="{{ route('deficit-stocks.oversold') }}" class="oversold-filter-bar">
                            @if ($canChooseOutlet)
                                <div class="oversold-filter">
                                    <label for="outlet_id">Outlet</label>
                                    <select id="outlet_id" name="outlet_id" class="form-control input-sm">
                                        <option value="">Semua outlet</option>
                                        @foreach ($outlets as $outlet)
                                            <option value="{{ $outlet->id }}" {{ (int) $selectedOutletId === (int) $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="oversold-filter">
                                <label for="tanggal_mulai">Dari tanggal</label>
                                <input id="tanggal_mulai" type="date" name="tanggal_mulai" value="{{ $from }}" class="form-control input-sm">
                            </div>
                            <div class="oversold-filter">
                                <label for="tanggal_selesai">Sampai tanggal</label>
                                <input id="tanggal_selesai" type="date" name="tanggal_selesai" value="{{ $to }}" class="form-control input-sm">
                            </div>
                            @if ($product)
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <div class="oversold-filter">
                                    <label>Produk</label>
                                    <div class="form-control input-sm" style="height:auto;">{{ $product->name }} <small class="text-muted">{{ $product->code }}</small></div>
                                </div>
                            @endif
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Tampilkan</button>
                            <a href="{{ route('deficit-stocks.oversold', array_filter(['outlet_id' => $selectedOutletId])) }}" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Reset</a>
                            <a href="{{ route('deficit-stocks.index', array_filter(['outlet_id' => $selectedOutletId])) }}" class="btn btn-warning btn-sm"><i class="fa fa-minus-circle"></i> Stok Minus</a>
                        </form>

                        <p class="oversold-summary" style="margin:0;">
                            <span><strong>{{ $summary['invoices'] }}</strong> invoice</span>
                            <span><strong>{{ $summary['items'] }}</strong> baris item</span>
                            <span><strong>{{ $summary['qty'] }}</strong> qty terjual melebihi stok</span>
                            <span><strong>{{ $summary['still_minus'] }}</strong> produk masih minus</span>
                        </p>
                    </div>

                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped oversold-table">
                            <thead>
                                <tr>
                                    <th style="width:40px;">No</th>
                                    <th>Tanggal</th>
                                    <th>Invoice</th>
                                    <th>Outlet</th>
                                    <th>Kasir</th>
                                    <th>Kode</th>
                                    <th>Produk</th>
                                    <th class="text-right">Qty</th>
                                    <th class="text-right">Harga</th>
                                    <th class="text-right">HPP Perkiraan</th>
                                    <th>Status Minus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr>
                                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                                        <td>{{ \Carbon\Carbon::parse($row->sold_at)->format('d M Y H:i') }}</td>
                                        <td><a href="{{ route('penjualan.show', $row->penjualan_id) }}">{{ $row->invoice_code }}</a></td>
                                        <td>{{ $row->outlet_name }}</td>
                                        <td>{{ $row->cashier_name ?? '-' }}</td>
                                        <td>{{ $row->product_code }}</td>
                                        <td>{{ $row->product_name }}</td>
                                        <td class="text-right">{{ $row->qty }} {{ $row->satuan }}</td>
                                        <td class="text-right">Rp {{ number_format((float) $row->price, 0, ',', '.') }}</td>
                                        <td class="text-right">Rp {{ number_format((float) $row->hpp, 0, ',', '.') }}</td>
                                        <td>
                                            @if ((int) $row->deficit_balance < 0)
                                                <span class="label label-danger">Masih minus ({{ (int) $row->deficit_balance }})</span>
                                            @else
                                                <span class="label label-success">Sudah tertutup</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="11" class="text-center text-muted">Tidak ada penjualan melebihi stok pada periode ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($rows->hasPages())
                        <div class="box-footer">{{ $rows->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection