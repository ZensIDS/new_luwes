@extends('layouts.master')
@section('title', 'Laporan Gudang')
@section('container')
@include('laporan.partials._style')

@php
    $role = auth()->user()->role;
    $all = ['superadmin', 'admin-gudang', 'owner'];

    // group => daftar laporan. 'date' = butuh rentang tanggal, 'roles' = role yang boleh melihat.
    $groups = [
        'Pembelian & Penerimaan' => [
            ['id'=>'po', 'title'=>'Laporan PO', 'icon'=>'fa-file-text-o', 'color'=>'blue', 'xls'=>'laporan.export.po', 'date'=>true,
             'desc'=>'Daftar purchase order ke supplier pada periode terpilih.', 'roles'=>['superadmin', 'admin-gudang']],
            ['id'=>'pembelian', 'title'=>'Pembelian Barang', 'icon'=>'fa-shopping-cart', 'color'=>'blue', 'xls'=>'laporan.export.pembelian', 'date'=>true,
             'desc'=>'Rekap pembelian barang dari supplier.', 'roles'=>['superadmin', 'owner']],
            ['id'=>'penerimaan', 'title'=>'Penerimaan Barang', 'icon'=>'fa-inbox', 'color'=>'green', 'xls'=>'laporan.export.penerimaan', 'date'=>true,
             'desc'=>'Barang yang sudah diterima gudang.', 'roles'=>$all],
        ],
        'Stok' => [
            ['id'=>'stok', 'title'=>'Stok Barang', 'icon'=>'fa-cubes', 'color'=>'teal', 'xls'=>'laporan.stock', 'date'=>false,
             'desc'=>'Posisi stok seluruh barang di gudang saat ini.', 'roles'=>$all],
            ['id'=>'opname', 'title'=>'Stok Opname & Adjustment', 'icon'=>'fa-balance-scale', 'color'=>'red', 'xls'=>'laporan.stock-opname', 'date'=>true,
             'desc'=>'Hasil opname dan penyesuaian stok.', 'roles'=>$all],
            ['id'=>'pergerakan', 'title'=>'Pergerakan & Kebutuhan Stok', 'icon'=>'fa-line-chart', 'color'=>'purple', 'xls'=>'laporan.export.pergerakan', 'date'=>false,
             'desc'=>'Pergerakan barang dan perkiraan kebutuhan stok.', 'roles'=>['superadmin', 'owner']],
            ['id'=>'outlet_minimal_stock', 'title'=>'Minimal Stock & Alokasi PO', 'icon'=>'fa-bar-chart', 'color'=>'amber', 'xls'=>'laporan.outlet.minimal-stock', 'date'=>false,
             'desc'=>'Stok minimal per produk beserta status dan saran qty PO.', 'roles'=>$all],
        ],
        'Distribusi ke Outlet' => [
            ['id'=>'pr', 'title'=>'PR (Permintaan Outlet)', 'icon'=>'fa-file-text-o', 'color'=>'blue', 'xls'=>'laporan.export.pr', 'date'=>true,
             'desc'=>'Permintaan barang dari outlet ke gudang.', 'roles'=>$all],
            ['id'=>'picking', 'title'=>'Picking & Packing', 'icon'=>'fa-list-ol', 'color'=>'purple', 'xls'=>'laporan.export.picking', 'date'=>true,
             'desc'=>'Proses pengambilan dan pengemasan barang.', 'roles'=>$all],
            ['id'=>'pengiriman', 'title'=>'Pengiriman Barang', 'icon'=>'fa-truck', 'color'=>'amber', 'xls'=>'laporan.export.pengiriman', 'date'=>true,
             'desc'=>'Pengiriman barang dari gudang ke outlet.', 'roles'=>$all],
            ['id'=>'masuk', 'title'=>'Barang Masuk', 'icon'=>'fa-arrow-down', 'color'=>'green', 'xls'=>'laporan.export.barang-masuk', 'date'=>true,
             'desc'=>'Seluruh mutasi barang yang masuk ke gudang.', 'roles'=>['superadmin', 'owner']],
            ['id'=>'keluar', 'title'=>'Barang Keluar', 'icon'=>'fa-arrow-up', 'color'=>'amber', 'xls'=>'laporan.export.barang-keluar', 'date'=>true,
             'desc'=>'Seluruh mutasi barang yang keluar dari gudang.', 'roles'=>['superadmin', 'owner']],
        ],
        'Retur' => [
            ['id'=>'retur_supplier', 'title'=>'Retur ke Supplier', 'icon'=>'fa-undo', 'color'=>'amber', 'xls'=>'laporan.retur-supplier', 'date'=>true,
             'desc'=>'Barang yang dikembalikan ke supplier.', 'roles'=>$all],
            ['id'=>'retur_outlet', 'title'=>'Retur Outlet', 'icon'=>'fa-undo', 'color'=>'red', 'xls'=>'laporan.retur-outlet', 'date'=>true,
             'desc'=>'Barang yang diretur dari outlet ke gudang.', 'roles'=>$all],
        ],
        'Aktivitas' => [
            ['id'=>'aktifitas', 'title'=>'Aktivitas Gudang', 'icon'=>'fa-tasks', 'color'=>'teal', 'xls'=>'laporan.export.aktifitas', 'date'=>true,
             'desc'=>'Riwayat aktivitas petugas di gudang.', 'roles'=>['superadmin', 'owner']],
        ],
    ];

    // Saring berdasarkan role, buang grup yang kosong.
    $groups = collect($groups)
        ->map(fn ($items) => collect($items)->filter(fn ($r) => in_array($role, $r['roles']))->values())
        ->filter(fn ($items) => $items->isNotEmpty());
@endphp

<section class="content-header">
    <h1>Laporan Gudang <small>Pilih laporan, atur periode, lalu unduh Excel</small></h1>
</section>

<section class="content">
    <div class="rpt-toolbar">
        <p class="rpt-hint">{{ $groups->flatten(1)->count() }} laporan tersedia untuk akun Anda.</p>
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

    @foreach ($groups->flatten(1) as $r)
        @include('laporan.partials._modal', ['r' => $r])
    @endforeach
</section>
@endsection

@section('page-script')
    @include('laporan.partials._script')
@endsection