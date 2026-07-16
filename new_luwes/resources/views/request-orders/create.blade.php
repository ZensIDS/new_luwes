@extends('layouts.master')

@section('title', 'Tambah Request Order')

@section('container')
    <section class="content">
        <div class="row">
            <!-- left column -->
            <div class="col-md-12">
                <!-- general form elements -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Tambah Request Order</h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <form action="{{ route('request-orders.store') }}" method="POST">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label>Owner/Outlet <span class="text-danger">*</span></label>
                                <select name="owner_id" class="form-control select2" required>
                                    <option value="">Select Outlet</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Request Date <span class="text-danger">*</span></label>
                                <input type="date" name="request_date" class="form-control"
                                    value="{{ now()->format('Y-m-d') }}" required>
                            </div>

                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="notes" class="form-control" rows="3"></textarea>
                            </div>

                            <hr>
                            <h4>Pilih Produk</h4>
                            <div class="table-responsive text-nowrap">
                                <table class="table table-bordered" id="items-table">
                                    <thead>
                                        <tr>
                                            <th width="40">No</th>
                                            <th>Product</th>
                                            <th>Available Qty</th>
                                            <th>Konversi</th>
                                            <th>Qty Request</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="item-row">
                                            <td class="row-number text-center text-muted">1</td>
                                            <td>
                                                <select name="items[0][product_id]" class="form-control product-select select2" required>
                                                    <option value="">Select Product</option>
                                                    @foreach ($products as $product)
                                                        <option value="{{ $product->id }}"
                                                            data-available="{{ $product->total_available }}"
                                                            data-konversi="{{ $product->konversi_qty }}"
                                                            data-satuan-besar="{{ $product->satuan_besar }}"
                                                            data-satuan="{{ $product->satuan }}">
                                                            {{ $product->code }} - {{ $product->name }} : {{ $product->total_available }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="available-qty">-</td>
                                            <td class="konversi-info">-</td>
                                            <td>
                                                <input type="number" name="items[0][qty_requested]" class="form-control" min="1" required>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm remove-row"><i class="fa fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-success" id="add-row"><i class="fa fa-plus"></i> Add Product</button>
                            <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#modalCekProduk">
                                <i class="fa fa-search"></i> Cek Produk
                            </button>

                            <hr>
                            <h4>Sample</h4>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="notes-table">
                                    <thead>
                                        <tr>
                                            <th>Kategori</th>
                                            <th style="width:120px">Qty Sample</th>
                                            <th>Nama PJ</th>
                                            <th style="width:60px">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="notes-tbody">
                                        {{-- rows added dynamically --}}
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-default btn-sm" id="add-note-row">
                                <i class="fa fa-plus"></i> Tambah Sample
                            </button>
                        </div>

                        <div class="box-footer">
                            <a href="{{ route('request-orders.index') }}" class="btn btn-default">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div><!-- /.box -->
            </div>
        </div>
    </section>

    {{-- Modal Cek Produk --}}
    <div class="modal fade" id="modalCekProduk" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title"><i class="fa fa-search"></i> Pilih Produk</h4>
                </div>
                <div class="modal-body">
                    <table id="tableCekProduk" class="table table-bordered table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th width="30"><input type="checkbox" id="checkAllProduk"></th>
                                <th>Kode</th>
                                <th>Nama Produk</th>
                                <th>Tersedia</th>
                            </tr>
                        </thead>
                        <tbody id="cekProdukBody"></tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" id="btnTambahkanProduk">
                        <i class="fa fa-check"></i> Tambahkan ke Request
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('page-script')
    <script>
        let products = @json($products);
        let rowIndex = {{ isset($requestOrder) ? count($requestOrder->items) : 1 }};
        let categories = @json($categories);

        function konversiDisplay(qty, konversiQty, satuanBesar, satuan) {
            satuan = satuan || 'PCS';
            qty = parseInt(qty) || 0;
            if (!konversiQty || !satuanBesar) return null;
            var boxes = Math.floor(qty / konversiQty);
            var rem = qty % konversiQty;
            if (rem === 0) return boxes + ' ' + satuanBesar;
            if (boxes > 0) return boxes + ' ' + satuanBesar + ' ' + rem + ' ' + satuan;
            return qty + ' ' + satuan;
        }

        function updateRowNumbers() {
            $('#items-table tbody tr.item-row').each(function(index) {
                $(this).find('.row-number').text(index + 1);
            });
        }

        function populateProductSelect($select, selectedId = null) {
            $select.empty().append('<option value="">Select Product</option>');
            $.each(products, function(index, product) {
                let available = product.total_available || 0;
                let option = $('<option>', {
                    value: product.id,
                    'data-available': available,
                    'data-konversi': product.konversi_qty || 0,
                    'data-satuan-besar': product.satuan_besar || '',
                    'data-satuan': product.satuan || 'PCS',
                    text: product.code + ' - ' + product.name + ' : ' + available
                });
                $select.append(option);
            });
            if (selectedId) {
                $select.val(selectedId).trigger('change');
            }
        }

        $(document).ready(function() {
            $('.product-select').each(function() {
                let $select = $(this);
                let currentVal = $select.val();
                populateProductSelect($select, currentVal);
                $select.select2({ width: '100%' });
            });

            updateRowNumbers();
        });

        $(document).on('change', '.product-select', function() {
            let $row      = $(this).closest('tr');
            let productId = $(this).val();
            if (!productId) return;

            let available   = $(this).find(':selected').data('available') || 0;
            let product     = products.find(function(p) { return p.id == productId; });
            let k           = product ? konversiDisplay(available, product.konversi_qty, product.satuan_besar, product.satuan) : null;
            let konversiQty = product?.konversi_qty || 0;
            let satuanBesar = product?.satuan_besar || '';
            let satuan      = product?.satuan || 'PCS';

            $row.find('.available-qty').html(
                available + (k ? ' <span class="label label-info">' + k + '</span>' : '')
            );

            if (konversiQty && satuanBesar) {
                $row.find('.konversi-info').html(`1 ${satuanBesar} = ${konversiQty} ${satuan}`);
            } else {
                $row.find('.konversi-info').html('-');
            }

            $row.find('input[name*="qty_requested"]').attr('max', available);
        });

        $('#add-row').click(function() {
            let newRow = `
                <tr class="item-row">
                    <td class="row-number text-center text-muted"></td>
                    <td>
                        <select name="items[${rowIndex}][product_id]" class="form-control product-select" required style="width:100%;">
                            <option value="">Select Product</option>
                        </select>
                    </td>
                    <td class="available-qty">-</td>
                    <td class="konversi-info">-</td>
                    <td>
                        <input type="number" name="items[${rowIndex}][qty_requested]" class="form-control" min="1" required>
                    </td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm remove-row"><i class="fa fa-trash"></i></button>
                    </td>
                </tr>
            `;
            $('#items-table tbody').append(newRow);
            let $newSelect = $('#items-table tbody tr:last .product-select');
            populateProductSelect($newSelect);
            $newSelect.select2({ width: '100%' });
            rowIndex++;
            updateRowNumbers();
        });

        $(document).on('click', '.remove-row', function() {
            if ($('.item-row').length > 1) {
                $(this).closest('tr').remove();
                updateRowNumbers();
            }
        });

        // ---- Catatan Tambahan repeater ----
        let noteIndex = 0;

        function addNoteRow() {
            let options = categories.map(c => `<option value="${c.name}">${c.name}</option>`).join('');
            const row = `
                <tr class="note-row">
                    <td>
                        <select name="extra_notes[${noteIndex}][kategori]" class="form-control kategori-select" required style="width:100%;">
                            <option value="">Pilih Kategori</option>
                            ${options}
                        </select>
                    </td>
                    <td><input type="number" name="extra_notes[${noteIndex}][qty]" class="form-control" min="0" value="0" required></td>
                    <td><input type="text" name="extra_notes[${noteIndex}][nama_pj]" class="form-control" placeholder="Nama PJ"></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-sm remove-note-row"><i class="fa fa-trash"></i></button>
                    </td>
                </tr>`;
            $('#notes-tbody').append(row);
            $('#notes-tbody tr:last .kategori-select').select2({ width: '100%' });
            noteIndex++;
        }

        $('#add-note-row').on('click', addNoteRow);

        $(document).on('click', '.remove-note-row', function() {
            $(this).closest('tr').remove();
        });

        // ---- Modal Cek Produk ----
        var cekProdukTable = null;

        $('#modalCekProduk').on('show.bs.modal', function () {
            if (cekProdukTable) {
                cekProdukTable.destroy();
                $('#cekProdukBody').empty();
            }

            var rows = products.map(function (p) {
                var available = p.total_available || 0;
                return '<tr data-product-id="' + p.id + '" data-available="' + available + '">'
                    + '<td class="text-center"><input type="checkbox" class="chk-produk"></td>'
                    + '<td>' + p.code + '</td>'
                    + '<td>' + p.name + '</td>'
                    + '<td>' + available + '</td>'
                    + '</tr>';
            });
            $('#cekProdukBody').html(rows.join(''));

            cekProdukTable = $('#tableCekProduk').DataTable({
                order: [[2, 'asc']],
                columnDefs: [{ orderable: false, targets: 0 }],
                pageLength: 10,
                language: { search: 'Cari:' }
            });
        });

        $('#checkAllProduk').on('change', function () {
            var isChecked = this.checked;
            cekProdukTable.rows().nodes().to$().find('.chk-produk').prop('checked', isChecked);
        });

        $('#btnTambahkanProduk').on('click', function () {
            var checked = [];

            cekProdukTable.rows().nodes().to$().each(function () {
                var $row = $(this);
                if ($row.find('.chk-produk').is(':checked')) {
                    checked.push({ id: $row.data('product-id'), available: $row.data('available') });
                }
            });

            if (checked.length === 0) {
                alert('Pilih minimal satu produk.');
                return;
            }

            checked.forEach(function (p) {
                var prod = products.find(function(pr) { return pr.id == p.id; });
                var kk = prod ? konversiDisplay(p.available, prod.konversi_qty, prod.satuan_besar, prod.satuan) : null;
                var availableDisplay = p.available + (kk ? ' <span class="label label-info">' + kk + '</span>' : '');
                var newRow = '<tr class="item-row">'
                    + '<td class="row-number text-center text-muted"></td>'
                    + '<td><select name="items[' + rowIndex + '][product_id]" class="form-control product-select" required style="width:100%;"></select></td>'
                    + '<td class="available-qty">' + availableDisplay + '</td>'
                    + '<td class="konversi-info">-</td>'
                    + '<td><input type="number" name="items[' + rowIndex + '][qty_requested]" class="form-control" min="1" max="' + p.available + '" required></td>'
                    + '<td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fa fa-trash"></i></button></td>'
                    + '</tr>';
                $('#items-table tbody').append(newRow);

                var $newSelect = $('#items-table tbody tr:last .product-select');
                populateProductSelect($newSelect, p.id);
                $newSelect.select2({ width: '100%' });
                rowIndex++;
            });

            updateRowNumbers();

            $('#modalCekProduk').modal('hide');
            $('#checkAllProduk').prop('checked', false);

            cekProdukTable.rows().nodes().to$().find('.chk-produk').prop('checked', false);
        });
    </script>
@endsection
