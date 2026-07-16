@extends('layouts.master')
@section('title', 'Stock Opname')
@section('container')
    <section class="content-header">
        <h1>Stock Opname</h1>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-sm-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><strong>STOCK OPNAME</strong></h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-borderless mb-2" style="width: 70%">
                            <tr>
                                <td style="white-space:nowrap">Tanggal Stock Opname</td>
                                <td>
                                    <input type="date" id="tglStockOpname" class="form-control" value="{{ date('Y-m-d') }}" />
                                </td>
                                <td style="white-space:nowrap;padding-left:16px">Lokasi</td>
                                <td>
                                    <select id="filterLokasi" class="form-control select2">
                                        <option value="">-- Semua Lokasi --</option>
                                        @foreach($lokasiOptions as $lok)
                                            <option value="{{ $lok }}">{{ $lok }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td style="white-space:nowrap">Supplier</td>
                                <td colspan="3">
                                    <select id="filterSupplier" class="form-control select2">
                                        <option value="">-- Semua Supplier --</option>
                                        @foreach($supplierOptions as $sup)
                                            <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <div class="table-responsive">
                            <table id="datatable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Barcode</th>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>Satuan</th>
                                        <th>Stock Fisik</th>
                                        <th>Stock di Kartu</th>
                                        <th>Selisih</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody">
                                    <tr>
                                        <td colspan="8" class="text-center">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 d-flex justify-content-between">
                            <button id="tambahBaris" class="btn btn-primary">
                                <i class="fa fa-plus-circle"></i> Tambah Baris
                            </button>
                            <button class="btn btn-success" id="btnSaveOpname">
                                <i class="fa fa-save"></i> Save Stock Opname
                            </button>
                            <a id="btnExportTemplate" href="{{ route('stock.opname.export-template') }}"
                               class="btn btn-sm btn-default">
                                <i class="fa fa-file-excel-o"></i> Export Template
                            </a>
                            <form method="GET" action="{{ route('laporan.stock-opname') }}" style="display:inline;">
                                <input type="hidden" name="tanggal" id="exportTanggal" value="{{ date('Y-m-d') }}" />
                                <input type="hidden" name="lokasi" id="exportLokasi" value="" />
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="fa fa-file-excel-o"></i> Export Laporan
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('page-script')
    <script>
        let allStockData = [];

        $(document).ready(function() {
            loadStockData();

            function loadStockData() {
                const lokasi     = $('#filterLokasi').val();
                const supplierId = $('#filterSupplier').val();
                $.get('{{ route('stock.opname.data') }}', {
                    lokasi:      lokasi,
                    supplier_id: supplierId,
                }, function(data) {
                    allStockData = data.stocks;
                    renderInitialRows();
                }).fail(function() {
                    alert('Gagal memuat data stock');
                });
            }

            function renderInitialRows() {
                const tbody = $('#tableBody');
                tbody.empty();

                allStockData.forEach((item, index) => {
                    const newRow = `
                <tr>
                    <td>${index + 1}</td>
                    <td><input type="text" class="form-control sku" value="${item.product_code}" disabled /></td>
                    <td><input type="text" class="form-control product-name" value="${item.product_name}" data-stock-id="${item.id}" data-product-id="${item.product_id}" disabled /></td>
                    <td><input type="text" class="form-control sku" value="${item.sku}" disabled /></td>
                    <td><input type="text" class="form-control satuan" value="${item.satuan}" disabled /></td>
                    <td><input type="number" step="0.01" class="form-control stock_fisik" value="${item.qty}" /></td>
                    <td><input type="number" class="form-control stock_dikartu" value="${item.qty}" disabled /></td>
                    <td><input type="number" step="0.01" class="form-control selisih" value="0" disabled /></td>
                    <td><input type="text" class="form-control keterangan" value="" /></td>
                </tr>
            `;
                    tbody.append(newRow);
                });

                attachEventListeners();
            }

            function attachEventListeners() {
                $('.stock_fisik').off('input').on('input', function() {
                    const row = $(this).closest('tr');
                    const stockFisik = parseFloat($(this).val()) || 0;
                    const stockKartu = parseFloat(row.find('.stock_dikartu').val()) || 0;
                    const selisih = (stockFisik - stockKartu).toFixed(2);
                    row.find('.selisih').val(selisih);
                });
            }

            function updateNomorUrut() {
                $('#tableBody tr').each(function(index) {
                    $(this).find('td:first').text(index + 1);
                });
            }

            function updateExportTemplateUrl() {
                const lokasi     = $('#filterLokasi').val();
                const supplierId = $('#filterSupplier').val();
                const base       = '{{ route('stock.opname.export-template') }}';

                const params = new URLSearchParams();
                if (lokasi)     params.append('lokasi', lokasi);
                if (supplierId) params.append('supplier_id', supplierId);

                const query = params.toString();
                $('#btnExportTemplate').attr('href', query ? base + '?' + query : base);
            }

            $('#tglStockOpname').on('change', function() {
                $('#exportTanggal').val($(this).val());
            });

            $('#filterLokasi').on('change', function() {
                $('#exportLokasi').val($(this).val());
                updateExportTemplateUrl();
                loadStockData();
            });

            $('#filterSupplier').on('change', function() {
                updateExportTemplateUrl();
                loadStockData();
            });

            $('#tambahBaris').on('click', function(e) {
                e.preventDefault();
                const newRow = `
            <tr>
                <td></td>
                <td>
                    <select class="form-control select-stock">
                        <option value="">-- Pilih Stock --</option>
                        ${allStockData.map(s => `
                                    <option value="${s.id}"
                                            data-product-id="${s.product_id}"
                                            data-sku="${s.sku}"
                                            data-satuan="${s.satuan}"
                                            data-qty="${s.qty}">
                                        ${s.product_name} - SKU: ${s.sku}
                                    </option>
                                `).join('')}
                    </select>
                </td>
                <td><input type="text" class="form-control sku" disabled /></td>
                <td><input type="text" class="form-control satuan" disabled /></td>
                <td><input type="number" step="0.01" class="form-control stock_fisik" value="0" /></td>
                <td><input type="number" class="form-control stock_dikartu" disabled /></td>
                <td><input type="number" step="0.01" class="form-control selisih" disabled /></td>
                <td><input type="text" class="form-control keterangan" /></td>
            </tr>
        `;
                $('#tableBody').append(newRow);
                updateNomorUrut();

                const lastRow = $('#tableBody tr:last');
                lastRow.find('.select-stock').on('change', function() {
                    const selected = $(this).find(':selected');
                    const row = $(this).closest('tr');
                    const stockQty = parseFloat(selected.data('qty')) || 0;

                    row.find('.sku').val(selected.data('sku') || '');
                    row.find('.satuan').val(selected.data('satuan') || '');
                    row.find('.stock_dikartu').val(stockQty);
                    row.find('.stock_fisik').val(stockQty);
                    row.find('.selisih').val(0);
                });

                lastRow.find('.stock_fisik').on('input', function() {
                    const row = $(this).closest('tr');
                    const stockFisik = parseFloat($(this).val()) || 0;
                    const stockKartu = parseFloat(row.find('.stock_dikartu').val()) || 0;
                    row.find('.selisih').val((stockFisik - stockKartu).toFixed(2));
                });
            });

            $('#btnSaveOpname').on('click', function() {
                const tglStockOpname = $('#tglStockOpname').val();
                if (!tglStockOpname) {
                    alert('Tanggal Stock Opname harus diisi!');
                    return;
                }

                const items = [];
                $('#tableBody tr').each(function() {
                    const row          = $(this);
                    const productInput = row.find('.product-name');
                    const selectStock  = row.find('.select-stock');

                    let stockId = productInput.data('stock-id');
                    if (!stockId && selectStock.length) {
                        stockId = selectStock.val();
                    }

                    const selisih     = parseFloat(row.find('.selisih').val()) || 0;
                    const keterangan  = row.find('.keterangan').val().trim();
                    const systemQty   = parseFloat(row.find('.stock_dikartu').val()) || 0;
                    const physicalQty = parseFloat(row.find('.stock_fisik').val()) || 0;

                    if (stockId && selisih !== 0) {
                        items.push({
                            stock_id:     stockId,
                            selisih:      selisih,
                            system_qty:   systemQty,
                            physical_qty: physicalQty,
                            keterangan:   keterangan,
                        });
                    }
                });

                if (items.length === 0) {
                    alert('Tidak ada perubahan stock untuk disimpan!');
                    return;
                }

                if (!confirm(`Simpan ${items.length} penyesuaian stock?`)) {
                    return;
                }

                $.ajax({
                    url: '{{ route('stock.opname.save') }}',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        _token: '{{ csrf_token() }}',
                        adjustment_date: tglStockOpname,
                        items: items
                    }),
                    success: function(data) {
                        if (data.success) {
                            alert('Stock opname berhasil disimpan!');
                            location.reload();
                        } else {
                            alert('Gagal menyimpan: ' + data.message);
                        }
                    },
                    error: function(xhr) {
                        alert('Terjadi kesalahan saat menyimpan data');
                        console.error(xhr.responseText);
                    }
                });
            });
        });
    </script>
@endsection
