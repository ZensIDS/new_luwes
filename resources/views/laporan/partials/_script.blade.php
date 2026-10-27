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

    // ---- Pilihan folder (nama awal barang) mengikuti outlet terpilih ----
    function loadFolders(modal) {
        var $folder = modal.find('.js-folder');
        if (!$folder.length) { return; }

        var outletId = modal.find('select[data-param=outlet_id]').val() || '';
        $folder.data('outlet', outletId).prop('disabled', true)
            .empty().append($('<option>').val('').text('Semua barang'));

        if ($.fn.select2 && !$folder.data('select2')) {
            $folder.select2({ width: '100%', dropdownParent: modal });
        }
        if (!outletId) { return; }

        $.getJSON($folder.data('url'), { outlet_id: outletId }).done(function (res) {
            if (String($folder.data('outlet')) !== String(outletId)) { return; } // outlet sudah diganti lagi
            $.each(res.folders || [], function (i, f) {
                $folder.append($('<option>').val(f.key).text(f.label + ' (' + f.total + ')'));
            });
        }).always(function () {
            if (String($folder.data('outlet')) === String(outletId)) { $folder.prop('disabled', false); }
        });
    }
    $(document).on('change', '.rpt-modal select[data-param=outlet_id]', function () { loadFolders($(this).closest('.modal')); });
    $(document).on('shown.bs.modal', '.rpt-modal', function () {
        var modal = $(this);
        if (modal.find('.js-folder').length && !modal.data('foldersLoaded')) {
            modal.data('foldersLoaded', true);
            loadFolders(modal); // outlet sudah terpilih otomatis (mis. staff-outlet) langsung memuat folder
        }
    });

    // ---- Unduh Excel ----
    $(document).on('click', '.js-export', function (event) {
        event.preventDefault();
        var modal = $(this).closest('.modal'), params = {};

        modal.find('[data-param]').each(function () {
            var v = $(this).val();
            if (v) params[$(this).data('param')] = v;
        });

        // Outlet wajib (laporan yang menandainya dengan data-required).
        if (modal.find('[data-required]').length && !params.outlet_id) {
            modal.find('.rpt-error').hide();
            modal.find('.rpt-error-outlet').show();
            return;
        }

        if (params.tanggal_mulai && params.tanggal_selesai && params.tanggal_mulai > params.tanggal_selesai) {
            modal.find('.rpt-error').hide();
            modal.find('.rpt-error-date').show();
            return;
        }
        modal.find('.rpt-error').hide();

        var query = $.param(params), url = $(this).data('url');
        window.location.href = query ? url + '?' + query : url;
        setTimeout(function () { modal.modal('hide'); }, 400);
    });
});
</script>