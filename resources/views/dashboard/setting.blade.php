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
