<script>
$(function () {
    // ---- Pencarian kartu laporan ----
    $('#rptSearch').on('input', function () {
        var q = $.trim($(this).val()).toLowerCase();
        var total = 0;
        $('.rpt-group').each(function () {
            var matches = 0;
            $(this).find('.rpt-card').each(function () {
                var show = !q || $(this).text().toLowerCase().indexOf(q) !== -1;
                $(this).css('display', show ? '' : 'none');
                if (show) matches++;
            });
            $(this).css('display', matches > 0 ? '' : 'none');
            total += matches;
        });
        $('.rpt-empty').css('display', total === 0 ? 'block' : 'none');
    });

    // ---- Preset tanggal ----
    function fmt(d) {
        var m = ('0' + (d.getMonth() + 1)).slice(-2), day = ('0' + d.getDate()).slice(-2);
        return d.getFullYear() + '-' + m + '-' + day;
    }
    $(document).on('click', '.js-preset', function () {
        var modal = $(this).closest('.modal'), now = new Date(), s = new Date(now), e = new Date(now);
        switch ($(this).data('range')) {
            case '7d':        s.setDate(now.getDate() - 6); break;
            case 'month':     s = new Date(now.getFullYear(), now.getMonth(), 1); break;
            case 'lastmonth': s = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                              e = new Date(now.getFullYear(), now.getMonth(), 0); break;
        }
        modal.find('.js-start').val(fmt(s));
        modal.find('.js-end').val(fmt(e));
        modal.find('.rpt-error').hide();
    });

    // ---- Preset satu tanggal (Hari ini / Kemarin) ----
    $(document).on('click', '.js-preset-day', function () {
        var d = new Date();
        d.setDate(d.getDate() + parseInt($(this).data('offset'), 10));
        $(this).closest('.modal').find('.js-day').val(fmt(d));
    });

    // ---- Brand (nama depan barang): Select2 + AJAX, muncul saat ketik >= 2 huruf ----
    $(document).on('shown.bs.modal', '.rpt-modal', function () {
        var modal = $(this);
        modal.find('.js-brand').each(function () {
            var el = $(this);
            if (el.data('select2')) { return; }
            el.select2({
                dropdownParent: modal,
                width: '100%',
                allowClear: true,
                placeholder: el.data('placeholder') || '',
                minimumInputLength: 2,
                language: {
                    inputTooShort: function () { return 'Ketik minimal 2 huruf nama depan barang'; },
                    noResults: function () { return 'Brand tidak ditemukan'; },
                    searching: function () { return 'Mencari...'; }
                },
                ajax: {
                    url: el.data('url'),
                    dataType: 'json',
                    delay: 250,
                    cache: true,
                    data: function (params) {
                        return {
                            q: $.trim(params.term || ''),
                            outlet_id: modal.find('[data-param="outlet_id"]').val() || ''
                        };
                    },
                    processResults: function (items) {
                        return { results: $.map(items, function (brand) { return { id: brand, text: brand }; }) };
                    }
                }
            });
        });
    });

    // Ganti outlet -> kosongkan brand (saran brand tergantung outlet)
    $(document).on('change', '.rpt-modal [data-param="outlet_id"]', function () {
        $(this).closest('.modal').find('.js-brand').val(null).trigger('change');
    });

    // ---- Unduh Excel ----
    $(document).on('click', '.js-export', function (event) {
        event.preventDefault();
        var modal = $(this).closest('.modal'), params = {};

        modal.find('[data-param]').each(function () {
            var v = $(this).val();
            if (v) params[$(this).data('param')] = v;
        });

        if (params.tanggal_mulai && params.tanggal_selesai && params.tanggal_mulai > params.tanggal_selesai) {
            modal.find('.rpt-error').show();
            return;
        }
        modal.find('.rpt-error').hide();

        var query = $.param(params), url = $(this).data('url');
        window.location.href = query ? url + '?' + query : url;
        setTimeout(function () { modal.modal('hide'); }, 400);
    });
});
</script>