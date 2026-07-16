@extends('layouts.master')

@section('title', 'Tambah Supplier')

@section('container')
    <section class="content">
        <div class="row">
            <!-- left column -->
            <div class="col-md-12">
                <!-- general form elements -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Tambah Supplier</h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <form action="{{ route('supplier.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label>Kode</label>
                                <input type="text" class="form-control" name="kode_supplier"
                                    value="{{ old('kode_supplier', $nextKode) }}">
                                @error('kode_supplier')<div class="invalid-feedback text-danger">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group">
                                <label>Nama Supplier <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" value="{{ old('name') }}"
                                    placeholder="Masukkan Nama Supplier">
                                @error('name')<div class="invalid-feedback text-danger">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group">
                                <label>Alamat <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="alamat" value="{{ old('alamat') }}"
                                    placeholder="Masukkan Alamat">
                                @error('alamat')<div class="invalid-feedback text-danger">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group">
                                <label>Nomor Telp <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="no_telp" value="{{ old('no_telp') }}"
                                    placeholder="Masukkan Nomor Telp">
                                @error('no_telp')<div class="invalid-feedback text-danger">{{ $message }}</div>@enderror
                            </div>

                            {{-- Deadline Order --}}
                            <div class="form-group">
                                <label>Jadwal Deadline Order</label>
                                <div class="row">
                                    <div class="col-sm-4">
                                        <label class="control-label" style="font-weight:normal">Jangka Waktu</label>
                                        <select class="form-control" name="deadline_interval_weeks">
                                            <option value="">— Tidak ada deadline —</option>
                                            @foreach([1=>'1 Minggu Sekali',2=>'2 Minggu Sekali',3=>'3 Minggu Sekali',4=>'4 Minggu Sekali'] as $val => $lbl)
                                                <option value="{{ $val }}" {{ old('deadline_interval_weeks') == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-sm-8">
                                        <label class="control-label" style="font-weight:normal">Hari Deadline</label>
                                        <div class="deadline-days-checkboxes">
                                            @php
                                                $dayLabels = [1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'];
                                                $oldDays   = (array) old('deadline_days', []);
                                            @endphp
                                            @foreach($dayLabels as $num => $label)
                                                <label class="checkbox-inline">
                                                    <input type="checkbox" name="deadline_days[]" value="{{ $num }}"
                                                        {{ in_array($num, $oldDays) ? 'checked' : '' }}>
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="deadline_reference_date" id="deadlineReferenceDate"
                                    value="{{ old('deadline_reference_date') }}">
                                <p class="help-block text-muted" style="margin-top:6px">
                                    <i class="fa fa-info-circle"></i>
                                    Notifikasi akan muncul H-3 sebelum deadline di dashboard.
                                </p>
                            </div>
                        </div><!-- /.box-body -->

                        <div class="box-footer">
                            <a href="{{ route('supplier.index') }}" class="btn btn-default">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div><!-- /.box -->
            </div>
        </div>
    </section>
@endsection

@section('page-script')
<script>
// Auto-set reference date to nearest past Monday on page load
(function() {
    var ref = document.getElementById('deadlineReferenceDate');
    if (ref && !ref.value) {
        var d = new Date();
        var day = d.getDay(); // 0=Sun, 1=Mon...
        var diff = day === 0 ? -6 : 1 - day; // offset to Monday
        d.setDate(d.getDate() + diff);
        ref.value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
})();
</script>
@endsection
