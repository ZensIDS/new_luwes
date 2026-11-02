@extends('layouts.master')

@section('title', 'Edit Supplier')

@section('container')
    <section class="content">
        <div class="row">
            <!-- left column -->
            <div class="col-md-12">
                <!-- general form elements -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Edit Supplier</h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <form action="{{ route('supplier.update', $supplier->id) }}" method="POST" enctype="multipart/form-data">
                        @method('PUT')
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label>Kode</label>
                                <input type="text" class="form-control" name="kode_supplier" id="kodeSupplierInput" data-except-id="{{ $supplier->id }}" autocomplete="off"
                                    value="{{ old('kode_supplier', $supplier->kode_supplier) }}" placeholder="Masukkan Kode Supplier">
                                <small id="kodeSupplierInfo"></small>
                                @error('kode_supplier')<div class="invalid-feedback text-danger">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group">
                                <label>Nama Supplier <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name"
                                    value="{{ old('name', $supplier->name) }}" placeholder="Masukkan Nama Supplier">
                                @error('name')<div class="invalid-feedback text-danger">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group">
                                <label>Alamat <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="alamat"
                                    value="{{ old('alamat', $supplier->alamat) }}" placeholder="Masukkan Alamat">
                                @error('alamat')<div class="invalid-feedback text-danger">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group">
                                <label>Nomor Telp <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="no_telp"
                                    value="{{ old('no_telp', $supplier->no_telp) }}" placeholder="Masukkan Nomor Telp">
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
                                            @foreach([1=>'1 Minggu Sekali',2=>'2 Minggu Sekali',3=>'3 Minggu Sekali',4=>'4 Minggu Sekali'] as $val => $label)
                                                <option value="{{ $val }}"
                                                    {{ old('deadline_interval_weeks', $supplier->deadline_interval_weeks) == $val ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-sm-8">
                                        <label class="control-label" style="font-weight:normal">Hari Deadline</label>
                                        <div class="deadline-days-checkboxes">
                                            @php
                                                $dayLabels   = [1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'];
                                                $savedDays   = (array) old('deadline_days', $supplier->deadline_days ?? []);
                                            @endphp
                                            @foreach($dayLabels as $num => $label)
                                                <label class="checkbox-inline">
                                                    <input type="checkbox" name="deadline_days[]" value="{{ $num }}"
                                                        {{ in_array($num, $savedDays) ? 'checked' : '' }}>
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="deadline_reference_date" id="deadlineReferenceDate"
                                    value="{{ old('deadline_reference_date', $supplier->deadline_reference_date?->format('Y-m-d')) }}">
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
(function() {
    var ref = document.getElementById('deadlineReferenceDate');
    if (ref && !ref.value) {
        var d = new Date();
        var day = d.getDay();
        var diff = day === 0 ? -6 : 1 - day;
        d.setDate(d.getDate() + diff);
        ref.value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
})();
</script>

<script>
(function() {
    var input = document.getElementById('kodeSupplierInput');
    var info  = document.getElementById('kodeSupplierInfo');
    var btn   = document.querySelector('form button[type="submit"]');
    if (!input || !info) return;

    var url      = "{{ route('supplier.check-kode') }}";
    var exceptId = input.dataset.exceptId || '';
    var timer = null, seq = 0;

    function setState(used, name) {
        if (used) {
            info.className = 'text-danger';
            info.textContent = 'Kode sudah digunakan oleh supplier: ' + name;
            input.closest('.form-group').classList.add('has-error');
            if (btn) btn.disabled = true;
        } else {
            info.className = '';
            info.textContent = '';
            input.closest('.form-group').classList.remove('has-error');
            if (btn) btn.disabled = false;
        }
    }

    function check() {
        var kode = input.value.trim();
        if (!kode) { setState(false); return; }
        var current = ++seq;
        var q = '?kode=' + encodeURIComponent(kode) + (exceptId ? '&except_id=' + encodeURIComponent(exceptId) : '');
        fetch(url + q, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(d) { if (current === seq) setState(d.used, d.name); })
            .catch(function() {});
    }

    input.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(check, 300);
    });
    check();
})();
</script>
@endsection