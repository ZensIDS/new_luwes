{{--
    Saran barcode buatan sendiri: user mengetik awalan huruf (K, I, LO, ...), panel menampilkan nomor yang sudah
    dipakai, nomor berikutnya, dan nomor kosong. Diletakkan setelah partial _barcode-check.
    Variabel: $input (selector input barcode), $url (route product.suggest-barcode)
--}}
<style>
    .barcode-suggest { display:none; margin-top:6px; padding:10px 12px; border:1px solid #dfe3ea; border-radius:6px; background:#f8fafc; font-size:12.5px; }
    .barcode-suggest .bs-title { margin-bottom:6px; color:#475569; }
    .barcode-suggest .bs-section { margin-top:8px; }
    .barcode-suggest .bs-label { display:block; margin-bottom:3px; font-weight:600; color:#334155; }
    .barcode-suggest .btn-xs { margin:0 4px 4px 0; }
    .barcode-suggest .bs-recent { margin:0; padding-left:0; list-style:none; color:#475569; }
    .barcode-suggest .bs-recent li { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .barcode-suggest .bs-recent b { color:#0f172a; }
</style>
<script>
    $(function () {
        var $input = $(@json($input));
        if (!$input.length) { return; }

        var url = @json($url);
        var $panel = $('<div class="barcode-suggest" aria-live="polite"></div>');
        // Letakkan setelah pesan cek barcode (kalau ada) supaya urutannya: kolom, status, saran.
        var $anchor = $input.siblings('.barcode-check-feedback');
        ($anchor.length ? $anchor : $input).last().after($panel);

        var timer = null, request = null;

        function hide() { $panel.hide().empty(); }

        function use(code) {
            $input.val(code).trigger('input').trigger('focus');
            hide();
        }

        function chip(text, code, cls) {
            return $('<button type="button" class="btn btn-xs ' + (cls || 'btn-default') + '">').text(text)
                .on('click', function () { use(code); });
        }

        function render(res) {
            $panel.empty();

            // Kode lengkap sudah diketik (angka sepanjang format): panel tidak perlu lagi.
            if (res.digits.length >= res.width) { hide(); return; }

            if (res.count === 0 && res.deleted_count === 0) {
                $panel.append($('<div class="bs-title">').append(
                    document.createTextNode('Awalan '), $('<b>').text(res.prefix),
                    document.createTextNode(' belum pernah dipakai. Mulai dari ')
                )).append(chip('Pakai ' + res.next, res.next, 'btn-primary'));
                $panel.show();
                return;
            }

            $panel.append($('<div class="bs-title">').append(
                $('<b>').text(res.prefix), document.createTextNode(': '),
                $('<b>').text(res.count.toLocaleString('id-ID')),
                document.createTextNode(' kode terpakai' + (res.deleted_count ? ' (+' + res.deleted_count + ' dari barang terhapus)' : '') + '. Nomor berikutnya: '),
                $('<b>').text(res.next)
            ));
            $panel.append(chip('Pakai ' + res.next, res.next, 'btn-primary'));

            if (res.recent.length) {
                var $ul = $('<ul class="bs-recent">');
                $.each(res.recent, function (_, r) {
                    $ul.append($('<li>').append($('<b>').text(r.code), document.createTextNode(' - ' + r.name)));
                });
                $panel.append($('<div class="bs-section">').append(
                    $('<span class="bs-label">').text(res.digits ? 'Kode yang cocok' : 'Kode terakhir terpakai'), $ul));
            }

            if (res.gaps.length) {
                var $g = $('<div class="bs-section">').append($('<span class="bs-label">')
                    .text('Nomor kosong (klik untuk memakai yang pertama)'));
                $.each(res.gaps, function (_, g) {
                    var label = g.from === g.to ? g.from : g.from + ' - ' + g.to + ' (' + g.count + ')';
                    $g.append(chip(label, g.from));
                });
                if (res.gap_total > res.gaps.reduce(function (a, g) { return a + g.count; }, 0)) {
                    $g.append($('<span class="text-muted">').text(' dan seterusnya, total ' + res.gap_total.toLocaleString('id-ID') + ' nomor kosong'));
                }
                $panel.append($g);
            }

            $panel.show();
        }

        function load() {
            var q = $.trim($input.val());
            // Hanya untuk kode buatan sendiri: diawali huruf (barcode pabrik angka semua tidak memicu panel).
            if (!/^[A-Za-z]{1,10}\d{0,12}$/.test(q)) { hide(); return; }

            if (request) { request.abort(); }
            request = $.getJSON(url, { q: q })
                .done(function (res) {
                    if ($.trim($input.val()) !== q) { return; }
                    if (res && res.valid) { render(res); } else { hide(); }
                })
                .fail(function (xhr) { if (xhr.statusText !== 'abort') { hide(); } })
                .always(function () { request = null; });
        }

        $input.on('input', function () {
            clearTimeout(timer);
            timer = setTimeout(load, 300);
        });
        $input.on('keydown', function (e) { if (e.key === 'Escape') { hide(); } });
        // Halaman pemakai mengganti isi kolom lewat kode (mis. panel pindah mode tambah/edit): sembunyikan saran lama.
        $input.on('barcode:recheck', hide);
    });
</script>