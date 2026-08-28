@extends('layouts.master')

@section('title', 'Penerimaan Barang (Pembelian)')

@section('container')
    <section class="content-header">
        <h1>Penerimaan Barang <small>Pembelian</small></h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <p class="text-muted">
                            <i class="fa fa-info-circle"></i>
                            Pilih PO untuk melakukan input penerimaan barang dari supplier tanpa perlu menunggu ACC pembelian.
                        </p>
                    </div>
                    <div class="box-body table-responsive text-nowrap">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th width="40">No</th>
                                    <th>Kode PO</th>
                                    <th>Kode Pembelian</th>
                                    <th>Supplier</th>
                                    <th>Items</th>
                                    <th width="130">Status Penerimaan</th>
                                    <th width="130">Status PO</th>
                                    <th>Tgl Terima</th>
                                    <th>PIC</th>
                                    <th width="180">Aksi</th>
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
                    url: '{{ route('pembelian.penerimaan.index.data') }}'
                },
                order: [[1, 'desc']], // setara ->latest() sebelumnya (server default juga sudah desc by created_at)
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
                        render: function(data) { return '<strong>' + data + '</strong>'; }
                    },
                    { data: 'code_gr', name: 'pembelians.code_gr' },
                    { data: 'supplier', name: 'suppliers.name' },
                    { data: 'items_html', orderable: false, searchable: false },
                    { data: 'receipt_status_html', name: 'pembelians.receipt_status', orderable: false },
                    { data: 'po_status_html', name: 'pembelians.owner_approval_status', orderable: false },
                    { data: 'receipt_date', name: 'pembelians.receipt_date' },
                    { data: 'receipt_pic', name: 'pembelians.receipt_pic' },
                    { data: 'aksi_html', orderable: false, searchable: false },
                ]
            });

            $(document).on('click', '.btn-toggle-pembelian-items', function () {
                var id = $(this).data('target');
                var state = $(this).data('state');
                var $extra = $('.extra-item-pembelian-' + id);
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
