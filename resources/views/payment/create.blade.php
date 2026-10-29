@extends('layouts.master')

@section('title', 'Tambah Payment')

@section('container')
    <section class="content">
        <div class="row">
            <!-- left column -->
            <div class="col-md-12">
                <!-- general form elements -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Tambah Payment</h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <form action="{{ route('payment.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label for="">Nama</label>
                                <input type="text" class="form-control" name="name" value="{{ old('name') }}"
                                    placeholder="Masukkan Nama">
                                @error('name')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="">Nomor Rekening</label>
                                <input type="text" class="form-control" name="bank_number" value="{{ old('bank_number') }}"
                                    placeholder="Masukkan Nomor Rekening">
                                @error('bank_number')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="">Deskripsi</label>
                                <textarea class="form-control" name="desc" value="{{ old('desc') }}" placeholder="Masukkan Deskripsi"></textarea>
                                @error('desc')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="charge_percent">Biaya Tambahan / Charge (%)</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control" id="charge_percent" name="charge_percent"
                                    value="{{ old('charge_percent', 0) }}" placeholder="0">
                                <p class="help-block">Persen dari total belanja yang ditambahkan ke pembayaran pelanggan (mis. 0,7 untuk QRIS). Kosongkan / 0 bila tanpa biaya.</p>
                                @error('charge_percent')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="checkbox">
                                <label>
                                    <input type="hidden" name="is_fixed_amount" value="0">
                                    <input type="checkbox" name="is_fixed_amount" value="1" {{ old('is_fixed_amount', false) ? 'checked' : '' }}>
                                    <b>Nominal fix</b> &mdash; uang diterima terisi otomatis sebesar total (+ biaya), kasir tidak perlu mengisi nominal
                                </label>
                            </div>
                        </div><!-- /.box-body -->

                        <div class="box-footer">
                            <a href="{{ route('kas.index') }}" class="btn btn-default">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div><!-- /.box -->
            </div>
        </div>
    </section>
@endsection