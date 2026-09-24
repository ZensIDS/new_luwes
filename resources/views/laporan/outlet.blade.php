@extends('layouts.master')
@section('title', 'Laporan Outlet')
@section('container')
<section class="content-header">
    <h1>Laporan Outlet</h1>
    <ul class="nav nav-tabs" style="margin-top:15px;">
        <li><a href="{{ route('laporan.index') }}"><i class="fa fa-building"></i> Laporan Gudang / Warehouse</a></li>
        <li class="active"><a href="{{ route('laporan.outlet.index') }}"><i class="fa fa-home"></i> Laporan Outlet</a></li>
    </ul>
</section>
<section class="content">
    <div class="alert alert-info">
        <i class="fa fa-info-circle"></i>
        Penjualan yang diretur tidak dihitung sebagai penjualan; barang pengganti tetap dihitung sebagai penjualan.
        Semua laporan dapat dicetak/preview PDF atau diunduh sebagai XLSX.
    </div>

    @php $role = auth()->user()->role; @endphp
    <div class="row">
        <div class="col-md-4 col-sm-6">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-line-chart"></i> Minimal Stock &amp; Alokasi PO</h3></div>
                <div class="box-body small">Rata-rata penjualan 3 bulan x faktor frekuensi PO. Menampilkan status out_of_stock dan saran qty PO.</div>
                <div class="box-footer"><button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_outlet_minimal_stock"><i class="fa fa-bar-chart"></i> Lihat Laporan</button></div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="box box-success">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-shopping-cart"></i> Laporan Penjualan</h3></div>
                <div class="box-body small">Filter daterange dan akun kasir, dengan rekap payment method, bon, dan setoran akhir.</div>
                <div class="box-footer"><button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_outlet_penjualan"><i class="fa fa-bar-chart"></i> Lihat Laporan</button></div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="box box-warning">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-tags"></i> Laporan Rafaksi</h3></div>
                <div class="box-body small">Penjualan produk pada periode rafaksi dengan diskon per unit dan total diskon.</div>
                <div class="box-footer"><button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_outlet_rafaksi"><i class="fa fa-bar-chart"></i> Lihat Laporan</button></div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="box box-danger">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-exchange"></i> Laporan Retur</h3></div>
                <div class="box-body small">Menampilkan transaksi retur serta barang yang diretur dan barang penggantinya.</div>
                <div class="box-footer"><button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_outlet_retur"><i class="fa fa-bar-chart"></i> Lihat Laporan</button></div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="box box-info">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-cubes"></i> Laporan All Stock</h3></div>
                <div class="box-body small">Stock berdasarkan barcode produk, bukan SKU, termasuk HPP, HPP + Pajak, dan rekap persediaan.</div>
                <div class="box-footer"><button class="btn btn-sm btn-default btn-block" data-toggle="modal" data-target="#modal_outlet_all_stock"><i class="fa fa-bar-chart"></i> Lihat Laporan</button></div>
            </div>
        </div>
    </div>

    @php
        $reports = [
            ['id' => 'outlet_minimal_stock', 'title' => 'Minimal Stock & Alokasi PO', 'pdf' => 'laporan.pdf.outlet.minimal-stock', 'xls' => 'laporan.outlet.minimal-stock', 'date' => false, 'outlet' => true, 'cashier' => false, 'rafaksi' => false],
            ['id' => 'outlet_penjualan', 'title' => 'Penjualan Outlet', 'pdf' => 'laporan.pdf.outlet.penjualan', 'xls' => 'laporan.outlet.penjualan', 'date' => true, 'outlet' => true, 'cashier' => true, 'rafaksi' => false],
            ['id' => 'outlet_rafaksi', 'title' => 'Rafaksi Outlet', 'pdf' => 'laporan.pdf.outlet.rafaksi', 'xls' => 'laporan.outlet.rafaksi', 'date' => true, 'outlet' => true, 'cashier' => false, 'rafaksi' => true],
            ['id' => 'outlet_retur', 'title' => 'Retur Outlet', 'pdf' => 'laporan.pdf.outlet.retur', 'xls' => 'laporan.outlet.retur', 'date' => true, 'outlet' => true, 'cashier' => false, 'rafaksi' => false],
            ['id' => 'outlet_all_stock', 'title' => 'All Stock Outlet', 'pdf' => 'laporan.pdf.outlet.all-stock', 'xls' => 'laporan.outlet.all-stock', 'date' => false, 'outlet' => true, 'cashier' => false, 'rafaksi' => false],
        ];
    @endphp

    @foreach ($reports as $report)
        <div id="modal_{{ $report['id'] }}" class="modal fade" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">{{ $report['title'] }}</h4>
                    </div>
                    <div class="modal-body">
                        @if ($report['date'])
                            <div class="row">
                                <div class="col-sm-6 form-group"><label>Tanggal Mulai</label><input type="date" id="mulai_{{ $report['id'] }}" class="form-control" value="{{ now()->startOfMonth()->format('Y-m-d') }}"></div>
                                <div class="col-sm-6 form-group"><label>Tanggal Selesai</label><input type="date" id="selesai_{{ $report['id'] }}" class="form-control" value="{{ now()->format('Y-m-d') }}"></div>
                            </div>
                        @endif
                        @if ($report['outlet'])
                            <div class="form-group"><label>Outlet</label><select id="outlet_{{ $report['id'] }}" class="form-control"><option value="">Semua Outlet</option>@foreach ($outlets as $outlet)<option value="{{ $outlet->id }}">{{ $outlet->name }}</option>@endforeach</select></div>
                        @endif
                        @if ($report['cashier'])
                            <div class="form-group"><label>Akun Kasir</label><select id="kasir_{{ $report['id'] }}" class="form-control"><option value="">Semua Kasir</option>@foreach ($cashiers as $cashier)<option value="{{ $cashier->id }}">{{ $cashier->name }}</option>@endforeach</select></div>
                        @endif
                        @if ($report['rafaksi'])
                            <div class="form-group"><label>Periode Rafaksi</label><select id="promotion_{{ $report['id'] }}" class="form-control"><option value="">Semua Rafaksi</option>@foreach ($rafaksis as $rafaksi)<option value="{{ $rafaksi->id }}">{{ $rafaksi->name }} ({{ optional($rafaksi->start_at)->format('d/m/Y') ?? '-' }} - {{ optional($rafaksi->end_at)->format('d/m/Y') ?? '-' }})</option>@endforeach</select></div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                        <button type="button" class="btn btn-success btn-preview-outlet-pdf" data-id="{{ $report['id'] }}" data-url="{{ route($report['pdf']) }}"><i class="fa fa-print"></i> Cetak / Preview PDF</button>
                        <a href="{{ route($report['xls']) }}" class="btn btn-primary btn-export-outlet-xls" data-id="{{ $report['id'] }}" data-url="{{ route($report['xls']) }}"><i class="fa fa-file-excel-o"></i> Export XLSX</a>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</section>
@endsection

@section('page-script')
<script>
$(function () {
    function buildUrl(baseUrl, id) {
        var params = {};
        var mulai = $('#mulai_' + id).val();
        var selesai = $('#selesai_' + id).val();
        var outlet = $('#outlet_' + id).val();
        var kasir = $('#kasir_' + id).val();
        var promotion = $('#promotion_' + id).val();
        if (mulai) params.tanggal_mulai = mulai;
        if (selesai) params.tanggal_selesai = selesai;
        if (outlet) params.outlet_id = outlet;
        if (kasir) params.kasir_id = kasir;
        if (promotion) params.promotion_id = promotion;
        var query = $.param(params);
        return query ? baseUrl + '?' + query : baseUrl;
    }

    $(document).on('click', '.btn-preview-outlet-pdf', function () {
        window.open(buildUrl($(this).data('url'), $(this).data('id')), '_blank');
    });
    $(document).on('click', '.btn-export-outlet-xls', function (event) {
        event.preventDefault();
        window.location.href = buildUrl($(this).data('url'), $(this).data('id'));
    });
});
</script>
@endsection
