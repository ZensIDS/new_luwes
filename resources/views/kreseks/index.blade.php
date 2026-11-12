@extends('layouts.master')

@section('title', 'Master Kresek')

@section('container')
    <section class="content-header">
        <h1>Master Kresek <small>Kantong plastik yang bisa dipilih di layar kasir</small></h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <a href="{{ route('kresek.create') }}" class="btn btn-md bg-green">Tambah</a>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th style="width:50px;">No</th>
                                    <th>Nama</th>
                                    <th class="text-right">Harga</th>
                                    <th>Status</th>
                                    <th style="width:160px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($kreseks as $kresek)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $kresek->name }}</td>
                                        <td class="text-right">@currency($kresek->price)</td>
                                        <td>
                                            @if ($kresek->is_active)
                                                <span class="label label-success">Aktif</span>
                                            @else
                                                <span class="label label-default">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a class="btn btn-warning btn-sm" href="{{ route('kresek.edit', $kresek) }}">Edit</a>
                                            <form action="{{ route('kresek.destroy', $kresek) }}" method="post" style="display:inline;">
                                                @method('delete')
                                                @csrf
                                                <button class="border-0 btn btn-danger btn-sm" onclick="return confirm('Hapus kresek ini? Nota lama tidak berubah.')">Hapus</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">Belum ada kresek.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection