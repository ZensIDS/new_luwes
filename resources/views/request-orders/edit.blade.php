@extends('layouts.master')

@section('title', 'Edit Request Order')

@section('container')
    <section class="content">
        <div class="row">
            <!-- left column -->
            <div class="col-md-12">
                <!-- general form elements -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            Edit Request Order — {{ $requestOrder->code }}
                        </h3>
                        <div class="box-tools">
                            <span id="autosave-indicator" class="text-muted small"></span>
                        </div>
                    </div><!-- /.box-header -->
                    <!-- form start: dipakai HANYA untuk tombol "Selesai" di akhir -->
                    <form action="{{ route('request-orders.finish', $requestOrder) }}" method="POST" id="finish-form">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label>Owner/Outlet <span class="text-danger">*</span></label>
                                <select name="owner_id" id="owner_id" class="form-control select2" required>
                                    <option value="">Select Outlet</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}"
                                            {{ $requestOrder->owner_id == $outlet->id ? 'selected' : '' }}>
                                            {{ $outlet->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Request Date <span class="text-danger">*</span></label>
                                <input type="date" name="request_date" id="request_date" class="form-control"
                                    value="{{ optional($requestOrder->request_date)->format('Y-m-d') ?? now()->format('Y-m-d') }}"
                                    required>
                            </div>

                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="notes" id="notes" class="form-control" rows="3">{{ $requestOrder->notes }}</textarea>
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
                                            <th width="90">Status</th>
                                            <th width="60">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($requestOrder->items as $item)
                                            <tr class="item-row" data-item-id="{{ $item->id }}">
                                                <td class="row-number text-center text-muted"></td>
                                                <td>
                                                    <select class="form-control product-select" required style="width:100%;"></select>
                                                </td>
                                                <td class="available-qty">-</td>
                                                <td class="konversi-info">-</td>
                                                <td>
                                                    <input type="number" class="form-control qty-input"
                                                        min="1" value="{{ $item->qty_requested }}" required>
                                                </td>
                                                <td class="text-center row-status"><span class="label label-success">Tersimpan</span></td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm remove-row"><i class="fa fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr class="item-row">
                                                <td class="row-number text-center text-muted">1</td>
                                                <td>
                                                    <select class="form-control product-select" required style="width:100%;"></select>
                                                </td>
                                                <td class="available-qty">-</td>
                                                <td class="konversi-info">-</td>
                                                <td>
                                                    <input type="number" class="form-control qty-input" min="0" value="0" required>
                                                </td>
                                                <td class="text-center row-status"><span class="label label-default">Belum tersimpan</span></td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm remove-row"><i class="fa fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        @endforelse
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
                                            <th style="width:90px">Status</th>
                                            <th style="width:60px">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="notes-tbody">
                                        @foreach ($requestOrder->additionalNotes as $note)
                                            <tr class="note-row" data-note-id="{{ $note->id }}">
                                                <td>
                                                    <select class="form-control kategori-select" required style="width:100%;">
                                                        <option value="">Pilih Kategori</option>
                                                        @foreach ($categories as $c)
                                                            <option value="{{ $c->name }}" {{ $note->kategori == $c->name ? 'selected' : '' }}>
                                                                {{ $c->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td><input type="number" class="form-control qty-note-input" min="0" value="{{ $note->qty }}" required></td>
                                                <td><input type="text" class="form-control nama-pj-input" placeholder="Nama PJ" value="{{ $note->nama_pj }}"></td>
                                                <td class="text-center row-status"><span class="label label-success">Tersimpan</span></td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-danger btn-sm remove-note-row"><i class="fa fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-default btn-sm" id="add-note-row">
                                <i class="fa fa-plus"></i> Tambah Sample
                            </button>
                        </div>

                        <div class="box-footer">
                            <a href="{{ route('request-orders.index') }}" class="btn btn-default">Kembali</a>
                            <button type="submit" class="btn btn-primary">Selesai</button>
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
        const requestOrderId = {{ $requestOrder->id }};
        const csrfToken = '{{ csrf_token() }}';
        let products   = @json($products);
        let categories = @json($categories);

        const routes = {
            autosaveHeader: `/request-orders/${requestOrderId}/autosave-header`,
            autosaveItem:   `/request-orders/${requestOrderId}/items`,
            destroyItem:    (itemId) => `/request-orders/${requestOrderId}/items/${itemId}`,
            autosaveNote:   `/request-orders/${requestOrderId}/notes`,
            destroyNote:    (noteId) => `/request-orders/${requestOrderId}/notes/${noteId}`,
        };

        function showIndicator(message, isError = false) {
            const $ind = $('#autosave-indicator');
            $ind.removeClass('text-danger text-success').addClass(isError ? 'text-danger' : 'text-success');
            $ind.text(message);
            clearTimeout(showIndicator._t);
            showIndicator._t = setTimeout(() => $ind.text(''), 2000);
        }

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

        // ---- init existing rows: match saved product_id from server-rendered data ----
        // Karena select produk di-render kosong (opsi ditambahkan lewat JS), kita perlu
        // sisipkan product_id yang tersimpan lewat data attribute yang di-print dari server.
        const existingItems = @json($requestOrder->items->map(fn($i) => ['item_id' => $i->id, 'product_id' => $i->product_id]));
        const existingNotesSelected = @json($requestOrder->additionalNotes->pluck('kategori', 'id'));

        $(document).ready(function() {
            $('#items-table tbody tr.item-row').each(function(idx) {
                const $row = $(this);
                const itemId = $row.data('item-id');
                const $select = $row.find('.product-select');
                populateProductSelect($select);
                if (itemId) {
                    const match = existingItems.find(i => i.item_id === itemId);
                    if (match) {
                        $select.val(match.product_id).trigger('change');
                    }
                }
                $select.select2({ width: '100%' });
            });

            $('#notes-tbody tr.note-row').each(function() {
                $(this).find('.kategori-select').select2({ width: '100%' });
            });

            updateRowNumbers();
            $('#owner_id').select2();
        });

        // ---- header autosave (debounced) ----
        let headerTimeout;
        function autosaveHeader() {
            clearTimeout(headerTimeout);
            headerTimeout = setTimeout(function() {
                $.ajax({
                    url: routes.autosaveHeader,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: {
                        owner_id: $('#owner_id').val(),
                        request_date: $('#request_date').val(),
                        notes: $('#notes').val(),
                    },
                    success: function() { showIndicator('Tersimpan otomatis ✓'); },
                    error: function() { showIndicator('Gagal menyimpan', true); },
                });
            }, 600);
        }
        $('#owner_id').on('change', autosaveHeader);
        $('#request_date, #notes').on('input change', autosaveHeader);

        // ---- item row autosave ----
        function autosaveRow($row) {
            const productId = $row.find('.product-select').val();
            const qty       = $row.find('.qty-input').val();
            const itemId    = $row.data('item-id') || null;

            if (!productId) return;

            $.ajax({
                url: routes.autosaveItem,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                data: { id: itemId, product_id: productId, qty_requested: qty },
                success: function(res) {
                    $row.data('item-id', res.item_id);
                    $row.attr('data-item-id', res.item_id);
                    $row.find('.row-status').html('<span class="label label-success">Tersimpan</span>');
                    showIndicator('Item tersimpan ✓');
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Gagal menyimpan item';
                    $row.find('.row-status').html('<span class="label label-danger">Gagal</span>');
                    showIndicator(msg, true);
                },
            });
        }

        let qtyDebounce;
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

            $row.find('.qty-input').attr('max', available);

            autosaveRow($row);
        });

        $(document).on('input', '.qty-input', function() {
            let $row = $(this).closest('tr');
            clearTimeout(qtyDebounce);
            qtyDebounce = setTimeout(function() {
                autosaveRow($row);
            }, 600);
        });

        $('#add-row').click(function() {
            let newRow = `
                <tr class="item-row">
                    <td class="row-number text-center text-muted"></td>
                    <td>
                        <select class="form-control product-select" required style="width:100%;">
                            <option value="">Select Product</option>
                        </select>
                    </td>
                    <td class="available-qty">-</td>
                    <td class="konversi-info">-</td>
                    <td>
                        <input type="number" class="form-control qty-input" min="0" value="0" required>
                    </td>
                    <td class="text-center row-status"><span class="label label-default">Belum tersimpan</span></td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm remove-row"><i class="fa fa-trash"></i></button>
                    </td>
                </tr>
            `;
            $('#items-table tbody').append(newRow);
            let $newSelect = $('#items-table tbody tr:last .product-select');
            populateProductSelect($newSelect);
            $newSelect.select2({ width: '100%' });
            updateRowNumbers();
        });

        $(document).on('click', '.remove-row', function() {
            if ($('.item-row').length <= 1) return;

            const $row   = $(this).closest('tr');
            const itemId = $row.data('item-id');

            if (itemId) {
                $.ajax({
                    url: routes.destroyItem(itemId),
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    success: function() {
                        $row.remove();
                        updateRowNumbers();
                        showIndicator('Item dihapus ✓');
                    },
                    error: function() { showIndicator('Gagal menghapus item', true); },
                });
            } else {
                $row.remove();
                updateRowNumbers();
            }
        });

        // ---- Sample / extra_notes repeater ----
        function autosaveNoteRow($row) {
            const kategori = $row.find('.kategori-select').val();
            const qty      = $row.find('.qty-note-input').val();
            const namaPj   = $row.find('.nama-pj-input').val();
            const noteId   = $row.data('note-id') || null;

            if (!kategori) return;

            $.ajax({
                url: routes.autosaveNote,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                data: { id: noteId, kategori: kategori, qty: qty || 0, nama_pj: namaPj },
                success: function(res) {
                    $row.data('note-id', res.note_id);
                    $row.attr('data-note-id', res.note_id);
                    $row.find('.row-status').html('<span class="label label-success">Tersimpan</span>');
                    showIndicator('Sample tersimpan ✓');
                },
                error: function() {
                    $row.find('.row-status').html('<span class="label label-danger">Gagal</span>');
                    showIndicator('Gagal menyimpan sample', true);
                },
            });
        }

        function addNoteRow() {
            let options = categories.map(c => `<option value="${c.name}">${c.name}</option>`).join('');
            const row = `
                <tr class="note-row">
                    <td>
                        <select class="form-control kategori-select" required style="width:100%;">
                            <option value="">Pilih Kategori</option>
                            ${options}
                        </select>
                    </td>
                    <td><input type="number" class="form-control qty-note-input" min="0" value="0" required></td>
                    <td><input type="text" class="form-control nama-pj-input" placeholder="Nama PJ"></td>
                    <td class="text-center row-status"><span class="label label-default">Belum tersimpan</span></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-sm remove-note-row"><i class="fa fa-trash"></i></button>
                    </td>
                </tr>`;
            $('#notes-tbody').append(row);
            $('#notes-tbody tr:last .kategori-select').select2({ width: '100%' });
        }

        $('#add-note-row').on('click', addNoteRow);

        $(document).on('change', '.kategori-select', function() {
            autosaveNoteRow($(this).closest('tr'));
        });

        let noteDebounce;
        $(document).on('input', '.qty-note-input, .nama-pj-input', function() {
            let $row = $(this).closest('tr');
            clearTimeout(noteDebounce);
            noteDebounce = setTimeout(function() {
                autosaveNoteRow($row);
            }, 600);
        });

        $(document).on('click', '.remove-note-row', function() {
            const $row   = $(this).closest('tr');
            const noteId = $row.data('note-id');

            if (noteId) {
                $.ajax({
                    url: routes.destroyNote(noteId),
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    success: function() {
                        $row.remove();
                        showIndicator('Sample dihapus ✓');
                    },
                    error: function() { showIndicator('Gagal menghapus sample', true); },
                });
            } else {
                $row.remove();
            }
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
                    + '<td><select class="form-control product-select" required style="width:100%;"></select></td>'
                    + '<td class="available-qty">' + availableDisplay + '</td>'
                    + '<td class="konversi-info">-</td>'
                    + '<td><input type="number" class="form-control qty-input" min="0" value="0" max="' + p.available + '" required></td>'
                    + '<td class="text-center row-status"><span class="label label-default">Belum tersimpan</span></td>'
                    + '<td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fa fa-trash"></i></button></td>'
                    + '</tr>';
                $('#items-table tbody').append(newRow);

                var $newSelect = $('#items-table tbody tr:last .product-select');
                populateProductSelect($newSelect, p.id);
                $newSelect.select2({ width: '100%' });
            });

            updateRowNumbers();

            $('#modalCekProduk').modal('hide');
            $('#checkAllProduk').prop('checked', false);

            cekProdukTable.rows().nodes().to$().find('.chk-produk').prop('checked', false);
        });
    </script>
@endsection
