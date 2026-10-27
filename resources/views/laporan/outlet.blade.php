@extends('layouts.master')
@section('title', 'Laporan Toko')
@section('container')
@include('laporan.partials._style')

@php
    $groups = [
        'Penjualan' => [
            ['id'=>'outlet_penjualan', 'title'=>'Penjualan Outlet', 'icon'=>'fa-shopping-cart', 'color'=>'green', 'xls'=>'laporan.outlet.penjualan',
             'date'=>true, 'outlet'=>true, 'cashier'=>true, 'rafaksi'=>false,
             'desc'=>'Penjualan per kasir lengkap dengan rekap metode pembayaran, bon, dan setoran akhir.'],
            ['id'=>'outlet_rafaksi', 'title'=>'Rafaksi Outlet', 'icon'=>'fa-tags', 'color'=>'amber', 'xls'=>'laporan.outlet.rafaksi',
             'date'=>true, 'outlet'=>true, 'cashier'=>false, 'rafaksi'=>true,
             'desc'=>'Penjualan produk pada periode rafaksi beserta diskon per unit dan total diskon.'],
            ['id'=>'outlet_retur', 'title'=>'Retur Penjualan', 'icon'=>'fa-exchange', 'color'=>'red', 'xls'=>'laporan.outlet.retur',
             'date'=>true, 'outlet'=>true, 'cashier'=>false, 'rafaksi'=>false,
             'desc'=>'Transaksi retur, barang yang diretur, dan barang penggantinya.'],
        ],
        'Stok' => [
            ['id'=>'outlet_all_stock', 'title'=>'All Stock Outlet', 'icon'=>'fa-cubes', 'color'=>'teal', 'xls'=>'laporan.outlet.all-stock',
             'date'=>false, 'outlet'=>true, 'cashier'=>false, 'rafaksi'=>false,
             'desc'=>'Stok per barcode produk, termasuk HPP, HPP + pajak, dan rekap persediaan.'],
            ['id'=>'outlet_product_list', 'title'=>'Daftar Barang Outlet', 'icon'=>'fa-barcode', 'color'=>'blue', 'xls'=>'laporan.outlet.product-list',
             'date'=>false, 'outlet'=>true, 'outlet_required'=>true, 'folder'=>true, 'cashier'=>false, 'rafaksi'=>false,
             'desc'=>'Barcode dan nama barang satu outlet: semua barang, atau hanya yang berawalan nama tertentu (folder).'],
            ['id'=>'outlet_minimal_stock', 'title'=>'Minimal Stock & Alokasi PO', 'icon'=>'fa-bar-chart', 'color'=>'purple', 'xls'=>'laporan.outlet.minimal-stock',
             'date'=>false, 'outlet'=>false, 'cashier'=>false, 'rafaksi'=>false,
             'desc'=>'Stok minimal per produk gudang, status out of stock, dan saran qty PO.'],
        ],
        'Permintaan ke Gudang' => [
            ['id'=>'pr', 'title'=>'PR (Permintaan ke Gudang)', 'icon'=>'fa-file-text-o', 'color'=>'blue', 'xls'=>'laporan.export.pr',
             'date'=>true, 'outlet'=>false, 'cashier'=>false, 'rafaksi'=>false,
             'desc'=>'Rekap permintaan barang dari toko ke gudang.'],
            ['id'=>'retur_outlet', 'title'=>'Retur Toko ke Gudang', 'icon'=>'fa-undo', 'color'=>'red', 'xls'=>'laporan.retur-outlet',
             'date'=>true, 'outlet'=>false, 'cashier'=>false, 'rafaksi'=>false,
             'desc'=>'Retur barang dari toko ke gudang.'],
        ],
    ];
@endphp

<section class="content-header">
    <h1>Laporan Toko <small>Pilih laporan, atur filter, lalu unduh Excel</small></h1>
</section>

<section class="content">
    <div class="rpt-toolbar">
        <p class="rpt-hint">
            <i class="fa fa-info-circle"></i>
            Penjualan yang diretur tidak dihitung sebagai penjualan; barang pengganti tetap dihitung.
        </p>
        <div class="rpt-search">
            <i class="fa fa-search"></i>
            <input type="text" id="rptSearch" class="form-control" placeholder="Cari laporan..." autocomplete="off">
        </div>
    </div>

    @foreach ($groups as $groupName => $items)
        <div class="rpt-group">
            <h3 class="rpt-group-title">{{ $groupName }}</h3>
            <div class="rpt-grid">
                @foreach ($items as $r)
                    <button type="button" class="rpt-card rpt-c-{{ $r['color'] }}" data-toggle="modal" data-target="#modal_{{ $r['id'] }}">
                        <span class="rpt-icon"><i class="fa {{ $r['icon'] }}"></i></span>
                        <h4>{{ $r['title'] }}</h4>
                        <p>{{ $r['desc'] }}</p>
                        <span class="rpt-open">Buka <i class="fa fa-arrow-right"></i></span>
                    </button>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="rpt-empty"><i class="fa fa-search fa-2x"></i><p>Tidak ada laporan yang cocok.</p></div>

    @foreach (collect($groups)->flatten(1) as $r)
        @include('laporan.partials._modal', ['r' => $r])
    @endforeach
</section>
@endsection

@section('page-script')
    @include('laporan.partials._script')
@endsection