{{-- Dialog hapus penjualan: alasan wajib + ringkasan stok yang dikembalikan.
     Form: .form-delete-sale (data-code, data-items, data-qty) berisi input hidden "reason". --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).on('click', '.btn-delete-sale', function () {
    var form = $(this).closest('form');
    var code = String(form.data('code') || '');
    var items = parseInt(form.data('items'), 10) || 0;
    var qty = parseInt(form.data('qty'), 10) || 0;

    function submitWithReason(reason) {
        form.find('input[name="reason"]').val(reason);
        form.get(0).submit();
    }

    if (typeof Swal === 'undefined') {
        var reason = window.prompt('Hapus penjualan ' + code + '? Stok ' + items + ' item akan dikembalikan.\nAlasan (wajib, min. 5 karakter):');
        if (reason && reason.trim().length >= 5) { submitWithReason(reason.trim()); }
        return;
    }

    var safeCode = $('<div>').text(code).html();
    Swal.fire({
        icon: 'warning',
        title: 'Hapus penjualan ' + safeCode + '?',
        html: '<p>Stok <b>' + items + ' item</b> (total qty <b>' + qty + '</b>) akan dikembalikan ke batch asalnya.</p>'
            + '<p style="font-size:12px;color:#777">Voucher yang dipakai bisa digunakan lagi. Tindakan ini tercatat di Kartu Stok.</p>',
        input: 'textarea',
        inputLabel: 'Alasan penghapusan (wajib)',
        inputAttributes: { maxlength: 255 },
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#d9534f',
        inputValidator: function (value) {
            if (!value || value.trim().length < 5) { return 'Alasan wajib diisi, minimal 5 karakter.'; }
        }
    }).then(function (result) {
        if (result.isConfirmed) { submitWithReason(String(result.value).trim()); }
    });
});
</script>