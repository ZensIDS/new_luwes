@extends('layouts.master')

@section('title', 'Pendapatan Lain')

@php
    $rp = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $canManageCategories = in_array(auth()->user()->role, ['superadmin', 'owner'], true);
@endphp

@section('container')
    <section class="content-header">
        <h1>Pendapatan Lain <small>Pemasukan di luar penjualan kasir</small></h1>
    </section>

    <section class="content">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul style="margin-bottom:0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="box box-primary">
            <div class="box-header">
                @php
                    // Filter aktif dibawa ke export. Nilai kosong tetap dikirim ('') supaya
                    // "tanpa batas tanggal" tidak berubah jadi bulan berjalan.
                    $exportParams = array_map(fn ($value) => $value ?? '', $filters);
                @endphp
                <a class="btn btn-success" href="{{ route('other-income.create') }}"><i class="fa fa-plus"></i> Tambah Pendapatan Lain</a>
                <a class="btn btn-primary" href="{{ route('other-income.export', $exportParams) }}"><i class="fa fa-file-excel-o"></i> Export Excel</a>
                @if ($canManageCategories)
                    <a class="btn btn-default" href="{{ route('other-income.categories.index') }}"><i class="fa fa-tags"></i> Kategori</a>
                @endif
            </div>

            <div class="box-body">
                <form method="GET" action="{{ route('other-income.index') }}" class="form-inline" style="margin-bottom:15px;">
                    <select class="form-control input-sm" name="category_id">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ (int) $filters['category_id'] === $category->id ? 'selected' : '' }}>
                                {{ $category->name }}{{ $category->trashed() ? ' (dihapus)' : '' }}
                            </option>
                        @endforeach
                    </select>

                    @unless ($scoped)
                        <select class="form-control input-sm" name="outlet_id">
                            <option value="">Semua outlet</option>
                            <option value="umum" {{ $filters['outlet_id'] === 'umum' ? 'selected' : '' }}>Umum (tanpa outlet)</option>
                            @foreach ($outlets as $outlet)
                                <option value="{{ $outlet->id }}" {{ $filters['outlet_id'] === (string) $outlet->id ? 'selected' : '' }}>
                                    {{ $outlet->name }}</option>
                            @endforeach
                        </select>
                    @endunless

                    <label class="control-label" style="margin-left:6px;">Dari</label>
                    <input type="date" class="form-control input-sm" name="dari" value="{{ $filters['dari'] }}">
                    <label class="control-label">Sampai</label>
                    <input type="date" class="form-control input-sm" name="sampai" value="{{ $filters['sampai'] }}">

                    <button class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
                    <a class="btn btn-default btn-sm" href="{{ route('other-income.index') }}">Reset</a>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width:50px;">No</th>
                                <th>Tanggal</th>
                                <th>Kategori</th>
                                <th>Outlet</th>
                                <th>Keterangan</th>
                                <th>Sumber</th>
                                <th class="text-right">Jumlah</th>
                                <th style="width:140px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($incomes as $income)
                                <tr>
                                    <td>{{ $incomes->firstItem() + $loop->index }}</td>
                                    {{-- Tanggal = waktu input (otomatis). --}}
                                    <td>{{ ($income->created_at ?? $income->tanggal)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        {{ $income->category?->name ?? '-' }}
                                        @if ($income->category?->trashed())
                                            <small class="text-muted">(dihapus)</small>
                                        @endif
                                    </td>
                                    <td>{{ $income->outlet?->name ?? 'Umum' }}</td>
                                    <td>
                                        @if ($income->is_auto)
                                            Produk: {{ $income->product_name_snapshot ?? '-' }}
                                        @else
                                            {{ $income->keterangan }}
                                        @endif

                                        {{-- Info Sewa: periode, supplier, produk (hanya muncul bila terisi). --}}
                                        @if ($income->sewa_mulai || $income->supplier_id || $income->product_id)
                                            <div class="small text-muted" style="margin-top:3px; line-height:1.5;">
                                                @if ($income->sewa_mulai && $income->sewa_selesai)
                                                    <div><i class="fa fa-calendar"></i> Sewa: {{ $income->sewa_mulai->format('d/m/Y') }} – {{ $income->sewa_selesai->format('d/m/Y') }}</div>
                                                @endif
                                                @if ($income->supplier)
                                                    <div><i class="fa fa-truck"></i> Supplier: {{ $income->supplier->name }}</div>
                                                @endif
                                                @if ($income->product || $income->product_name_snapshot)
                                                    <div><i class="fa fa-cube"></i> Produk: {{ $income->product?->name ?? $income->product_name_snapshot }}</div>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($income->is_auto)
                                            <span class="label label-info">Otomatis (Listing)</span>
                                        @else
                                            <span class="label label-default">Manual</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ $rp($income->jumlah) }}</td>
                                    <td>
                                        @if ($income->is_auto)
                                            <button type="button" class="btn btn-warning btn-sm" disabled
                                                title="Income otomatis tidak bisa diubah">Edit</button>
                                            <button type="button" class="btn btn-danger btn-sm" disabled
                                                title="Income otomatis tidak bisa dihapus">Hapus</button>
                                        @else
                                            <a class="btn btn-warning btn-sm" href="{{ route('other-income.edit', $income) }}">Edit</a>
                                            <form action="{{ route('other-income.destroy', $income) }}" method="post" style="display:inline;">
                                                @csrf
                                                @method('delete')
                                                <button class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Hapus pendapatan lain ini?')">Hapus</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">Belum ada data untuk filter ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="6" class="text-right">
                                    Total sesuai filter ({{ number_format($incomes->total(), 0, ',', '.') }} transaksi)
                                </th>
                                <th class="text-right">{{ $rp($total) }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{ $incomes->links() }}
            </div>
        </div>
    </section>
@endsection