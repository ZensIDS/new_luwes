@extends('layouts.master')
@section('title', 'Laporan')
@section('container')
<section class="content-header">
    <h1>Laporan & Export Data</h1>
</section>
<section class="content">

@php $role = auth()->user()->role; @endphp

<div class="row">
    {{-- Laporan PO --}}
    @if (in_array($role, ['superadmin', 'admin-gudang']))
    <div class="col-md-4 col-sm-6">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-file-text-o"></i> Laporan PO</h3></div>
            <div class="box-footer">
                <button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_po"><i class="fa fa-bar-chart"></i> Lihat Laporan</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Laporan PR (Permintaan Outlet) --}}
    @if (in_array($role, ['superadmin', 'staff-outlet']))
    <div class="col-md-4 col-sm-6">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-file-text-o"></i> Laporan PR (Permintaan Outlet)</h3></div>
            <div class="box-footer">
                <button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_pr"><i class="fa fa-bar-chart"></i> Lihat Laporan</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Laporan Barang Masuk --}}
    @if (in_array($role, ['superadmin', 'owner']))
    <div class="col-md-4 col-sm-6">
        <div class="box box-success">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-arrow-down"></i> Laporan Barang Masuk</h3></div>
            <div class="box-footer">
                <button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_masuk"><i class="fa fa-bar-chart"></i> Lihat Laporan</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Laporan Barang Keluar --}}
    @if (in_array($role, ['superadmin', 'owner']))
    <div class="col-md-4 col-sm-6">
        <div class="box box-warning">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-arrow-up"></i> Laporan Barang Keluar</h3></div>
            <div class="box-footer">
                <button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_keluar"><i class="fa fa-bar-chart"></i> Lihat Laporan</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Laporan Stok Barang --}}
    @if (in_array($role, ['superadmin', 'admin-gudang', 'owner']))
    <div class="col-md-4 col-sm-6">
        <div class="box box-info">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-cubes"></i> Laporan Stok Barang</h3></div>
            <div class="box-footer">
                <button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_stok"><i class="fa fa-bar-chart"></i> Lihat Laporan</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Laporan Penerimaan Barang --}}
    @if (in_array($role, ['superadmin', 'admin-gudang', 'owner']))
    <div class="col-md-4 col-sm-6">
        <div class="box box-success">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-inbox"></i> Laporan Penerimaan Barang</h3></div>
            <div class="box-footer">
                <button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_penerimaan"><i class="fa fa-bar-chart"></i> Lihat Laporan</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Laporan Pengiriman Barang --}}
    @if (in_array($role, ['superadmin', 'admin-gudang', 'owner']))
    <div class="col-md-4 col-sm-6">
        <div class="box box-warning">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-truck"></i> Laporan Pengiriman Barang</h3></div>
            <div class="box-footer">
                <button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_pengiriman"><i class="fa fa-bar-chart"></i> Lihat Laporan</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Laporan Picking & Packing --}}
    @if (in_array($role, ['superadmin', 'admin-gudang', 'owner']))
    <div class="col-md-4 col-sm-6">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-list-ol"></i> Laporan Picking &amp; Packing</h3></div>
            <div class="box-footer">
                <button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_picking"><i class="fa fa-bar-chart"></i> Lihat Laporan</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Laporan Aktivitas Gudang --}}
    @if (in_array($role, ['superadmin', 'owner']))
    <div class="col-md-4 col-sm-6">
        <div class="box box-info">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-tasks"></i> Laporan Aktivitas Gudang</h3></div>
            <div class="box-footer">
                <button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_aktifitas"><i class="fa fa-bar-chart"></i> Lihat Laporan</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Laporan Pembelian Barang --}}
    @if (in_array($role, ['superadmin', 'owner']))
    <div class="col-md-4 col-sm-6">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-shopping-cart"></i> Laporan Pembelian Barang</h3></div>
            <div class="box-footer">
                <button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_pembelian"><i class="fa fa-bar-chart"></i> Lihat Laporan</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Laporan Stok Opname & Adjusment --}}
    @if (in_array($role, ['superadmin', 'admin-gudang', 'owner']))
    <div class="col-md-4 col-sm-6">
        <div class="box box-danger">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-balance-scale"></i> Laporan Stok Opname &amp; Adjusment</h3></div>
            <div class="box-footer">
                <button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_opname"><i class="fa fa-bar-chart"></i> Lihat Laporan</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Laporan Pergerakan & Kebutuhan Stok --}}
    @if (in_array($role, ['superadmin', 'owner']))
    <div class="col-md-4 col-sm-6">
        <div class="box box-info">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-line-chart"></i> Laporan Pergerakan &amp; Kebutuhan Stok</h3></div>
            <div class="box-footer">
                <button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_pergerakan"><i class="fa fa-bar-chart"></i> Lihat Laporan</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Laporan Retur Ke Supplier --}}
    @if (in_array($role, ['superadmin', 'admin-gudang', 'owner']))
    <div class="col-md-4 col-sm-6">
        <div class="box box-warning">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-undo"></i> Laporan Retur Ke Supplier</h3></div>
            <div class="box-footer">
                <button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_retur_supplier"><i class="fa fa-bar-chart"></i> Lihat Laporan</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Laporan Retur Outlet --}}
    @if (in_array($role, ['superadmin', 'staff-outlet', 'owner']))
    <div class="col-md-4 col-sm-6">
        <div class="box box-danger">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-undo"></i> Laporan Retur Outlet</h3></div>
            <div class="box-footer">
                <button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_retur_outlet"><i class="fa fa-bar-chart"></i> Lihat Laporan</button>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- ============ MODALS ============ --}}

@php
$modals = [
    ['id'=>'po',          'title'=>'Laporan PO',                           'pdf'=>'laporan.pdf.po',        'xls'=>'laporan.export.po',        'date'=>true],
    ['id'=>'pr',          'title'=>'Laporan PR (Permintaan Outlet)',        'pdf'=>'laporan.pdf.pr',        'xls'=>'laporan.export.pr',        'date'=>true],
    ['id'=>'masuk',       'title'=>'Laporan Barang Masuk',                 'pdf'=>'laporan.pdf.barang-masuk',  'xls'=>'laporan.export.barang-masuk',  'date'=>true],
    ['id'=>'keluar',      'title'=>'Laporan Barang Keluar',                'pdf'=>'laporan.pdf.barang-keluar', 'xls'=>'laporan.export.barang-keluar', 'date'=>true],
    ['id'=>'stok',        'title'=>'Laporan Stok Barang',                  'pdf'=>'laporan.pdf.stok',      'xls'=>'laporan.stock',            'date'=>false],
    ['id'=>'penerimaan',  'title'=>'Laporan Penerimaan Barang',            'pdf'=>'laporan.pdf.penerimaan','xls'=>'laporan.export.penerimaan','date'=>true],
    ['id'=>'pengiriman',  'title'=>'Laporan Pengiriman Barang',            'pdf'=>'laporan.pdf.pengiriman','xls'=>'laporan.export.pengiriman','date'=>true],
    ['id'=>'picking',     'title'=>'Laporan Picking &amp; Packing',        'pdf'=>'laporan.pdf.picking',   'xls'=>'laporan.export.picking',   'date'=>true],
    ['id'=>'aktifitas',   'title'=>'Laporan Aktivitas Gudang',             'pdf'=>'laporan.pdf.aktifitas', 'xls'=>'laporan.export.aktifitas', 'date'=>true],
    ['id'=>'pembelian',   'title'=>'Laporan Pembelian Barang',             'pdf'=>'laporan.pdf.pembelian', 'xls'=>'laporan.export.pembelian', 'date'=>true],
    ['id'=>'opname',      'title'=>'Laporan Stok Opname &amp; Adjusment',  'pdf'=>'laporan.pdf.opname',    'xls'=>'laporan.stock-opname',     'date'=>true],
    ['id'=>'pergerakan',  'title'=>'Laporan Pergerakan &amp; Kebutuhan Stok','pdf'=>'laporan.pdf.pergerakan','xls'=>'laporan.export.pergerakan','date'=>false],
    ['id'=>'retur_supplier','title'=>'Laporan Retur Ke Supplier',          'pdf'=>'laporan.pdf.retur-supplier','xls'=>'laporan.retur-supplier','date'=>true],
    ['id'=>'retur_outlet',  'title'=>'Laporan Retur Outlet',               'pdf'=>'laporan.pdf.retur-outlet',  'xls'=>'laporan.retur-outlet',  'date'=>true],
];
@endphp

@foreach($modals as $m)
<div id="modal_{{ $m['id'] }}" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">{!! $m['title'] !!}</h4>
            </div>
            <div class="modal-body">
                @if($m['date'])
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Tanggal Mulai</label>
                            <input type="date" id="mulai_{{ $m['id'] }}" class="form-control"
                                value="{{ now()->startOfMonth()->format('Y-m-d') }}">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Tanggal Selesai</label>
                            <input type="date" id="selesai_{{ $m['id'] }}" class="form-control"
                                value="{{ now()->format('Y-m-d') }}">
                        </div>
                    </div>
                </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-success btn-preview-pdf"
                    data-id="{{ $m['id'] }}"
                    data-url="{{ route($m['pdf']) }}"
                    data-date="{{ $m['date'] ? 'true' : 'false' }}">
                    <i class="fa fa-eye"></i> Preview PDF
                </button>
                <a href="{{ route($m['xls']) }}" id="xls_{{ $m['id'] }}" class="btn btn-primary btn-export-xls"
                    data-id="{{ $m['id'] }}"
                    data-url="{{ route($m['xls']) }}"
                    data-date="{{ $m['date'] ? 'true' : 'false' }}">
                    <i class="fa fa-file-excel-o"></i> Export Excel
                </a>
            </div>
        </div>
    </div>
</div>
@endforeach

</section>
@endsection

@section('page-script')
<script>
$(document).ready(function () {

    function buildUrl(baseUrl, id, hasDate) {
        if (!hasDate) return baseUrl;
        var mulai   = $('#mulai_'   + id).val();
        var selesai = $('#selesai_' + id).val();
        return baseUrl + '?tanggal_mulai=' + mulai + '&tanggal_selesai=' + selesai;
    }

    $(document).on('click', '.btn-preview-pdf', function () {
        var id      = $(this).data('id');
        var url     = $(this).data('url');
        var hasDate = $(this).data('date') === true || $(this).data('date') === 'true';
        window.open(buildUrl(url, id, hasDate), '_blank');
    });

    $(document).on('click', '.btn-export-xls', function (e) {
        e.preventDefault();
        var id      = $(this).data('id');
        var url     = $(this).data('url');
        var hasDate = $(this).data('date') === true || $(this).data('date') === 'true';
        window.location.href = buildUrl(url, id, hasDate);
    });

});
</script>
@endsection
