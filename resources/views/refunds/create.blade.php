@extends('layouts.master')

@section('title', 'Tambah Refund Penjualan')

@section('container')
    <section class="content">
        <div class="row">
            <!-- left column -->
            <div class="col-md-12">
                <!-- general form elements -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Tambah Refund</h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <form action="{{ route('refund.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label for="">Kode Refund</label>
                                <input type="text" class="form-control" name="code" value="{{ old('code') }}"
                                    placeholder="Masukkan Kode Refund">
                                @error('code')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="">Tanggal</label>
                                <input type="date" class="form-control" name="tanggal" value="{{ old('tanggal') }}"
                                    placeholder="Masukkan Tanggal">
                                @error('tanggal')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Outlet</label>
                                <select id="outlet" class="form-control select2" name="outlet_id" data-placeholder="Pilih Outlet"
                                    style="width: 100%;">
                                    <option value="" selected disabled>Pilih Outlet</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}"
                                            {{ old('outlet_id') == $outlet->id ? 'selected' : '' }}>
                                            {{ $outlet->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('outlet_id')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <hr>
                            <div class="form-group">
                                <label>Penjualan</label>
                                <select id="penjualan" class="form-control select2" name="penjualan_id" data-placeholder="Pilih Penjualan" style="width: 100%;"></select>
                                @error('penjualan_id')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Customer</label>
                                <select id="customer" class="form-control select2" name="customer_id" data-placeholder="Pilih Customer" style="width: 100%;"></select>
                                @error('customer_id')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Kas</label>
                                <select id="kas" class="form-control select2" name="kas_id" data-placeholder="Pilih Kas" style="width: 100%;">
                                    {{-- @foreach ($kas as $kas)<option value="{{ $kas->id }}" {{ old('kas_id') == $kas->id ? 'selected' : '' }}>{{ $kas->name }}</option>@endforeach --}}
                                </select>
                                @error('kas_id')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <hr>
                            <div class="form-group">
                                <label for="">Total IDR</label>
                                <input type="text" class="form-control numeral-mask" name="total" value="{{ old('total') }}"
                                    placeholder="Masukkan Total IDR">
                                @error('total')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <table class="table table-bordered table-striped" id="example">
                                <thead>
                                    <tr>
                                        <td>Nama Product</td>
                                        <td>Qty</td>
                                        <td>Serial</td>
                                        <td>Aksi</td>
                                    </tr>
                                </thead>
                                <tbody id="product-repeater">
                                    <tr>
                                        <td>
                                            <select class="form-control select2" data-placeholder="Pilih Product" name="product[0][product_id]" required style="width:100%">
                                                <option value="" selected disabled>Pilih Product</option>
                                                @foreach ($products as $product)
                                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input class="form-control" name="product[0][qty]" required value="0"></td>
                                        <td><input class="form-control" name="product[0][alasan]" required value=""></td>
                                        <td><button class="btn btn-sm btn-danger" onclick="removeBahanBaku(this)" type="button">Remove</button></td>
                                    </tr>
                                </tbody>
                            </table>

                            <button class="btn btn-sm btn-primary" onclick="addBahanBaku()" type="button">Add</button>
                        </div><!-- /.box-body -->

                        <div class="box-footer">
                            <a href="{{ route('refund.index') }}" class="btn btn-default">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div><!-- /.box -->
            </div>
        </div>
    </section>
@endsection
@section('page-script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        let productIndex = 0;

        function addBahanBaku() {
            productIndex++;
            let productTemplate = `
        <tr>
            <td>
                <select required class="form-control select2" name="product[${productIndex}][product_id]" data-placeholder="Pilih Product" style="width:100%;">
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="text" required value="0" class="form-control" name="product[${productIndex}][qty]"></td>
            <td><input type="text" required value="" placeholder="" class="form-control" name="product[${productIndex}][alasan]"></td>
            <td><button class="btn btn-sm btn-danger" onclick="removeBahanBaku(this)" type="button">Remove</button></td>

        </tr>`;
            $('#product-repeater').append(productTemplate);
            $('#product-repeater .select2').last().select2();
        }

        $('.numeral-mask').mask("#,##0", {
            reverse: true
        });

        function removeBahanBaku(button) {
            if ($('#example tbody tr').length > 1) {
                $(button).closest('tr').remove();
            }
        }

        $('#penjualan').prop('disabled', true);
        $('#customer').prop('disabled', true);
        $('#kas').prop('disabled', true);

        $('#outlet').on('change', function() {
            let outlet_id = $(this).val();
            $.get('/get-penjualan/' + outlet_id, function(data) {
                $('#penjualan').find('option').remove();
                let defaultOption = $('<option>').val('').text('Pilih Penjualan').prop('disabled', true).prop('selected', true);
                $('#penjualan').append(defaultOption);
                data.forEach(function(penjualan) {
                    let option = $('<option>').val(penjualan.id).text(penjualan.code);
                    $('#penjualan').append(option);
                });
                $('#penjualan').trigger('change.select2');
            });
            $('#penjualan').prop('disabled', false);
            $('#customer').prop('disabled', true);
            $('#customer').find('option').remove();
            let defaultOption = $('<option>').val('').text('Pilih Customer').prop('disabled', true).prop('selected', true);
            $('#customer').append(defaultOption);

            $.get('/outlet/' + outlet_id + '/kas', function(data) {
                $('#kas').find('option').remove();
                let defaultKasOption = $('<option>').val('').text('Pilih Kas').prop('disabled', true).prop('selected', true);
                $('#kas').append(defaultKasOption);
                data.forEach(function(kas) {
                    let option = $('<option>').val(kas.id).text(kas.name);
                    $('#kas').append(option);
                });
                $('#kas').trigger('change.select2');
            });
            $('#kas').prop('disabled', false);

        });

        $('#penjualan').on('change', function() {
            let penjualan_id = $(this).val();
            $.get('/get-customer/' + penjualan_id, function(data) {
                $('#customer').find('option').remove();
                let defaultOption = $('<option>').val('').text('Pilih Customer').prop('disabled', true).prop('selected', true);
                $('#customer').append(defaultOption);
                let option = $('<option>').val(data.id).text(data.name);
                $('#customer').append(option);
                $('#customer').trigger('change.select2');
            });
            $('#customer').prop('disabled', false);

            $.ajax({
                url: '/penjualan-detail/' + penjualan_id + '/items',
                type: 'GET',
                success: function(data) {
                    // update form repeater with fetched data
                    $('#product-repeater').empty();
                    data.forEach(function(item) {
                        let productTemplate = `
                            <tr>
                                <td>
                                    <select required class="form-control select2" name="product[${item.id}][product_id]" data-placeholder="Pilih Product" style="width:100%;">
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" ${item.product_id == {{ $product->id }} ? 'selected' : ''}>{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" required value="${item.qty}" class="form-control" name="product[${item.id}][qty]"></td>
                                <td><input type="text" required value="${item.serial_number ? item.serial_number : ''}" placeholder=" " class="form-control" name="product[${item.id}][alasan]"></td>
                                <td><button class="btn btn-sm btn-danger" onclick="removeBahanBaku(this)" type="button">Remove</button></td>
                            </tr>`;
                        $('#product-repeater').append(productTemplate);
                    });
                    $('#product-repeater .select2').select2();
                }
            });
        });

    </script>
@endsection
