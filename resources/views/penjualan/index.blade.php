@extends('layouts.master')

@section('title', 'Penjualan')

@section('container')
    <!-- Main content -->
    <style>
        .pj-filter { display:flex; flex-wrap:wrap; gap:12px 20px; align-items:flex-end; }
        .pj-filter .form-group { margin:0; }
        .pj-filter label { display:block; margin-bottom:4px; font-weight:600; font-size:12px; color:#555; }
        .pj-filter .form-control { min-width:240px; }
        .pj-hint { margin:10px 0 0; font-size:12px; color:#777; }
        #penjualan-table td { vertical-align:middle; }
        #penjualan-table td.pj-total { text-align:right; font-weight:600; white-space:nowrap; }
        #penjualan-table tr.pj-open > td { background:#f4f8fb; }
        #penjualan-table td.pj-child { padding:8px 12px 12px; background:#fafbfc; }
        #penjualan-table td.pj-child .table { margin-bottom:0; background:#fff; }
        .pj-actions .btn-group { white-space:nowrap; }
    </style>
    <section class="content-header">
        <h1>Penjualan <small>Riwayat penjualan per akun kasir</small></h1>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        @if ($canManageSales)
                        <a href="{{ route('penjualan.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Buat Penjualan Baru</a>
                        @endif
                        <a href="{{ route('refundPenjualan.create') }}" class="btn btn-warning"><i class="fa fa-exchange"></i> Retur / Ganti Barang</a>
                        <a href="{{ route('cashier.history') }}" class="btn btn-info"><i class="fa fa-history"></i> History Kasir</a>
                        <a href="{{ route('owner-stocks.index') }}" class="btn btn-default"><i class="fa fa-cubes"></i> Lihat Stock Toko</a>
                    </div>
                    <div class="box-body" style="padding-bottom:0">
                        <div class="pj-filter">
                            <div class="form-group">
                                <label for="kasir_id">Akun Kasir</label>
                                <select name="kasir_id" id="kasir_id" class="form-control"
                                    {{ $isCashier ? 'disabled' : '' }}>
                                    @unless ($isCashier)
                                        <option value="">-- Pilih akun kasir --</option>
                                    @endunless
                                    @foreach ($cashiers as $cashier)
                                        <option value="{{ $cashier->id }}" {{ (int) $selectedCashierId === $cashier->id ? 'selected' : '' }}>
                                            {{ $cashier->name }}@if ($cashier->outlet) — {{ $cashier->outlet->name }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="daterange">Tanggal</label>
                                <input type="text" id="daterange" class="form-control" readonly>
                            </div>
                        </div>
                        <p class="pj-hint">Default menampilkan penjualan hari ini. Ubah rentang tanggal untuk melihat data sebelumnya. Klik <strong>Detail</strong> pada baris untuk melihat rincian barang.</p>
                    </div>
                    <div class="box-body table-responsive text-nowrap">
                        <table id="penjualan-table" class="table table-bordered table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Kode Invoice</th>
                                    <th>Outlet</th>
                                    <th>Kassa (akun)</th>
                                    <th>Kasir shift</th>
                                    <th class="text-right">Total</th>
                                    <th>Detail</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div><!-- /.box-body -->
                </div><!-- /.box -->
            </div><!-- /.col -->
        </div><!-- /.row -->
    </section><!-- /.content -->
@endsection

@section('page-script')
@include('penjualan._delete-script')
<script>
$(function () {
    var dateFrom = moment().format('YYYY-MM-DD');
    var dateTo = dateFrom;
    var table;

    function setRange(start, end) {
        dateFrom = start.format('YYYY-MM-DD');
        dateTo = end.format('YYYY-MM-DD');
        $('#daterange').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
    }

    // daterangepicker bawaan AdminLTE (v1.x) membaca `format` di level atas, v2+ di `locale.format`: kirim keduanya.
    $('#daterange').daterangepicker({
        format: 'DD/MM/YYYY',
        separator: ' - ',
        startDate: moment(),
        endDate: moment(),
        maxDate: moment(),
        locale: { format: 'DD/MM/YYYY', separator: ' - ', applyLabel: 'Terapkan', cancelLabel: 'Batal' },
        ranges: {
            'Hari ini': [moment(), moment()],
            'Kemarin': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            '7 hari terakhir': [moment().subtract(6, 'days'), moment()],
            'Bulan ini': [moment().startOf('month'), moment()],
            'Bulan lalu': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    }, function (start, end) {
        setRange(start, end);
        table.ajax.reload();
    });
    setRange(moment(), moment());

    // Server-side: hanya satu halaman yang dikirim ke browser (aman untuk ratusan ribu penjualan).
    // Search berbasis awalan kode invoice / kode-nama produk / serial number, diproses di server.
    table = $('#penjualan-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('penjualan.index.data') }}',
            data: function (d) {
                d.kasir_id = $('#kasir_id').val() || '';
                d.date_from = dateFrom;
                d.date_to = dateTo;
            }
        },
        order: [[1, 'desc']], // tanggal terbaru dulu
        pageLength: 25,
        searchDelay: 400,
        autoWidth: false,
        lengthMenu: [10, 25, 50, 100],
        columns: [
            { orderable: false, searchable: false, className: 'text-center',
              render: function (d, t, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; } },
            null,
            null,
            { orderable: false },
            { orderable: false },
            { orderable: false },
            { orderable: false, searchable: false, className: 'pj-total' },
            { orderable: false, searchable: false, visible: false }, // rincian barang: tampil lewat tombol Detail
            { orderable: false, searchable: false, className: 'pj-actions' }
        ],
        language: {
            emptyTable: 'Tidak ada penjualan. Pilih akun kasir dan rentang tanggal untuk menampilkan data.',
            zeroRecords: 'Tidak ada penjualan yang cocok.',
            processing: 'Memuat...',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ baris',
            info: 'Menampilkan _START_ - _END_ dari _TOTAL_ penjualan',
            infoEmpty: 'Tidak ada penjualan',
            infoFiltered: '(disaring dari _MAX_ penjualan)',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' },
            searchPlaceholder: 'Awalan invoice / produk'
        }
    });

    // Rincian barang per baris: dibuka/ditutup lewat tombol Detail (data sudah ikut dikirim server).
    $('#penjualan-table tbody').on('click', '.btn-toggle-detail', function () {
        var $tr = $(this).closest('tr');
        var row = table.row($tr);
        var $icon = $(this).find('i');

        if (row.child.isShown()) {
            row.child.hide();
            $tr.removeClass('pj-open');
            $icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        } else {
            row.child(row.data()[7], 'pj-child').show();
            $tr.addClass('pj-open');
            $icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        }
    });

    $('#kasir_id').on('change', function () { table.ajax.reload(); });
});
</script>
@endsection