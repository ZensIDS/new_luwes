@extends('layouts.master')

@section('title', 'Set Stock Toko')

@section('container')
    <style>
        .ss-wrap { display:flex; gap:16px; align-items:flex-start; flex-wrap:wrap; }
        .ss-main { flex:1 1 420px; min-width:0; }
        .ss-scan { font-size:20px; height:46px; letter-spacing:1px; }
        .ss-card { margin-top:14px; border:1px solid #d9dee5; border-radius:6px; background:#fff; padding:16px; display:none; }
        .ss-card h3 { margin:0 0 4px; font-size:20px; line-height:1.25; }
        .ss-card .code { color:#777; font-family:'Courier New',monospace; margin-bottom:10px; }
        .ss-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:10px; margin-bottom:14px; }
        .ss-grid .cell { background:#f6f8fa; border-radius:4px; padding:8px 10px; }
        .ss-grid .cell small { display:block; color:#777; font-size:11px; text-transform:uppercase; letter-spacing:.3px; }
        .ss-grid .cell b { font-size:16px; }
        .ss-qty-row { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
        .ss-qty-row .qty-box { flex:0 0 180px; }
        .ss-qty { font-size:26px; height:54px; text-align:center; font-weight:700; }
        .ss-note { margin-top:8px; font-size:12px; color:#8a6d3b; }
        .ss-msg { margin-top:10px; display:none; }
        .ss-recent { margin-top:14px; font-size:13px; color:#555; }
        .ss-basket-btn { position:relative; }
        .ss-drawer { position:fixed; top:0; right:0; bottom:0; width:520px; max-width:100%; background:#fff; z-index:1100; box-shadow:-4px 0 18px rgba(0,0,0,.2); display:none; flex-direction:column; }
        .ss-drawer.open { display:flex; }
        .ss-drawer header { padding:12px 14px; border-bottom:1px solid #e3e6ea; display:flex; gap:8px; align-items:center; }
        .ss-drawer header h4 { margin:0; flex:1; font-size:17px; }
        .ss-drawer .tools { padding:10px 14px; display:flex; gap:8px; border-bottom:1px solid #eef0f2; }
        .ss-drawer .list { flex:1; overflow:auto; padding:0 14px; }
        .ss-drawer footer { padding:12px 14px; border-top:1px solid #e3e6ea; background:#fafbfc; }
        .ss-table { width:100%; border-collapse:collapse; }
        .ss-table td, .ss-table th { padding:6px 4px; border-bottom:1px solid #f0f1f3; vertical-align:middle; font-size:13px; }
        .ss-table input.q { width:80px; text-align:right; }
        .ss-table .nm { max-width:200px; }
        .ss-table .nm div:first-child { font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .ss-table .nm small { color:#888; }
        .ss-failures { max-height:130px; overflow:auto; font-size:12px; }
        .ss-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:1090; display:none; }
        .ss-backdrop.open { display:block; }
    </style>

    <section class="content-header">
        <h1>Set Stock <small>Isi / koreksi stok toko dengan scan barcode</small></h1>
    </section>

    <section class="content">
        <div class="box">
            <div class="box-body">
                @if ($outlets->count() > 1)
                    <form method="GET" action="{{ route('set-stock.index') }}" class="form-inline" style="margin-bottom:14px;">
                        <label>Toko&nbsp;</label>
                        <select name="outlet_id" class="form-control" onchange="this.form.submit()">
                            <option value="">-- Pilih Toko --</option>
                            @foreach ($outlets as $outlet)
                                <option value="{{ $outlet->id }}" {{ (int) $outletId === (int) $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif

                @if (! $selectedOutlet)
                    <p class="text-muted">Pilih toko terlebih dahulu untuk mulai scan.</p>
                @else
                    <div class="alert alert-warning" style="margin-bottom:14px;">
                        <i class="fa fa-exclamation-triangle"></i>
                        Qty yang disimpan <b>menimpa</b> stok toko <b>{{ $selectedOutlet->name }}</b> (stok sebelumnya ditampilkan di kartu produk). Bila stok sedang <b>minus</b> (sudah ada penjualan), qty ini menutup minus lebih dulu: misal minus 2 + Set Stock 10 = stok 8.
                        Keranjang dipakai bersama oleh semua pengguna toko ini dan otomatis tersimpan.
                    </div>

                    <div style="margin-bottom:14px;">
                        <button type="button" class="btn btn-success btn-sm" id="btnExport" data-toggle="modal" data-target="#exportModal">
                            <i class="fa fa-file-excel-o"></i> Export Excel
                        </button>
                    </div>

                    <div class="ss-wrap">
                        <div class="ss-main">
                            <div class="input-group">
                                <input type="text" id="scanInput" class="form-control ss-scan" placeholder="Scan / ketik barcode lalu Enter" autocomplete="off" autofocus>
                                <span class="input-group-btn">
                                    <button type="button" id="btnBasket" class="btn btn-primary btn-lg ss-basket-btn">
                                        <i class="fa fa-shopping-basket"></i> Isi Keranjang (<span id="basketCount">0</span>)
                                    </button>
                                </span>
                            </div>
                            <div id="scanMsg" class="alert alert-danger ss-msg"></div>

                            <div id="productCard" class="ss-card">
                                <h3 id="pName"></h3>
                                <div class="code" id="pCode"></div>
                                <div class="ss-grid">
                                    <div class="cell"><small>Stok sistem saat ini</small><b id="pStock">0</b></div>
                                    <div class="cell"><small>HPP</small><b id="pHpp">-</b></div>
                                    <div class="cell"><small>Harga coret</small><b id="pStrike">-</b></div>
                                    <div class="cell"><small>Harga jual POS</small><b id="pNet">-</b></div>
                                </div>
                                <div class="ss-qty-row">
                                    <div class="qty-box">
                                        <label for="qtyInput" id="qtyLabel">Qty baru (menimpa stok)</label>
                                        <input type="number" id="qtyInput" class="form-control ss-qty" min="0" step="1" inputmode="numeric">
                                    </div>
                                    <button type="button" id="btnAdd" class="btn btn-success btn-lg"><i class="fa fa-plus"></i> Tambah ke keranjang (Enter)</button>
                                    <button type="button" id="btnCancel" class="btn btn-default btn-lg">Batal (Esc)</button>
                                </div>
                                <div class="ss-note" id="pDraftNote" style="display:none;"></div>
                                <div class="ss-note" id="pPreview" style="display:none;"></div>
                                <div class="ss-note" id="pHppWarn" style="display:none;">HPP produk ini 0. Cek harga beli produk sebelum menyimpan.</div>
                            </div>

                            <div class="ss-recent" id="lastAdded"></div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if ($selectedOutlet)
        {{-- Modal export Excel (GET, file langsung diunduh; halaman tidak berpindah). --}}
        <div class="modal fade" id="exportModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <form method="GET" action="{{ route('set-stock.export') }}" class="modal-content">
                    <input type="hidden" name="outlet_id" value="{{ $selectedOutlet->id }}">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-file-excel-o"></i> Export Excel Set Stock <small>{{ $selectedOutlet->name }}</small></h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Jenis</label>
                            <div class="radio"><label><input type="radio" name="jenis" value="sudah"> Sudah di-set</label></div>
                            <div class="radio"><label><input type="radio" name="jenis" value="belum"> Belum di-set</label></div>
                            <div class="radio"><label><input type="radio" name="jenis" value="semua" checked> Cetak semua (sudah di atas, belum di bawah)</label></div>
                        </div>
                        <div class="form-group">
                            <label for="exportName">Filter nama depan produk</label>
                            <select name="nama_awal" id="exportName" class="form-control" style="width:100%;" data-placeholder="Ketik 2 huruf nama depan, mis. IN (kosongkan untuk semua produk)"><option value=""></option></select>
                            <p class="help-block" style="margin-bottom:0;">Ketik minimal 2 huruf lalu pilih dari daftar (atau pakai teks yang diketik). Hanya produk yang namanya diawali teks ini yang di-export.</p>
                        </div>
                        <div class="row">
                            <div class="col-xs-6 form-group">
                                <label for="exportFrom">Dari tanggal</label>
                                <input type="date" name="tanggal_mulai" id="exportFrom" class="form-control" value="{{ now('Asia/Jakarta')->toDateString() }}" required>
                            </div>
                            <div class="col-xs-6 form-group">
                                <label for="exportTo">Sampai tanggal</label>
                                <input type="date" name="tanggal_selesai" id="exportTo" class="form-control" value="{{ now('Asia/Jakarta')->toDateString() }}" required>
                            </div>
                        </div>
                        <p class="help-block" style="margin-bottom:0;">
                            Rentang tanggal memakai waktu item Set Stock (WIB). "Belum di-set" = produk yang belum masuk Set Stock pada rentang tanggal itu.
                            Item yang masih di keranjang dihitung sudah dan diberi status "Di keranjang (belum disimpan)".
                            Hanya produk yang harga jualnya sudah diatur.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success"><i class="fa fa-download"></i> Unduh Excel</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="ss-backdrop" id="drawerBackdrop"></div>
        <aside class="ss-drawer" id="drawer">
            <header>
                <h4><i class="fa fa-shopping-basket"></i> Isi Keranjang <small id="basketSummary"></small></h4>
                <button type="button" class="btn btn-default btn-sm" id="btnReload" title="Muat ulang"><i class="fa fa-refresh"></i></button>
                <button type="button" class="btn btn-default btn-sm" id="btnCloseDrawer"><i class="fa fa-times"></i></button>
            </header>
            <div class="tools">
                <input type="search" id="basketSearch" class="form-control input-sm" placeholder="Cari di keranjang (kode / nama)">
            </div>
            <div class="list">
                <table class="ss-table">
                    <thead><tr><th>Produk</th><th style="text-align:right">Qty</th><th></th></tr></thead>
                    <tbody id="basketBody"><tr><td colspan="3" class="text-center text-muted" style="padding:20px;">Memuat...</td></tr></tbody>
                </table>
            </div>
            <footer>
                <div id="saveFailures" class="alert alert-danger ss-failures" style="display:none;"></div>
                <button type="button" id="btnSave" class="btn btn-primary btn-block btn-lg" disabled><i class="fa fa-save"></i> Simpan Set Stock</button>
            </footer>
        </aside>
    @endif
@endsection

@if ($selectedOutlet)
@section('page-script')
    <script>
        $(function () {
            const outletId = @json($selectedOutlet->id);
            const urls = {
                scan: @json(route('set-stock.scan')),
                draft: @json(route('set-stock.draft')),
                store: @json(route('set-stock.item.store')),
                destroy: @json(url('set-stock/item')),
                save: @json(route('set-stock.save')),
            };
            const csrf = @json(csrf_token());
            const nf = new Intl.NumberFormat('id-ID');
            const $scan = $('#scanInput'), $qty = $('#qtyInput'), $card = $('#productCard');
            let current = null;      // {product, item}
            let busy = false;
            let saving = false;
            let basket = [];

            const esc = (v) => $('<div>').text(v == null ? '' : v).html();
            const rp = (n) => 'Rp ' + nf.format(Math.round(n || 0));

            function api(method, url, data) {
                return $.ajax({
                    url: url, method: method, dataType: 'json',
                    data: Object.assign({ outlet_id: outletId }, data || {}),
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
            }
            const errText = (xhr, fallback) => (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || fallback;

            function showMsg(text, type) {
                $('#scanMsg').removeClass('alert-danger alert-success alert-warning').addClass('alert-' + (type || 'danger')).text(text).show();
            }
            function hideMsg() { $('#scanMsg').hide(); }

            function focusScan() { $scan.val('').focus(); }

            function closeCard() { current = null; $card.hide(); $('#pPreview').hide(); focusScan(); }

            // Pratinjau hasil akhir stok: set ulang < 72 jam = stok sekarang + qty, selain itu = qty menimpa stok.
            function updatePreview() {
                if (!current) { $('#pPreview').hide(); return; }
                const p = current.product, raw = $.trim($qty.val());
                if (raw === '' || !/^\d+$/.test(raw)) { $('#pPreview').hide(); return; }
                const q = parseInt(raw, 10);
                const d = p.deficit || 0; // saldo minus (<= 0), ditutup lebih dulu oleh qty Set Stock
                let msg;
                if (p.additive) {
                    msg = 'Stok sistem ' + nf.format(p.stock_now) + ' + ' + nf.format(q) + ' = ' + nf.format(p.stock_now + q) + ' (ditambah, bukan menimpa)';
                } else if (d < 0) {
                    msg = 'Stok minus ' + nf.format(d) + ' ditutup dulu: ' + nf.format(q) + ' ' + nf.format(d) + ' = ' + nf.format(q + d) + ' (minus tidak dihapus)';
                } else {
                    msg = 'Stok akan menjadi ' + nf.format(q) + ' (menimpa stok sistem ' + nf.format(p.stock_now) + ')';
                }
                $('#pPreview').show().text(msg);
            }
            $qty.on('input', updatePreview);

            function showCard(data) {
                current = data;
                const p = data.product, it = data.item;
                $('#pName').text(p.name);
                $('#pCode').text(p.code);
                $('#pStock').text(nf.format(p.stock_now) + ' ' + (p.satuan || ''));
                $('#pHpp').text(rp(p.hpp));
                $('#pStrike').text(p.price_strike > p.price_net ? rp(p.price_strike) : '-');
                $('#pNet').text(rp(p.price_net));
                $('#pHppWarn').toggle(!(p.hpp > 0));
                $('#qtyLabel').text(p.additive ? 'Qty tambahan (ditambah ke stok, set ulang < 72 jam)' : 'Qty baru (menimpa stok)');
                updatePreview();
                if (it) {
                    $('#pDraftNote').show().text('Sudah ada di keranjang: qty ' + it.qty + ' (discan ' + it.scan_count + 'x, terakhir diubah ' + (it.updated_by || '-') + ' ' + (it.updated_at || '') + '). Qty terisi kembali, cek ulang lalu Enter.');
                    $qty.val(it.qty);
                } else {
                    $('#pDraftNote').hide();
                    $qty.val('');
                }
                updatePreview();
                $card.show();
                $qty.trigger('focus').trigger('select');
            }

            // ---- scan ----
            $scan.on('keydown', function (e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                const code = $.trim($scan.val());
                if (!code || busy) return;
                busy = true; hideMsg();
                api('GET', urls.scan, { barcode: code })
                    .done(showCard)
                    .fail(function (xhr) { $card.hide(); current = null; showMsg(errText(xhr, 'Gagal mencari barcode.')); $scan.trigger('select'); })
                    .always(function () { busy = false; });
            });

            // ---- tambah ke keranjang ----
            function addToBasket() {
                if (!current || busy) return;
                const raw = $.trim($qty.val());
                if (raw === '' || !/^\d+$/.test(raw)) { showMsg('Qty harus diisi dengan bilangan bulat 0 atau lebih.', 'warning'); $qty.trigger('focus'); return; }
                busy = true; hideMsg();
                api('POST', urls.store, {
                    product_id: current.product.id,
                    qty: parseInt(raw, 10),
                    expected_version: current.item ? current.item.version : 0,
                }).done(function (res) {
                    $('#lastAdded').html('<i class="fa fa-check text-green"></i> <b>' + esc(current.product.name) + '</b> &rarr; qty ' + res.item.qty);
                    closeCard();
                    loadBasket(true);
                }).fail(function (xhr) {
                    if (xhr.status === 409 && xhr.responseJSON) {
                        current.item = xhr.responseJSON.item;
                        showMsg(xhr.responseJSON.message, 'warning');
                        $('#pDraftNote').show().text('Qty di keranjang sekarang ' + current.item.qty + ' (diubah ' + (current.item.updated_by || '-') + '). Tekan Enter lagi bila memang ingin menimpa.');
                        $qty.val(current.item.qty).trigger('focus').trigger('select');
                    } else {
                        showMsg(errText(xhr, 'Gagal menyimpan ke keranjang.'));
                    }
                }).always(function () { busy = false; });
            }
            $('#btnAdd').on('click', addToBasket);
            $('#btnCancel').on('click', closeCard);
            $qty.on('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); addToBasket(); }
                if (e.key === 'Escape') { e.preventDefault(); closeCard(); }
            });

            // ---- keranjang ----
            function renderBasket() {
                const q = $.trim($('#basketSearch').val()).toLowerCase();
                const rows = basket.filter(function (i) { return !q || (i.code + ' ' + i.name).toLowerCase().indexOf(q) !== -1; });
                $('#basketCount').text(basket.length);
                $('#btnSave').prop('disabled', saving || !basket.length);
                $('#basketSummary').text(basket.length ? '(' + basket.length + ' produk, total qty ' + nf.format(basket.reduce(function (s, i) { return s + i.qty; }, 0)) + ')' : '');
                if (!rows.length) {
                    $('#basketBody').html('<tr><td colspan="3" class="text-center text-muted" style="padding:20px;">' + (basket.length ? 'Tidak ada yang cocok.' : 'Keranjang kosong.') + '</td></tr>');
                    return;
                }
                $('#basketBody').html(rows.map(function (i) {
                    return '<tr data-id="' + i.id + '" data-version="' + i.version + '" data-product="' + i.product_id + '">' +
                        '<td class="nm"><div title="' + esc(i.name) + '">' + esc(i.name) + (i.additive ? ' <span class="label label-warning" title="Set ulang < 72 jam: qty ditambah ke stok">+ tambah</span>' : '') + '</div><small>' + esc(i.code) + ' &middot; ' + esc(i.updated_by || '-') + ' ' + esc(i.updated_at || '') + (i.scan_count > 1 ? ' &middot; ' + i.scan_count + 'x scan' : '') + '<br>' + (i.additive ? 'Stok ' + nf.format(i.stock_now) + ' + ' + nf.format(i.qty) + ' = <b>' + nf.format(i.stock_now + i.qty) + '</b>' : 'Stok ' + nf.format(i.stock_now) + ' &rarr; <b>' + nf.format(i.qty + (i.deficit || 0)) + '</b>' + (i.deficit < 0 ? ' (minus ' + nf.format(i.deficit) + ' ditutup)' : '')) + '</small></td>' +
                        '<td style="text-align:right"><input type="number" min="0" step="1" class="form-control input-sm q" value="' + i.qty + '" data-orig="' + i.qty + '"></td>' +
                        '<td style="text-align:right"><button type="button" class="btn btn-danger btn-xs btn-del"><i class="fa fa-trash"></i></button></td></tr>';
                }).join(''));
            }

            function loadBasket(silent) {
                return api('GET', urls.draft).done(function (res) {
                    basket = res.items || [];
                    renderBasket();
                }).fail(function (xhr) {
                    if (!silent) $('#basketBody').html('<tr><td colspan="3" class="text-center text-danger">' + esc(errText(xhr, 'Gagal memuat keranjang.')) + '</td></tr>');
                });
            }

            function openDrawer() { $('#drawer').addClass('open'); $('#drawerBackdrop').addClass('open'); loadBasket(); }
            function closeDrawer() { $('#drawer').removeClass('open'); $('#drawerBackdrop').removeClass('open'); focusScan(); }
            $('#btnBasket').on('click', openDrawer);
            $('#btnCloseDrawer, #drawerBackdrop').on('click', closeDrawer);
            $('#btnReload').on('click', function () { loadBasket(); });
            $('#basketSearch').on('input', renderBasket);

            $('#basketBody').on('change', 'input.q', function () {
                const $row = $(this).closest('tr'), $input = $(this);
                const raw = $.trim($input.val());
                if (!/^\d+$/.test(raw)) { alert('Qty harus bilangan bulat 0 atau lebih.'); $input.val($input.data('orig')); return; }
                api('POST', urls.store, { product_id: $row.data('product'), qty: parseInt(raw, 10), expected_version: $row.data('version') })
                    .done(function () { loadBasket(true); })
                    .fail(function (xhr) {
                        alert(errText(xhr, 'Gagal mengubah qty.'));
                        loadBasket(true);
                    });
            });
            $('#basketBody').on('click', '.btn-del', function () {
                const $row = $(this).closest('tr');
                if (!confirm('Hapus item ini dari keranjang?')) return;
                api('DELETE', urls.destroy + '/' + $row.data('id'))
                    .done(function () { loadBasket(true); })
                    .fail(function (xhr) { alert(errText(xhr, 'Gagal menghapus item.')); loadBasket(true); });
            });

            // ---- simpan ----
            $('#btnSave').on('click', function () {
                if (saving || !basket.length) return;
                const addN = basket.filter(function (i) { return i.additive; }).length;
                const note = addN
                    ? addN + ' produk (set ulang < 72 jam) akan DITAMBAH ke stok; ' + (basket.length - addN) + ' produk lainnya MENIMPA stok.'
                    : 'Qty akan MENIMPA stok toko ini.';
                if (!confirm('Simpan ' + basket.length + ' produk? ' + note + ' Tidak bisa dibatalkan dari halaman ini.')) return;
                saving = true;
                const $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Menyimpan...');
                $('#saveFailures').hide().empty();
                const token = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Date.now()) + Math.random();
                api('POST', urls.save, { token: token })
                    .done(function (res) {
                        alert(res.message);
                        basket = []; renderBasket(); closeDrawer(); $card.hide(); current = null;
                        $('#lastAdded').html('<i class="fa fa-check text-green"></i> ' + esc(res.message));
                    })
                    .fail(function (xhr) {
                        const body = xhr.responseJSON || {};
                        const html = '<b>' + esc(body.message || 'Gagal menyimpan.') + '</b>' +
                            ((body.failures || []).length ? '<ul style="padding-left:18px;margin:6px 0 0;">' + body.failures.map(function (f) { return '<li>' + esc(f) + '</li>'; }).join('') + '</ul>' : '');
                        $('#saveFailures').html(html).show();
                        loadBasket(true);
                    })
                    .always(function () {
                        saving = false;
                        $btn.html('<i class="fa fa-save"></i> Simpan Set Stock');
                        renderBasket();
                    });
            });

            // Filter nama depan produk (export): Select2 + saran brand dari folder Master Harga Jual
            $('#exportModal').on('shown.bs.modal', function () {
                const $name = $('#exportName');
                if ($name.data('select2')) { return; }
                $name.select2({
                    dropdownParent: $('#exportModal'),
                    width: '100%',
                    allowClear: true,
                    tags: true,
                    placeholder: $name.data('placeholder') || '',
                    minimumInputLength: 2,
                    createTag: function (params) {
                        const term = $.trim(params.term || '').toUpperCase();
                        return term.length >= 2 ? { id: term, text: term } : null;
                    },
                    language: {
                        inputTooShort: function () { return 'Ketik minimal 2 huruf nama depan produk'; },
                        searching: function () { return 'Mencari...'; }
                    },
                    ajax: {
                        url: @json(route('laporan.outlet.stok-harian.brands')),
                        dataType: 'json',
                        delay: 250,
                        cache: true,
                        data: function (params) { return { q: $.trim(params.term || ''), outlet_id: outletId }; },
                        processResults: function (items) {
                            return { results: $.map(items, function (brand) { return { id: brand, text: brand }; }) };
                        }
                    }
                });
            });

            // pulihkan keranjang saat halaman dibuka
            loadBasket(true);
            focusScan();
        });
    </script>
@endsection
@endif