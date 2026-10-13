{{--
    Field Harga Beli untuk form Product (create & edit).
    Harga bisa diinput per Satuan Kecil (pcs) atau per Satuan Besar (karton/box).
    Yang tersimpan di products.harga_beli selalu per SATUAN KECIL (harga besar ÷ isi konversi).
    Variabel: $value = nilai awal input.
--}}
<div class="col-md-6 form-group {{ $errors->has('harga_beli') || $errors->has('harga_beli_per') ? 'has-error' : '' }}">
    <label for="harga-beli-input">Harga Beli</label>
    <div class="row">
        <div class="col-sm-8">
            <div class="input-group">
                <span class="input-group-addon">Rp</span>
                <input type="text" id="harga-beli-input" inputmode="decimal" data-currency-input data-currency-decimals="2"
                    class="form-control" name="harga_beli" value="{{ $value }}" placeholder="Masukkan Harga Beli"
                    autocomplete="off">
            </div>
        </div>
        <div class="col-sm-4">
            <select class="form-control" id="harga-beli-per" name="harga_beli_per" title="Harga beli diinput per satuan apa">
                <option value="kecil" {{ old('harga_beli_per', 'kecil') === 'kecil' ? 'selected' : '' }}>Satuan Kecil</option>
                <option value="besar" {{ old('harga_beli_per', 'kecil') === 'besar' ? 'selected' : '' }}>Satuan Besar</option>
            </select>
        </div>
    </div>
    <p class="help-block" id="harga-beli-hint" style="margin-bottom:0;"></p>
    @error('harga_beli')
        <div class="invalid-feedback text-danger">{{ $message }}</div>
    @enderror
    @error('harga_beli_per')
        <div class="invalid-feedback text-danger">{{ $message }}</div>
    @enderror
</div>