{{--
    Field Harga Beli untuk form Product (create & edit).
    - Harga Beli Satuan Kecil  -> products.harga_beli       (dipakai untuk hitung HPP / laba)
    - Harga Beli Satuan Besar  -> products.harga_beli_besar (tampilan, boleh kosong)
    User cukup mengisi SALAH SATU; pasangannya terisi otomatis lewat Isi Konversi
    (besar -> kecil = besar / konversi,  kecil -> besar = kecil x konversi).
    Input hidden harga_beli_per mencatat kolom mana yang terakhir diisi user (sumber perhitungan di server).
    Variabel: $value = nilai awal harga satuan kecil. Harga satuan besar diambil dari old() / $product bila ada.
--}}
@php
    $fmtHarga = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $hargaBesarValue = old(
        'harga_beli_besar',
        isset($product) && $product->harga_beli_besar !== null ? $fmtHarga($product->harga_beli_besar) : ''
    );
    $hargaPer = old('harga_beli_per', 'kecil') === 'besar' ? 'besar' : 'kecil';
@endphp
<input type="hidden" name="harga_beli_per" id="harga-beli-per" value="{{ $hargaPer }}">

<div class="col-md-6 form-group {{ $errors->has('harga_beli') ? 'has-error' : '' }}">
    <label for="harga-beli-input">Harga Beli Satuan Kecil <span id="harga-beli-kecil-label"></span></label>
    <div class="input-group">
        <span class="input-group-addon">Rp</span>
        <input type="text" id="harga-beli-input" inputmode="decimal" data-currency-input data-currency-decimals="2"
            class="form-control" name="harga_beli" value="{{ $value }}" placeholder="Harga per satuan kecil"
            autocomplete="off">
    </div>
    @error('harga_beli')
        <div class="invalid-feedback text-danger">{{ $message }}</div>
    @enderror
    <p class="help-block" style="margin-bottom:0;">Dipakai untuk perhitungan HPP &amp; laba penjualan.</p>
</div>

<div class="col-md-6 form-group {{ $errors->has('harga_beli_besar') || $errors->has('harga_beli_per') ? 'has-error' : '' }}">
    <label for="harga-beli-besar-input">Harga Beli Satuan Besar <span id="harga-beli-besar-label"></span></label>
    <div class="input-group">
        <span class="input-group-addon">Rp</span>
        <input type="text" id="harga-beli-besar-input" inputmode="decimal" data-currency-input data-currency-decimals="2"
            class="form-control" name="harga_beli_besar" value="{{ $hargaBesarValue }}"
            placeholder="Harga per satuan besar (boleh kosong)" autocomplete="off">
    </div>
    @error('harga_beli_besar')
        <div class="invalid-feedback text-danger">{{ $message }}</div>
    @enderror
    @error('harga_beli_per')
        <div class="invalid-feedback text-danger">{{ $message }}</div>
    @enderror
    <p class="help-block" id="harga-beli-hint" style="margin-bottom:0;"></p>
</div>