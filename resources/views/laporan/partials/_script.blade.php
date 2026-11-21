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

    // ---- Rafaksi: "Semua rafaksi" atau pilih beberapa (Select2 multi) ----
    function rafaksiCount(modal) {
        var n = (modal.find('.js-rafaksi-select').val() || []).length;
        modal.find('.js-rafaksi-count').text(n + ' dipilih');
    }

    $(document).on('shown.bs.modal', '.rpt-modal', function () {
        var modal = $(this);
        modal.find('.js-rafaksi-select').each(function () {
            var el = $(this);
            if (el.data('select2')) { return; }
            el.select2({
                dropdownParent: modal,
                width: '100%',
                closeOnSelect: false,
                placeholder: el.data('placeholder') || '',
                language: { noResults: function () { return 'Rafaksi tidak ditemukan'; } }
            });
        });
    });

    $(document).on('change', '.js-rafaksi-mode', function () {
        var modal = $(this).closest('.modal'), selected = modal.find('.js-rafaksi-mode:checked').val() === 'selected';
        modal.find('.js-rafaksi-picker').toggle(selected);
        if (!selected) { modal.find('.js-rafaksi-select').val(null).trigger('change'); }
        modal.find('.js-rafaksi-error').hide();
    });

    $(document).on('change', '.js-rafaksi-select', function () {
        var modal = $(this).closest('.modal');
        rafaksiCount(modal);
        if ((modal.find('.js-rafaksi-select').val() || []).length) { modal.find('.js-rafaksi-error').hide(); }
    });

    $(document).on('click', '.js-rafaksi-all', function (e) {
        e.preventDefault();
        var sel = $(this).closest('.modal').find('.js-rafaksi-select');
        sel.val(sel.find('option').map(function () { return this.value; }).get()).trigger('change');
    });

    $(document).on('click', '.js-rafaksi-none', function (e) {
        e.preventDefault();
        $(this).closest('.modal').find('.js-rafaksi-select').val(null).trigger('change');
    });

    // Kumpulkan semua input ber-data-param; nilai kosong (termasuk array kosong) tidak dikirim.
    function collectParams(modal) {
        var params = {};
        modal.find('[data-param]').each(function () {
            var v = $(this).val();
            if (v && (!$.isArray(v) || v.length)) { params[$(this).data('param')] = v; }
        });
        return params;
    }

    // true bila mode "Pilih rafaksi tertentu" aktif tetapi belum ada yang dipilih.
    function rafaksiMissing(modal, params) {
        return modal.find('.js-rafaksi-mode:checked').val() === 'selected' && !params.promotion_ids;
    }

    // ---- Preview laporan (tanpa unduh) ----
    var $pv = $(
        '<div id="rptPreviewModal" class="modal fade" tabindex="-1" role="dialog"><div class="modal-dialog"><div class="modal-content">' +
        '<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button>' +
        '<h4 class="modal-title"><i class="fa fa-eye"></i> <span class="js-pv-title">Preview</span></h4></div>' +
        '<div class="modal-body"><div class="rpt-pv-loading"><i class="fa fa-spinner fa-spin fa-2x"></i><p>Menyiapkan preview...</p></div></div>' +
        '</div></div></div>'
    ).appendTo('body');

    // Preview tampil di atas modal filter (backdrop-nya ikut dinaikkan).
    $pv.on('shown.bs.modal', function () { $('.modal-backdrop').last().css('z-index', 1055); });
    $pv.on('hidden.bs.modal', function () { if ($('.modal.in').length) { $('body').addClass('modal-open'); } });

    function esc(v) { return $('<div>').text(v == null ? '' : v).html(); }

    function showPreview(res) {
        var body = $pv.find('.modal-body'), tabs = '', panes = '';
        $.each(res.sheets, function (i, s) {
            var note = s.total_rows > s.shown_rows
                ? 'Menampilkan ' + s.shown_rows + ' dari ' + s.total_rows + ' baris. Unduh Excel untuk data lengkap.'
                : s.total_rows + ' baris.';
            tabs += '<button type="button" class="btn btn-xs js-pv-tab ' + (i === 0 ? 'btn-primary' : 'btn-default') + '" data-i="' + i + '">' + esc(s.name) + '</button>';
            panes += '<div class="js-pv-pane" data-i="' + i + '"' + (i === 0 ? '' : ' style="display:none"') + '>' +
                '<p class="rpt-pv-note">' + esc(note) + '</p><div class="rpt-pv-scroll">' + s.html + '</div></div>';
        });
        body.html((res.sheets.length > 1 ? '<div class="rpt-pv-tabs">' + tabs + '</div>' : '') + panes);
    }

    $(document).on('click', '.js-pv-tab', function () {
        var i = $(this).data('i');
        $('.js-pv-tab').removeClass('btn-primary').addClass('btn-default');
        $(this).removeClass('btn-default').addClass('btn-primary');
        $('.js-pv-pane').hide().filter('[data-i="' + i + '"]').show();
    });

    $(document).on('click', '.js-preview', function () {
        var modal = $(this).closest('.modal'), params = collectParams(modal);

        modal.find('.rpt-error').hide();
        if (rafaksiMissing(modal, params)) { modal.find('.js-rafaksi-error').show(); return; }
        if (modal.find('.js-brand[data-required]').length && !params.brand) { modal.find('.js-brand-error').show(); return; }
        if (params.tanggal_mulai && params.tanggal_selesai && params.tanggal_mulai > params.tanggal_selesai) { modal.find('.js-date-error').show(); return; }

        $pv.find('.js-pv-title').text('Preview: ' + $(this).data('title'));
        $pv.find('.modal-body').html('<div class="rpt-pv-loading"><i class="fa fa-spinner fa-spin fa-2x"></i><p>Menyiapkan preview...</p></div>');
        $pv.modal('show');

        $.ajax({ url: $(this).data('url'), data: params, dataType: 'json', headers: { 'Accept': 'application/json' }, timeout: 180000 })
            .done(showPreview)
            .fail(function (xhr) {
                var j = xhr.responseJSON || {}, msg = j.message || 'Preview gagal dimuat.';
                if (j.errors) { msg = $.map(j.errors, function (e) { return e.join(' '); }).join(' '); }
                if (xhr.statusText === 'timeout') { msg = 'Preview terlalu lama. Persempit filter lalu coba lagi.'; }
                $pv.find('.modal-body').html('<p class="text-danger" style="margin:20px 0;"><i class="fa fa-exclamation-circle"></i> ' + esc(msg) + '</p>');
            });
    });

    // ---- Unduh Excel ----
    $(document).on('click', '.js-export', function (event) {
        event.preventDefault();
        var modal = $(this).closest('.modal'), params = collectParams(modal);

        modal.find('.rpt-error').hide();

        if (rafaksiMissing(modal, params)) {
            modal.find('.js-rafaksi-error').show();
            return;
        }

        if (modal.find('.js-brand[data-required]').length && !params.brand) {
            modal.find('.js-brand-error').show();
            return;
        }

        if (params.tanggal_mulai && params.tanggal_selesai && params.tanggal_mulai > params.tanggal_selesai) {
            modal.find('.js-date-error').show();
            return;
        }

        var query = $.param(params), url = $(this).data('url');
        window.location.href = query ? url + '?' + query : url;
        setTimeout(function () { modal.modal('hide'); }, 400);
    });
});
</script>