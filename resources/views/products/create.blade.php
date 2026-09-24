@extends('layouts.master')

@section('title', 'Tambah Product')

@section('container')
    <section class="content">
        <div class="row">
            <!-- left column -->
            <div class="col-md-12">
                <!-- general form elements -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Tambah Product</h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <form action="{{ route('product.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row box-body">
                            <div class="col-md-6 form-group">
                                <label for="">Nama</label>
                                <input type="text" class="form-control" name="name" value="{{ old('name') }}"
                                    placeholder="Masukkan Nama">
                                @error('name')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="">Warna</label>
                                <input type="text" class="form-control" name="warna" value="{{ old('warna') }}"
                                    placeholder="Masukkan Warna">
                                @error('warna')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="">Barcode</label>
                                <input type="text" class="form-control" name="code" value="{{ old('code') }}"
                                    placeholder="Masukkan Barcode">
                                @error('code')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="">Ukuran</label>
                                <input type="text" class="form-control" name="ukuran" value="{{ old('ukuran') }}"
                                    placeholder="Masukkan Ukuran">
                                @error('ukuran')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <!-- Satuan Besar (Konversi) -->
                            <div class="col-md-6 form-group">
                                <label for="">Satuan Besar </label>
                                <input type="text" class="form-control" name="satuan_besar"
                                    value="{{ old('satuan_besar') }}" placeholder="karton / box / lusin">
                                @error('satuan_besar')
                                    <div class="invalid-feedback text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="">Brand</label>
                                <input type="text" class="form-control" name="brand" value="{{ old('brand') }}"
                                    placeholder="Masukkan Brand">
                                @error('brand')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <!-- Satuan -->
                            <div class="col-md-6 form-group">
                                <label for="">Satuan</label>
                                <input type="text" class="form-control" name="satuan"
                                    value="{{ old('satuan', $product->satuan ?? '') }}" placeholder="Contoh: Pcs, Box, Kg">
                                @error('satuan')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="">Model</label>
                                <input type="text" class="form-control" name="model" value="{{ old('model') }}"
                                    placeholder="Masukkan Model">
                                @error('model')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="">Isi Konversi <small class="text-danger">(Contoh: 1 karton = 12 pcs)</small></label>
                                <input type="text" class="form-control integer-only-input" name="konversi_qty"
                                    value="{{ old('konversi_qty') }}" inputmode="numeric"
                                    pattern="[0-9]*" placeholder="12" autocomplete="off">
                                @error('konversi_qty')
                                    <div class="invalid-feedback text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="">Harga Beli</label>
                                <input type="text" class="form-control" name="harga_beli" value="{{ old('harga_beli', 0) }}"
                                    placeholder="Masukkan Harga Beli">
                                @error('harga_beli')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Category</label>
                                <select class="form-control select2" name="category_id" data-placeholder="Pilih Category"
                                    style="width: 100%;" required>
                                    <option value="" selected disabled>Pilih Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}"
                                            {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- <div class="col-md-6 form-group"> --}}
                                {{-- <label for="">Harga Jual</label> --}}
                                {{-- <input type="text" class="form-control" name="harga_jual" --}}
                                    {{-- value="{{ old('harga_jual') }}" placeholder="Masukkan Harga Jual"> --}}
                                {{-- @error('harga_jual') --}}
                                    {{-- <div class="invalid-feedback text-danger"> --}}
                                        {{-- {{ $message }} --}}
                                    {{-- </div> --}}
                                {{-- @enderror --}}
                            {{-- </div> --}}
                            <!-- Lokasi -->
                            <div class="col-md-6 form-group">
                                <label for="">Lokasi</label>
                                <input type="text" class="form-control" name="lokasi"
                                    value="{{ old('lokasi', $product->lokasi ?? '') }}"
                                    placeholder="Contoh: Rak A, Gudang 1">
                                @error('lokasi')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <!-- Multiple Select Supplier -->
                            <div class="col-md-6 form-group">
                                <label>Supplier</label>
                                <select class="form-control select2" name="supplier_ids[]" multiple
                                    data-placeholder="Pilih Supplier" style="width: 100%;">
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}"
                                            {{ in_array($supplier->id, old('supplier_ids', $selectedSuppliers ?? [])) ? 'selected' : '' }}>
                                            {{ $supplier->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('supplier_ids')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                                @error('supplier_ids.*')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <!-- Minimum Stock -->
                            <div class="col-md-6 form-group">
                                <label for="">Minimum Stock</label>
                                <input type="text" class="form-control integer-only-input" name="min_stock"
                                    value="{{ old('min_stock', $product->min_stock ?? 0) }}" min="0"
                                    placeholder="0">
                                @error('min_stock')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Status Produk</label>
                                <select class="form-control" name="status_produk" id="status-produk-select">
                                    @foreach ($statusProdukOptions as $statusValue => $statusLabel)
                                        <option value="{{ $statusValue }}"
                                            {{ old('status_produk', 'sudah') === $statusValue ? 'selected' : '' }}>
                                            {{ $statusLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status_produk')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="col-md-6 form-group" id="status-note-group" style="display:none;">
                                <label>Catatan Status</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="status-note-display"
                                        value="{{ old('status_produk_note') }}" readonly
                                        placeholder="Belum ada catatan tambahan diskon">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-default" data-toggle="modal"
                                            data-target="#statusProdukModal">
                                            Input Catatan
                                        </button>
                                    </span>
                                </div>
                                <input type="hidden" name="status_produk_note" id="status-note-hidden"
                                    value="{{ old('status_produk_note') }}">
                                <span class="help-block">Khusus tambahan diskon, isi nilai/catatan tambahannya.</span>
                                @error('status_produk_note')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        </div><!-- /.box-body -->

                        <div class="box-footer">
                            <a href="{{ route('product.index') }}" class="btn btn-default">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>

                    <div class="modal fade" id="statusProdukModal" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-sm" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    <h4 class="modal-title">Catatan Tambahan Diskon</h4>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label>Isi tambahan</label>
                                        <input type="text" class="form-control" id="status-note-input-modal"
                                            value="{{ old('status_produk_note') }}"
                                            placeholder="Contoh: tambah 10.000 atau diskon 5%">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                                    <button type="button" class="btn btn-primary" id="btn-save-status-note" data-dismiss="modal">Simpan Catatan</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /.box -->
            </div>
        </div>
    </section>
@endsection
@section('page-script')
    <script>
       $(document).on('keydown', '.integer-only-input', function (e) {
            if ([190, 188, 69, 110].includes(e.keyCode)) {
                e.preventDefault();
            }
        });
        
        $(document).on('input', '.integer-only-input', function () {
            let cleaned = $(this).val().replace(/[^0-9]/g, '');
            if ($(this).val() !== cleaned) {
                $(this).val(cleaned);
            }
        });
    </script>
    <script>
        $(document).ready(function() {
            // Initialize select2 for multiple select
            $('.select2').select2({
                placeholder: "Pilih Supplier",
                allowClear: true
            });
            var statusProdukModal = $('#statusProdukModal');

            function toggleStatusNote(forceOpen) {
                var isTambahanDiskon = $('#status-produk-select').val() === 'tambahan_diskon';
                $('#status-note-group').toggle(isTambahanDiskon);

                if (!isTambahanDiskon) {
                    $('#status-note-hidden').val('');
                    $('#status-note-display').val('');
                    $('#status-note-input-modal').val('');
                    return;
                }

                if (forceOpen || !$('#status-note-hidden').val()) {
                    statusProdukModal.modal('show');
                }
            }

            $('#status-produk-select').on('change', function() {
                toggleStatusNote(true);
            });

            $('#btn-save-status-note').on('click', function() {
                var note = $('#status-note-input-modal').val().trim();
                $('#status-note-hidden').val(note);
                $('#status-note-display').val(note);
                statusProdukModal.modal('hide');
            });

            toggleStatusNote(false);
        });
    </script>
    
@endsection
