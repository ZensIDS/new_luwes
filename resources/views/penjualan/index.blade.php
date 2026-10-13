@extends('layouts.master')

@section('title', 'Penjualan')

@section('container')
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header with-border">
                        @if ($canManageSales)
                        <a href="{{ route('penjualan.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Buat Penjualan Baru</a>
                        @endif
                        <a href="{{ route('owner-stocks.index') }}" class="btn btn-default"><i class="fa fa-cubes"></i> Lihat Stock Toko</a>
                        <a href="{{ route('cashier.history') }}" class="btn btn-info"><i class="fa fa-history"></i> History Kasir</a>
                        <a href="{{ route('refundPenjualan.create') }}" class="btn btn-warning"><i class="fa fa-exchange"></i> Retur / Ganti Barang</a>
                    </div>
                    <div class="box-body" style="padding-bottom:0">
                        <div class="form-inline">
                            <div class="form-group" style="margin-right:15px">
                                <label for="kasir_id" style="margin-right:8px">Akun Kasir</label>
                                <select name="kasir_id" id="kasir_id" class="form-control" style="min-width:260px"
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
                                <label for="daterange" style="margin-right:8px">Tanggal</label>
                                <input type="text" id="daterange" class="form-control" style="min-width:230px" readonly>
                            </div>
                        </div>
                        <p class="help-block" style="margin:8px 0 0">Default menampilkan penjualan hari ini. Ubah rentang tanggal untuk melihat data sebelumnya.</p>
                    </div>
                    <div class="box-body table-responsive text-nowrap">
                        <table id="penjualan-table" class="table table-bordered table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Kode Invoice</th>
                                    <th>Outlet</th>
                                    <th>Kassa (akun)</th>
                                    <th>Nama kasir shift</th>
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
        columns: [
            { orderable: false, searchable: false,
              render: function (d, t, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; } },
            null,
            null,
            { orderable: false },
            { orderable: false },
            { orderable: false },
            { orderable: false, searchable: false },
            { orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'Tidak ada penjualan. Pilih akun kasir dan rentang tanggal untuk menampilkan data.',
            searchPlaceholder: 'Awalan invoice / produk'
        }
    });

    $('#kasir_id').on('change', function () { table.ajax.reload(); });
});
</script>
@endsection