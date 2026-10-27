@extends('layouts.master')

@php
    $isEdit = $income->exists;
    $currentOutletId = old('outlet_id', $income->outlet_id);
    $currentCategoryId = (int) old('other_income_category_id', $income->other_income_category_id);
    $isSewaNow = in_array($currentCategoryId, $sewaCategoryIds, true);
    $currentSupplierId = (int) old('supplier_id', $income->supplier_id);
@endphp

@section('title', $isEdit ? 'Edit Pendapatan Lain' : 'Tambah Pendapatan Lain')

@section('container')
    <section class="content-header">
        <h1>{{ $isEdit ? 'Edit' : 'Tambah' }} Pendapatan Lain <small>Input manual</small></h1>
    </section>

    <section class="content">
        <div class="box box-primary">
            <form action="{{ $action }}" method="POST" id="income-form">
                @csrf
                @if ($method === 'PUT')
                    @method('PUT')
                @endif

                <div class="box-body">
                    <div class="row">
                        <div class="col-md-6 form-group {{ $errors->has('other_income_category_id') ? 'has-error' : '' }}">
                            <label>Kategori <span class="text-danger">*</span></label>
                            <select class="form-control" name="other_income_category_id" id="category-select">
                                <option value="">Pilih kategori</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}"
                                        {{ $currentCategoryId === $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}{{ $category->trashed() ? ' (dihapus)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('other_income_category_id')<span class="help-block">{{ $message }}</span>@enderror
                            <span class="help-block">Kategori Listing Product dibuat otomatis oleh sistem dan tidak bisa dipilih di sini.</span>
                        </div>

                        <div class="col-md-6 form-group {{ $errors->has('jumlah') ? 'has-error' : '' }}">
                            <label>Jumlah <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-addon">Rp</span>
                                <input type="text" inputmode="numeric" data-currency-input data-currency-decimals="0"
                                    class="form-control" name="jumlah" placeholder="Masukkan jumlah"
                                    value="{{ old('jumlah', $isEdit ? $income->jumlah + 0 : '') }}">
                            </div>
                            @error('jumlah')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    {{-- Khusus kategori Sewa: periode, supplier, dan produk milik supplier tersebut. --}}
                    <div id="sewa-fields" style="{{ $isSewaNow ? '' : 'display:none;' }}">
                        <div class="callout callout-info" style="margin-bottom:15px;">
                            <p style="margin:0;"><i class="fa fa-info-circle"></i> Kategori Sewa: isi periode sewa, supplier, lalu produk yang disewa.</p>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group {{ $errors->has('sewa_mulai') ? 'has-error' : '' }}">
                                <label>Tanggal Dari <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="sewa_mulai" id="sewa-mulai"
                                    value="{{ old('sewa_mulai', optional($income->sewa_mulai)->format('Y-m-d')) }}">
                                @error('sewa_mulai')<span class="help-block">{{ $message }}</span>@enderror
                            </div>

                            <div class="col-md-6 form-group {{ $errors->has('sewa_selesai') ? 'has-error' : '' }}">
                                <label>Tanggal Sampai <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="sewa_selesai" id="sewa-selesai"
                                    value="{{ old('sewa_selesai', optional($income->sewa_selesai)->format('Y-m-d')) }}">
                                @error('sewa_selesai')<span class="help-block">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group {{ $errors->has('supplier_id') ? 'has-error' : '' }}">
                                <label>Supplier <span class="text-danger">*</span></label>
                                <select class="form-control" name="supplier_id" id="supplier-select"
                                    data-placeholder="Pilih supplier" style="width:100%;">
                                    <option value=""></option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ $currentSupplierId === $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->name }}{{ $supplier->trashed() ? ' (dihapus)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('supplier_id')<span class="help-block">{{ $message }}</span>@enderror
                            </div>

                            <div class="col-md-6 form-group {{ $errors->has('product_id') ? 'has-error' : '' }}">
                                <label>Produk <span class="text-danger">*</span></label>
                                <select class="form-control" name="product_id" id="product-select"
                                    data-placeholder="Pilih supplier dulu, lalu ketik nama produk" style="width:100%;">
                                    @if ($selectedProduct)
                                        <option value="{{ $selectedProduct->id }}" selected>
                                            {{ $selectedProduct->code }} — {{ $selectedProduct->name }}</option>
                                    @endif
                                </select>
                                @error('product_id')<span class="help-block">{{ $message }}</span>@enderror
                                <span class="help-block">Hanya produk milik supplier terpilih. Ketik minimal 2 huruf.</span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group {{ $errors->has('outlet_id') ? 'has-error' : '' }}">
                            <label>Outlet <small class="text-muted">(opsional)</small></label>
                            @if ($scoped)
                                {{-- staff-outlet / kasir: terkunci ke outlet sendiri (diisi server) --}}
                                <input type="text" class="form-control" disabled
                                    value="{{ $outlets->first()?->name ?? 'Belum ada outlet' }}">
                            @else
                                <select class="form-control" name="outlet_id">
                                    <option value="">Umum (tanpa outlet)</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}" {{ (int) $currentOutletId === $outlet->id ? 'selected' : '' }}>
                                            {{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                            @error('outlet_id')<span class="help-block">{{ $message }}</span>@enderror
                        </div>

                        <div class="col-md-6 form-group">
                            <label>Waktu Input</label>
                            <input type="text" class="form-control" disabled
                                value="{{ $isEdit ? $income->created_at?->format('d/m/Y H:i') : 'Otomatis saat disimpan' }}">
                        </div>
                    </div>

                    <div class="form-group {{ $errors->has('keterangan') ? 'has-error' : '' }}">
                        <label>Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="3" maxlength="1000"
                            placeholder="Contoh: sewa rak bulan Oktober">{{ old('keterangan', $income->keterangan) }}</textarea>
                        @error('keterangan')<span class="help-block">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="box-footer">
                    <a href="{{ route('other-income.index') }}" class="btn btn-default">Kembali</a>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </section>
@endsection

@section('page-script')
    <script>
        $(function () {
            var sewaCategoryIds = @json($sewaCategoryIds);
            var $category = $('#category-select');
            var $sewa = $('#sewa-fields');
            var $supplier = $('#supplier-select');
            var $product = $('#product-select');
            var $mulai = $('#sewa-mulai');
            var $selesai = $('#sewa-selesai');

            $supplier.select2({
                width: '100%',
                placeholder: $supplier.data('placeholder'),
                allowClear: true
            });

            // Produk dicari di server: hanya milik supplier terpilih, minimal 2 huruf, maksimal 20 hasil.
            $product.select2({
                width: '100%',
                placeholder: $product.data('placeholder'),
                allowClear: true,
                minimumInputLength: 2,
                ajax: {
                    url: '{{ route('other-income.products.search') }}',
                    dataType: 'json',
                    delay: 300,
                    cache: true,
                    data: function (params) {
                        return { q: params.term, supplier_id: $supplier.val() };
                    },
                    processResults: function (data) {
                        return { results: data.results };
                    }
                },
                language: {
                    inputTooShort: function () { return 'Ketik minimal 2 huruf untuk mencari produk...'; },
                    searching: function () { return 'Mencari...'; },
                    noResults: function () { return 'Produk tidak ditemukan pada supplier ini'; }
                }
            });

            function toggleSewa() {
                var isSewa = sewaCategoryIds.indexOf(parseInt($category.val(), 10)) !== -1;
                $sewa.toggle(isSewa);
                // Kategori lain: kosongkan isian sewa (server juga mengosongkannya).
                if (!isSewa) {
                    $mulai.val('');
                    $selesai.val('');
                    $supplier.val(null).trigger('change.select2');
                    $product.val(null).trigger('change.select2');
                }
            }

            // Ganti supplier -> produk lama tidak berlaku lagi (produk harus milik supplier terpilih).
            // Event 'change' tidak dipakai agar pengisian awal halaman tidak menghapus produk tersimpan.
            $supplier.on('select2:select select2:clear', function () {
                $product.val(null).trigger('change.select2');
            });

            // Tanggal Sampai tidak boleh sebelum Tanggal Dari.
            $mulai.on('change', function () {
                $selesai.attr('min', $mulai.val() || '');
                if ($selesai.val() && $mulai.val() && $selesai.val() < $mulai.val()) {
                    $selesai.val($mulai.val());
                }
            }).trigger('change');

            $category.on('change', toggleSewa);
        });
    </script>
@endsection