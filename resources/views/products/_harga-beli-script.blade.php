{{--
    Hitung otomatis Harga Beli satuan kecil <-> satuan besar (dipakai form Product create & edit).
    - Isi harga BESAR  -> harga KECIL = besar / konversi
    - Isi harga KECIL  -> harga BESAR = kecil x konversi (kosong bila Isi Konversi belum diisi)
    Kolom yang terakhir diketik user disimpan di input hidden #harga-beli-per sebagai sumber perhitungan.
--}}
<script>
    $(function () {
        var $kecil = $('#harga-beli-input');
        var $besar = $('#harga-beli-besar-input');
        var $per = $('#harga-beli-per');
        var $hint = $('#harga-beli-hint');
        if (!$kecil.length || !$besar.length) { return; }

        // Parser angka format Indonesia (sama dengan parseIdNumber di currency-input.js)
        function parseId(value) {
            var text = String(value == null ? '' : value).trim().replace(/[^\d,.]/g, '');
            if (!text) { return 0; }
            if (text.indexOf(',') !== -1) {
                text = text.replace(/\./g, '').replace(',', '.');
            } else if (text.indexOf('.') !== -1) {
                var parts = text.split('.');
                var last = parts[parts.length - 1];
                text = last.length <= 2 ? parts.slice(0, -1).join('') + '.' + last : parts.join('');
            }
            var number = Number(text);
            return isFinite(number) ? number : 0;
        }

        function fmt(number) {
            return Number(number).toLocaleString('id-ID', { maximumFractionDigits: 2 });
        }

        function round2(number) {
            return Math.round(number * 100) / 100;
        }

        function setValue($input, number) {
            // Tanpa trigger event, supaya tidak memicu perhitungan berulang.
            $input.val(number > 0 ? fmt(round2(number)) : '');
        }

        function sync() {
            var besarLabel = $.trim($('input[name="satuan_besar"]').val());
            var kecilLabel = $.trim($('input[name="satuan"]').val());
            var konversi = parseId($('input[name="konversi_qty"]').val());

            $('#harga-beli-kecil-label').text(kecilLabel ? '(per ' + kecilLabel + ')' : '');
            $('#harga-beli-besar-label').text(besarLabel ? '(per ' + besarLabel + ')' : '');

            var per = $per.val() === 'besar' ? 'besar' : 'kecil';
            var kecilText = kecilLabel || 'satuan kecil';
            var besarText = besarLabel || 'satuan besar';

            if (per === 'besar') {
                var besar = parseId($besar.val());
                if (konversi < 1) {
                    $hint.attr('class', 'help-block text-warning')
                        .text('Isi Satuan Besar dan Isi Konversi supaya harga per ' + kecilText + ' terisi otomatis.');
                    return;
                }
                setValue($kecil, besar / konversi);
                if (besar > 0) {
                    $hint.attr('class', 'help-block text-success')
                        .text('Harga per ' + kecilText + ' = Rp' + fmt(round2(besar / konversi))
                            + ' (Rp' + fmt(besar) + ' ÷ ' + fmt(konversi) + '). Nilai ini yang dipakai untuk hitung laba.');
                } else {
                    $hint.attr('class', 'help-block').text('');
                }
                return;
            }

            var kecil = parseId($kecil.val());
            if (konversi >= 1) {
                setValue($besar, kecil * konversi);
                if (kecil > 0) {
                    $hint.attr('class', 'help-block text-muted')
                        .text('Terisi otomatis: Rp' + fmt(round2(kecil)) + ' × ' + fmt(konversi) + ' per ' + besarText + '.');
                } else {
                    $hint.attr('class', 'help-block').text('');
                }
            } else {
                $besar.val('');
                $hint.attr('class', 'help-block text-muted')
                    .text('Opsional. Terisi otomatis bila Satuan Besar dan Isi Konversi diisi.');
            }
        }

        $kecil.on('input change keyup', function () { $per.val('kecil'); sync(); });
        $besar.on('input change keyup', function () { $per.val('besar'); sync(); });
        $('input[name="satuan_besar"], input[name="satuan"], input[name="konversi_qty"]')
            .on('input change keyup', sync);

        sync();
        $(window).on('load', sync);
    });
</script>