{{--
    Keterangan Other Income Listing di bawah dropdown Status Produk (tanpa field nominal / outlet).
    Muncul hanya saat status "Listing" dipilih. Pakai JS biasa (tanpa jQuery) karena partial ini ada di badan halaman.

    Parameter:
      $select  (string)  selector CSS dropdown status, mis. '#status-produk-select'
      $mode    (string)  'trigger'  : produk akan mencatat income sekali saja (create / update pertama)
                         'recorded' : income Listing produk ini sudah tercatat sebelumnya
                         'closed'   : produk sudah lewat jendela pemicu (update kedua dst)
      $nominal (?float)  nominal Listing saat ini; null = belum diatur
      $wrapClass (string, opsional) kelas kolom pembungkus, default 'col-md-12'
--}}
@php
    $mode = $mode ?? 'trigger';
    $wrapClass = $wrapClass ?? 'col-md-12';
    $infoId = 'listing-info-' . substr(md5($select), 0, 8);
@endphp
<div class="{{ $wrapClass }}" id="{{ $infoId }}" style="display:none;">
    @if ($mode === 'trigger' && $nominal)
        <div class="alert alert-info" style="margin-bottom:15px;">
            <i class="fa fa-info-circle"></i>
            Produk ini akan mencatat Other Income Listing sebesar
            <strong>Rp {{ number_format($nominal, 0, ',', '.') }}</strong> (sekali saja).
        </div>
    @elseif ($mode === 'trigger')
        <div class="alert alert-danger" style="margin-bottom:15px;">
            <i class="fa fa-exclamation-triangle"></i>
            Nominal Listing belum diatur, sehingga produk <strong>tidak bisa disimpan</strong> dengan status Listing.
            Atur dulu di Other Income &gt; Kategori.
        </div>
    @elseif ($mode === 'recorded')
        <div class="alert alert-warning" style="margin-bottom:15px;">
            <i class="fa fa-info-circle"></i>
            Income Listing produk ini sudah tercatat sebelumnya, jadi tidak akan tercatat lagi.
        </div>
    @else
        <div class="alert alert-warning" style="margin-bottom:15px;">
            <i class="fa fa-info-circle"></i>
            Produk ini sudah tidak memicu Other Income (hanya saat dibuat atau disimpan pertama kali),
            jadi mengubah status ke Listing tidak mencatat income.
        </div>
    @endif
</div>
<script>
    (function () {
        var select = document.querySelector(@json($select));
        var box = document.getElementById(@json($infoId));
        if (!select || !box) { return; }

        function sync() {
            box.style.display = select.value === 'listing' ? '' : 'none';
        }

        select.addEventListener('change', sync);
        sync();
    })();
</script>