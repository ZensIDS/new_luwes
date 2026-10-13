@extends('layouts.master')

@section('title', 'Belanja Langsung → Stock Toko')

@section('container')
<section class="content-header"><h1>Belanja Langsung <small>Penambahan Stock Toko</small></h1></section>
<section class="content"><div class="box box-primary">
    <div class="box-header">
        <div class="alert alert-info">
            Gunakan halaman ini hanya jika outlet membeli barang langsung dari supplier.
            Barang dari Gudang tidak diinput di sini; gunakan <strong>Delivery Order (Outbound)</strong>.
            Setelah disimpan, transaksi berstatus <strong>Menunggu Penerimaan</strong> dan masih bisa diedit.
            <strong>Stock Toko</strong> dan kartu stok baru bertambah setelah penerimaan barang diselesaikan
            (klik <em>Lihat</em>, isi qty yang datang, lalu simpan).
        </div>
        <a href="{{ route('owner-stocks.index') }}" class="btn btn-default"><i class="fa fa-cubes"></i> Lihat Stock Toko</a>
        <a href="{{ route('outlet-purchases.create', ['outlet_id' => $selectedOutletId]) }}" class="btn btn-success"><i class="fa fa-plus"></i> Tambah Belanja Langsung</a>
    </div>
    <div class="box-body">
        <div class="row" style="margin-bottom:10px">
            <div class="col-md-3 col-sm-6">
                <label for="filterOutlet">Outlet</label>
                <select id="filterOutlet" class="form-control input-sm select2"
                    {{ auth()->user()->outlet_id ? 'disabled' : '' }}>
                    <option value="">Semua Outlet</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}" {{ (int) $selectedOutletId === (int) $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-sm-6">
                <label for="filterSearch">Cari</label>
                <input type="text" id="filterSearch" class="form-control input-sm" autocomplete="off"
                    placeholder="Awalan nama/kode produk, POUT, invoice, supplier">
            </div>
        </div>
    </div>
    <div class="box-body table-responsive">
        <table id="outlet-purchases-table" class="table table-bordered table-striped" style="width:100%">
            <thead><tr>
                <th width="40">No</th><th>Kode</th><th>Tanggal</th><th>Outlet</th><th>Supplier</th>
                <th>Produk</th><th>Subtotal</th><th>Status</th><th width="110">Aksi</th>
            </tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div></section>
@endsection
@section('page-script')
<script>
    $(document).ready(function () {
        if ($.fn.DataTable.isDataTable('#outlet-purchases-table')) {
            $('#outlet-purchases-table').DataTable().destroy();
        }

        var table = $('#outlet-purchases-table').DataTable({
            processing: true,
            serverSide: true,
            dom: 'lrtip', // kotak search bawaan disembunyikan, diganti #filterSearch
            ajax: {
                url: '{{ route('outlet-purchases.index.data') }}',
                data: function (d) { d.outlet_id = $('#filterOutlet').val() || ''; }
            },
            order: [[2, 'desc']], // tanggal, terbaru dulu
            pageLength: 25,
            searchDelay: 400,
            columns: [
                { data: null, orderable: false, searchable: false,
                  render: function (data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; } },
                { data: 'code', render: function (d) { return '<strong>' + d + '</strong>'; } },
                { data: 'tanggal' },
                { data: 'outlet' },
                { data: 'supplier' },
                { data: 'items_html', orderable: false, searchable: false },
                { data: 'subtotal' },
                { data: 'status_html' },
                { data: 'aksi_html', orderable: false, searchable: false }
            ]
        });

        $('#filterOutlet').on('change', function () { table.ajax.reload(); });

        var searchTimer = null;
        $('#filterSearch').on('input', function () {
            var value = $(this).val();
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { table.search(value).draw(); }, 400);
        });

        $(document).on('click', '.btn-toggle-items', function () {
            var id = $(this).data('target');
            var $extra = $('.extra-item-' + id);
            var $badge = $(this).find('.label');
            if ($(this).data('state') === 'closed') {
                $extra.show(); $badge.text('Tutup'); $(this).data('state', 'open');
            } else {
                $extra.hide(); $badge.text('Selengkapnya (' + $extra.length + ')'); $(this).data('state', 'closed');
            }
        });
    });
</script>
@endsection