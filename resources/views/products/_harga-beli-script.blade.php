{{-- Hitung otomatis Harga Beli per satuan kecil / besar (dipakai form Product create & edit). --}}
<script>
    $(function () {
        var $price = $('#harga-beli-input');
        var $per = $('#harga-beli-per');
        var $hint = $('#harga-beli-hint');
        if (!$price.length) { return; }

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

        function refresh() {
            var besar = $.trim($('input[name="satuan_besar"]').val());
            var kecil = $.trim($('input[name="satuan"]').val());
            var konversi = parseId($('input[name="konversi_qty"]').val());
            var harga = parseId($price.val());
            var kecilLabel = kecil || 'satuan kecil';

            $per.find('option[value="besar"]').text('Satuan Besar' + (besar ? ' (' + besar + ')' : ''));
            $per.find('option[value="kecil"]').text('Satuan Kecil' + (kecil ? ' (' + kecil + ')' : ''));

            if ($per.val() === 'besar') {
                if (!besar || konversi < 1) {
                    $hint.attr('class', 'help-block text-warning')
                        .text('Isi Satuan Besar dan Isi Konversi supaya harga per ' + kecilLabel + ' bisa dihitung otomatis.');
                } else if (harga > 0) {
                    $hint.attr('class', 'help-block text-success')
                        .text('Harga beli per ' + kecilLabel + ' = Rp' + fmt(Math.round(harga / konversi * 100) / 100)
                            + ' (Rp' + fmt(harga) + ' ÷ ' + fmt(konversi) + '). Nilai ini yang disimpan.');
                } else {
                    $hint.attr('class', 'help-block').text('');
                }
                return;
            }

            if (harga > 0) {
                var text = 'Harga beli disimpan sebagai harga per ' + kecilLabel + '.';
                if (besar && konversi >= 1) {
                    text += ' Setara Rp' + fmt(Math.round(harga * konversi * 100) / 100) + ' per ' + besar + '.';
                }
                $hint.attr('class', 'help-block text-muted').text(text);
            } else {
                $hint.attr('class', 'help-block').text('');
            }
        }

        $('input[name="satuan_besar"], input[name="satuan"], input[name="konversi_qty"], #harga-beli-input')
            .on('input change keyup', refresh);
        $per.on('change', refresh);
        refresh();
        $(window).on('load', refresh);
    });
</script>