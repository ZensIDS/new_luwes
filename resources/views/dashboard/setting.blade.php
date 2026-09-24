@extends('layouts.master')

@section('title', 'Setting')

@section('container')
    <section class="content-header">
        <h1>
            Dashboard Setting
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6">
                <div class="box">
                    <div class="box-header"></div><!-- /.box-header -->
                    <div class="box-body">
                        <form id="form" method="POST" action="{{ route('setting.store') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group">
                                <label for="name">Nama Perusahaan :</label>
                                <input class="form-control" type="text" name="name" id="name" value="{{ $name }}" required>
                            </div>
                            <div class="form-group">
                                <label for="address">Alamat Perusahaan :</label>
                                <input class="form-control" type="text" name="address" id="address" value="{{ $address }}" required>
                            </div>
                            <div class="form-group">
                                <label for="telp">No Telp :</label>
                                <input class="form-control" type="text" name="telp" id="telp" value="{{ $telp }}" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email :</label>
                                <input class="form-control" type="email" name="email" id="email" value="{{ $email }}" required>
                            </div>
                            <div class="form-group">
                                <label for="website">Website :</label>
                                <input class="form-control" type="url" name="website" id="website" value="{{ $website }}">
                            </div>
                            <div class="form-group">
                                <label for="logo">Logo Perusahaan :</label>
                                <input class="form-control" type="file" name="logo" id="logo" accept="image/*">
                                @if(isset($logo) && $logo)
                                    <div class="mt-2">
                                        <img src="{{ Storage::url($logo) }}" alt="Logo" style="max-height: 80px;">
                                    </div>
                                @endif
                            </div>
                            @if(auth()->user()?->role === 'superadmin')
                                <div class="form-group">
                                    <label for="return_pin">PIN Retur Penjualan :</label>
                                    <input class="form-control" type="password" name="return_pin" id="return_pin" inputmode="numeric" pattern="[0-9]{4,8}" minlength="4" maxlength="8" placeholder="Masukkan PIN baru (4–8 digit)">
                                    <small class="help-block">PIN ini wajib dimasukkan saat memproses retur penjualan. Kosongkan untuk mempertahankan PIN lama.</small>
                                    @if($returnPinConfigured)<span class="text-success"><i class="fa fa-check"></i> PIN retur sudah dikonfigurasi.</span>@else<span class="text-danger"><i class="fa fa-warning"></i> PIN retur belum dikonfigurasi.</span>@endif
                                </div>
                            @endif
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </form>
                    </div><!-- /.box-body -->
                </div><!-- /.box -->
            </div><!-- /.col -->
        </div><!-- /.row -->
    </section><!-- /.content -->
@endsection
@section('page-script')
    <script>
        $(document).ready(function() {
            //
        });
    </script>
@endsection
