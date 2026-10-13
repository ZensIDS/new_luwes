{{--
    Cek barcode otomatis tanpa menunggu simpan (scan maupun ketik manual).
    Variabel: $input (selector input barcode), $url (route product.check-barcode), $ignoreId (id produk yang sedang diedit, opsional)
--}}
<style>
    /* Bootstrap 3 memakai float: saat pesan cek barcode muncul, kolom jadi lebih tinggi dan
       kolom berikutnya meninggalkan ruang kosong. Flex-wrap membuat tiap baris tingginya rata. */
    .barcode-flex-row { display: flex; flex-wrap: wrap; }
    .barcode-flex-row:before, .barcode-flex-row:after { display: none; }
</style>
<script>
    $(function () {
        var $input = $(@json($input));
        if (!$input.length) { return; }
        $input.closest('.form-group').parent('.row').addClass('barcode-flex-row');

        var url = @json($url);
        var ignoreId = @json($ignoreId ?? null);
        var $form = $input.closest('form');
        var $group = $input.closest('.form-group');
        var $feedback = $('<div class="help-block barcode-check-feedback" aria-live="polite"></div>').hide();
        $input.after($feedback);

        var timer = null;
        var request = null;
        var lastChecked = $.trim($input.val());   // nilai awal (mis. saat edit) tidak perlu dicek
        var isDuplicate = false;

        function reset() {
            isDuplicate = false;
            $group.removeClass('has-error has-success');
            $feedback.attr('class', 'help-block barcode-check-feedback').empty().hide();
        }

        function check(force) {
            clearTimeout(timer);
            var code = $.trim($input.val());

            if (code === '') { lastChecked = ''; reset(); return; }
            if (code === lastChecked && force !== true) { return; }
            lastChecked = code;

            if (request) { request.abort(); }
            $group.removeClass('has-error has-success');
            $feedback.attr('class', 'help-block text-muted barcode-check-feedback').text('Memeriksa barcode...').show();

            request = $.getJSON(url, { code: code, ignore_id: ignoreId || '' })
                .done(function (res) {
                    if ($.trim($input.val()) !== code) { return; }   // input sudah berubah lagi

                    if (res.exists && res.product) {
                        isDuplicate = true;
                        $group.removeClass('has-success').addClass('has-error');
                        $feedback.attr('class', 'help-block text-danger barcode-check-feedback').empty()
                            .append(document.createTextNode('Barcode sudah digunakan produk lain: '))
                            .append($('<strong>').text(res.product.name || res.product.code))
                            .append(document.createTextNode(res.product.trashed ? ' (produk ini sudah dihapus)' : ''))
                            .show();
                    } else {
                        isDuplicate = false;
                        $group.removeClass('has-error').addClass('has-success');
                        $feedback.attr('class', 'help-block text-success barcode-check-feedback')
                            .text('Barcode tersedia.').show();
                    }
                })
                .fail(function (xhr) {
                    if (xhr.statusText === 'abort') { return; }
                    reset();   // gagal memeriksa: jangan blokir, validasi server tetap berjalan saat simpan
                })
                .always(function () { request = null; });
        }

        $input.on('input', function () {
            clearTimeout(timer);
            timer = setTimeout(check, 400);
        });
        $input.on('change blur', function () { check(); });

        // Scanner biasanya mengirim Enter di akhir barcode: jangan submit form, cukup cek barcode-nya.
        $input.on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                check(true);
            }
        });

        // Barcode sudah terdeteksi dipakai produk lain: tahan submit.
        $form.on('submit', function (e) {
            if (isDuplicate) {
                e.preventDefault();
                e.stopImmediatePropagation();
                $form.find('[type="submit"]').prop('disabled', false);
                $input.trigger('focus');
            }
        });
    });
</script>