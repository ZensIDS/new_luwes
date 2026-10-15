@extends('layouts.master')

@section('title', 'Belanja Langsung → Stock Toko')

@section('container')
<section class="content-header"><h1>Belanja Langsung <small>Penambahan Stock Toko</small></h1></section>
<section class="content"><div class="box box-primary">
    <div class="box-header">
        <div class="alert alert-info">
            Gunakan halaman ini hanya jika outlet membeli barang langsung dari supplier.
            Barang dari Gudang tidak diinput di sini; gunakan <strong>Delivery Order (Outbound)</strong>.
            Alurnya: <strong>Tambah</strong> (langsung jadi <strong>Draft</strong>, semua isian tersimpan otomatis)
            &rarr; <strong>Selesai</strong> (status <strong>Menunggu Penerimaan</strong>, masih bisa diedit)
            &rarr; <strong>Lihat</strong>, isi qty yang datang dan tanggal kadaluarsa, lalu selesaikan penerimaan
            (status <strong>Completed</strong>).
            Tombol <em>Cek Barang</em> di halaman isian membantu memilih produk yang stoknya kurang,
            dan tombol <em>Print</em> mencetak dokumen PO outlet.
            <strong>Stock Toko</strong> dan kartu stok baru bertambah setelah penerimaan barang diselesaikan.
        </div>
        <a href="{{ route('owner-stocks.index') }}" class="btn btn-default"><i class="fa fa-cubes"></i> Lihat Stock Toko</a>
        <a href="{{ route('outlet-purchases.create') }}" id="btn-add-purchase" class="btn btn-success"><i class="fa fa-plus"></i> Tambah Belanja Langsung</a>
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
            <div class="col-md-2 col-sm-6">
                <label for="filterStatus">Status</label>
                <select id="filterStatus" class="form-control input-sm">
                    <option value="">Semua Status</option>
                    <option value="draft">Draft</option>
                    <option value="pending">Menunggu Penerimaan</option>
                    <option value="received">Completed</option>
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
                <th>Produk</th><th>Subtotal</th><th>Status</th><th width="200">Aksi</th>
            </tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div></section>

<div class="modal fade" id="modalPilihOutlet" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document"><div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Pilih Outlet</h4>
        </div>
        <div class="modal-body">
            <p class="text-muted">Outlet tidak bisa diganti setelah draft dibuat. Jika salah pilih, hapus draft lalu tambah lagi.</p>
            <select id="modalOutlet" class="form-control">
                <option value="">Pilih outlet</option>
                @foreach ($outlets as $outlet)
                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-success" id="btnLanjutOutlet">Lanjut</button>
        </div>
    </div></div>
</div>
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
                data: function (d) {
                    d.outlet_id = $('#filterOutlet').val() || '';
                    d.status = $('#filterStatus').val() || '';
                }
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

        $('#filterOutlet, #filterStatus').on('change', function () { table.ajax.reload(); });

        // Tambah: outlet mengikuti filter outlet; bila filter "Semua Outlet" (admin/owner) pilih outlet dulu.
        var createUrl = '{{ route('outlet-purchases.create') }}';
        $('#btn-add-purchase').on('click', function (e) {
            e.preventDefault();
            var outletId = $('#filterOutlet').val();
            if (outletId) {
                window.location.href = createUrl + '?outlet_id=' + encodeURIComponent(outletId);
                return;
            }
            $('#modalOutlet').val('');
            $('#modalPilihOutlet').modal('show');
        });
        $('#btnLanjutOutlet').on('click', function () {
            var outletId = $('#modalOutlet').val();
            if (!outletId) { alert('Pilih outlet terlebih dahulu.'); return; }
            window.location.href = createUrl + '?outlet_id=' + encodeURIComponent(outletId);
        });

        // Hapus draft (hanya draft yang punya tombol ini; server menolak status lain).
        $(document).on('click', '.btn-delete-draft', function () {
            var code = $(this).data('code');
            if (!confirm('Hapus draft ' + code + '? Tindakan ini tidak bisa dibatalkan.')) { return; }
            var $form = $('<form method="POST" style="display:none"></form>').attr('action', $(this).data('url'));
            $form.append($('<input type="hidden" name="_token">').val('{{ csrf_token() }}'));
            $form.append('<input type="hidden" name="_method" value="DELETE">');
            $('body').append($form);
            $form.trigger('submit');
        });

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