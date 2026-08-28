@extends('layouts.master')

@section('title', 'Purchase Order')

@section('container')
    <section class="content-header">
        <h1>Purchase Order <small>Gudang → Supplier</small></h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        @if (auth()->user()->role !== 'owner')
                            <a href="{{ route('pembelian.create') }}" class="btn btn-md bg-green">
                                <i class="fa fa-plus"></i> Buat PO Baru
                            </a>
                        @endif
                    </div>
                    <div class="box-body table-responsive">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th width="40">No</th>
                                    <th>Kode PO</th>
                                    <th>Tanggal</th>
                                    <th>Supplier</th>
                                    <th>Items</th>
                                    <th width="120">Status PO</th>
                                    <th width="200">Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
@section('page-script')
<script>
    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#example1')) {
            $('#example1').DataTable().destroy();
        }

        var table = $('#example1').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route('pembelian.index.data') }}'
            },
            order: [[2, 'desc']], // Tanggal, terbaru dulu (setara ->latest() sebelumnya)
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                {
                    data: 'code',
                    name: 'pembelians.code',
                    render: function(data) {
                        return '<strong>' + data + '</strong>';
                    }
                },
                { data: 'tanggal', name: 'pembelians.created_at' },
                { data: 'supplier', name: 'suppliers.name' },
                { data: 'items_html', orderable: false, searchable: false },
                { data: 'status_html', name: 'pembelians.is_published', orderable: false },
                { data: 'aksi_html', orderable: false, searchable: false },
            ]
        });

        $(document).on('click', '.btn-toggle-items', function () {
            var id = $(this).data('target');
            var state = $(this).data('state');
            var $extra = $('.extra-item-' + id);
            var $badge = $(this).find('.label');

            if (state === 'closed') {
                $extra.show();
                $badge.text('Tutup');
                $(this).data('state', 'open');
            } else {
                $extra.hide();
                $badge.text('Selengkapnya (' + $extra.length + ')');
                $(this).data('state', 'closed');
            }
        });
    });
</script>
@endsection
