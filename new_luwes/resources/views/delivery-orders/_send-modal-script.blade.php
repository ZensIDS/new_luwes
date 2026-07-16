<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(function () {
        $(document).on('click', '.btn-confirm-send', function () {
            var formId = $(this).data('form-id');
            var $form = $('#' + formId);
            var code = $form.data('delivery-code');
            var errors = [];

            $form.find('.sample-error').text('').hide();
            $form.find('.qty-sample-input').css('border-color', '');

            $form.find('.qty-sample-input').each(function () {
                var $input = $(this);
                var required = parseInt($input.data('required'));
                var kategori = $input.data('kategori');
                var val = $input.val().trim();
                var $errDiv = $input.next('.sample-error');

                if (val === '') {
                    $errDiv.text('Qty sample wajib diisi.').show();
                    $input.css('border-color', '#a94442');
                    errors.push('"' + kategori + '": wajib diisi');
                } else if (parseInt(val) !== required) {
                    $errDiv.text('Harus tepat ' + required + ' (diisi: ' + val + ').').show();
                    $input.css('border-color', '#a94442');
                    errors.push('"' + kategori + '": harus tepat ' + required);
                }
            });

            if (errors.length > 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validasi Gagal',
                    html: '<ul style="margin-top:8px">' + errors.map(function (e) {
                        return '<li style="text-align:left">' + e + '</li>';
                    }).join('') + '</ul>',
                    confirmButtonText: 'OK',
                });
                return;
            }

            Swal.fire({
                icon: 'question',
                title: 'Konfirmasi Pengiriman',
                text: 'Kirim delivery order ' + code + '?',
                showCancelButton: true,
                confirmButtonText: 'Ya, Kirim',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#5cb85c',
            }).then(function (result) {
                if (result.isConfirmed) {
                    $form.trigger('submit');
                }
            });
        });
    });
</script>
