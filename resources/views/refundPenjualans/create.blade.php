@extends('layouts.master')

@section('title', 'Retur Penjualan')
@section('body_class', 'pos-mode return-pos-mode')

@section('container')
    <section class="content return-pos-content">
        <div class="return-pos-header">
            <div>
                <strong><i class="fa fa-exchange"></i> Retur / Ganti Barang</strong>
                <span class="text-muted">Scan barang, hitung nilainya, lalu scan barang pengganti.</span>
            </div>
            <div class="return-pos-actions">
                @if ($outletId)
                    <a href="{{ route('outlet.show', $outletId) }}" class="btn btn-xs btn-primary"><i class="fa fa-shopping-cart"></i> Kasir Penjualan</a>
                @endif
                <a href="{{ route('refundPenjualan.index') }}" class="btn btn-xs btn-default"><i class="fa fa-list"></i> History Retur</a>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul style="margin:0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('refundPenjualan.store') }}" id="returnForm">
            @csrf
            <input type="hidden" name="code" value="{{ $code }}">
            <input type="hidden" name="penjualan_id" id="penjualan_id" value="">

            <div class="return-pos-layout">
                <div class="return-pos-main">
                    <div class="return-pos-box">
                        <div class="return-pos-box-header">
                            <span class="step-number">1</span>
                            <div><strong>Barang yang dikembalikan</strong><small>Scan satu per satu. Scan ulang untuk menambah qty.</small></div>
                        </div>
                        <div class="return-pos-box-body">
                            <div class="row">
                                <div class="col-sm-4 form-group">
                                    <label for="outlet_id">Outlet</label>
                                    @if ($outlets->count() === 1)
                                        <select id="outlet_id" class="form-control" disabled>
                                            <option value="{{ $outlets->first()->id }}" selected>{{ $outlets->first()->name }}</option>
                                        </select>
                                        <input type="hidden" name="outlet_id" value="{{ $outlets->first()->id }}">
                                    @else
                                        <select name="outlet_id" id="outlet_id" class="form-control" required>
                                            <option value="">Pilih outlet</option>
                                            @foreach ($outlets as $outlet)
                                                <option value="{{ $outlet->id }}" @selected((string) $outletId === (string) $outlet->id)>{{ $outlet->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                                <div class="col-sm-8 form-group">
                                    <label for="receipt_barcode">Scan no nota <small>(opsional)</small></label>
                                    <div class="input-group">
                                        <input type="text" id="receipt_barcode" class="form-control" placeholder="Scan barcode nota untuk menghubungkan retur ke penjualan" autocomplete="off">
                                        <span class="input-group-btn"><button type="button" class="btn btn-default" id="receiptButton"><i class="fa fa-search"></i> Hubungkan</button></span>
                                    </div>
                                    <small id="receiptStatus" class="help-block">Tanpa nota tetap bisa diproses sebagai data retur baru.</small>
                                </div>
                            </div>
                            <div class="scan-line">
                                <label for="return_barcode">Scan barang retur <small>(Enter)</small></label>
                                <input type="text" id="return_barcode" class="form-control input-lg" placeholder="Scan barcode barang yang dibawa customer" autocomplete="off" disabled>
                            </div>
                            <div class="table-responsive return-table-wrap">
                                <table class="table table-bordered table-striped table-condensed">
                                    <thead><tr><th>Produk</th><th class="text-center">Qty</th><th class="text-right">Harga retur</th><th class="text-right">Subtotal</th><th></th></tr></thead>
                                    <tbody id="returnRows"><tr><td colspan="5" class="text-center text-muted empty-row">Belum ada barang retur. Scan barcode untuk mulai.</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="return-pos-box replacement-step">
                        <div class="return-pos-box-header">
                            <span class="step-number">2</span>
                            <div><strong>Barang pengganti</strong><small>Nilai total harus sama atau lebih besar dari barang yang dikembalikan.</small></div>
                        </div>
                        <div class="return-pos-box-body">
                            <div class="scan-line">
                                <label for="replacement_barcode">Scan barang pengganti <small>(Enter)</small></label>
                                <input type="text" id="replacement_barcode" class="form-control input-lg" placeholder="Scan barang yang dipilih sebagai ganti baru" autocomplete="off" disabled>
                            </div>
                            <div class="table-responsive return-table-wrap">
                                <table class="table table-bordered table-striped table-condensed">
                                    <thead><tr><th>Produk</th><th class="text-center">Qty</th><th class="text-right">Harga jual</th><th class="text-right">Subtotal</th><th></th></tr></thead>
                                    <tbody id="replacementRows"><tr><td colspan="5" class="text-center text-muted empty-row">Scan barang retur terlebih dahulu.</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="return-pos-summary">
                    <div class="return-pos-box summary-box">
                        <div class="return-pos-box-header"><span class="step-number">3</span><div><strong>Konfirmasi</strong><small>Periksa nilai tukar sebelum simpan.</small></div></div>
                        <div class="return-pos-box-body">
                            <dl class="summary-list">
                                <dt>Nilai barang retur</dt><dd id="returnedTotalText">Rp 0</dd>
                                <dt>Nilai barang pengganti</dt><dd id="replacementTotalText">Rp 0</dd>
                                <dt class="difference-label">Selisih dibayar</dt><dd id="differenceText" class="difference-value">Tidak ada selisih</dd>
                            </dl>
                            <div id="paymentBox" style="display:none">
                                <hr>
                                <div class="form-group">
                                    <label for="difference_paid">Selisih dibayar</label>
                                    <input type="text" inputmode="numeric" name="difference_paid" id="difference_paid" class="form-control" value="0" data-currency-input data-currency-decimals="0">
                                </div>
                                <div class="form-group">
                                    <label for="payment_method_name">Metode pembayaran</label>
                                    <select name="payment_method_name" id="payment_method_name" class="form-control">
                                        <option value="Tunai">Tunai</option><option value="Transfer">Transfer</option><option value="QRIS">QRIS</option><option value="Debit">Debit</option>
                                    </select>
                                </div>
                                <div class="form-group" id="paymentReferenceBox" style="display:none">
                                    <label for="payment_reference">Nomor referensi <small>(non-tunai)</small></label>
                                    <input type="text" name="payment_reference" id="payment_reference" class="form-control" maxlength="150">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="notes">Catatan <small>(opsional)</small></label>
                                <textarea name="notes" id="notes" rows="2" class="form-control"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="return_pin">PIN retur <span class="text-danger">*</span></label>
                                <input type="password" name="return_pin" id="return_pin" class="form-control" inputmode="numeric" pattern="[0-9]{4,8}" minlength="4" maxlength="8" autocomplete="off" required>
                                <small class="help-block">Masukkan PIN yang diatur oleh superadmin untuk mengonfirmasi retur.</small>
                            </div>
                            <div id="formError" class="alert alert-danger" style="display:none"></div>
                            <button type="submit" class="btn btn-success btn-lg btn-block" id="submitButton" disabled><i class="fa fa-check"></i> Simpan Retur</button>
                            <a href="{{ route('refundPenjualan.index') }}" class="btn btn-default btn-block">Batal</a>
                        </div>
                    </div>
                    <p class="text-muted small return-help"><i class="fa fa-info-circle"></i> Nota hanya diperlukan jika ingin menghubungkan retur dengan transaksi lama. Stok retur masuk kembali dan stok barang pengganti berkurang saat disimpan.</p>
                </aside>
            </div>
        </form>
    </section>
@endsection

@section('page-script')
    <style>
        html:has(body.return-pos-mode), body.return-pos-mode { height:100%; overflow:hidden; }
        .return-pos-mode .wrapper { height:100vh; min-height:0; overflow:hidden; }
        .return-pos-mode .main-header, .return-pos-mode .main-sidebar, .return-pos-mode .main-footer { display:none !important; }
        .return-pos-mode .content-wrapper { margin-left:0 !important; min-height:100vh !important; height:100vh; overflow:hidden; background:#f4f6f9; }
        .return-pos-content { height:100%; min-height:0 !important; padding:15px; overflow:auto; background:#f4f6f9; }
        .return-pos-header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; padding:10px 14px; background:#fff; border-left:4px solid #605ca8; box-shadow:0 1px 2px rgba(0,0,0,.08); font-size:16px; }
        .return-pos-header .text-muted { display:block; margin-top:3px; font-size:12px; }
        .return-pos-actions { display:flex; gap:5px; flex-wrap:wrap; justify-content:flex-end; }
        .return-pos-layout { display:flex; align-items:flex-start; gap:12px; }
        .return-pos-main { flex:1 1 auto; min-width:0; }
        .return-pos-summary { flex:0 0 340px; }
        .return-pos-box { margin-bottom:12px; background:#fff; border-top:3px solid #605ca8; box-shadow:0 1px 2px rgba(0,0,0,.08); }
        .return-pos-box-header { display:flex; align-items:center; gap:10px; padding:10px 14px; border-bottom:1px solid #eee; }
        .return-pos-box-header strong { display:block; font-size:16px; }
        .return-pos-box-header small { display:block; color:#777; margin-top:2px; }
        .step-number { display:inline-block; width:28px; height:28px; border-radius:50%; background:#605ca8; color:#fff; text-align:center; line-height:28px; font-weight:bold; }
        .return-pos-box-body { padding:14px; }
        .scan-line { margin-bottom:12px; }
        .scan-line label { display:block; font-size:14px; }
        .scan-line input { height:44px; font-size:18px; }
        .return-table-wrap { max-height:38vh; overflow:auto; border:1px solid #ddd; }
        .return-table-wrap table { margin-bottom:0; }
        .return-table-wrap th { position:sticky; top:0; z-index:1; background:#fff; }
        .return-table-wrap td, .return-table-wrap th { vertical-align:middle !important; }
        .empty-row { padding:24px !important; }
        .summary-list { display:grid; grid-template-columns:1fr auto; gap:12px 8px; margin:0; }
        .summary-list dt { font-weight:normal; color:#666; }
        .summary-list dd { margin:0; text-align:right; font-weight:bold; }
        .difference-label, .difference-value { color:#d58512; }
        .return-help { line-height:1.5; }
        @media (max-width:900px) {
            html:has(body.return-pos-mode), body.return-pos-mode { overflow:auto; }
            .return-pos-mode .wrapper, .return-pos-mode .content-wrapper { height:auto; min-height:100vh; overflow:visible; }
            .return-pos-header, .return-pos-layout { display:block; }
            .return-pos-actions { justify-content:flex-start; margin-top:8px; }
            .return-pos-summary { width:auto; }
            .return-table-wrap { max-height:none; }
        }
    </style>
    <script>
        (() => {
            const endpoint = @json(route('refundPenjualan.products'));
            const invoiceEndpoint = @json(route('refundPenjualan.invoices'));
            const outlet = document.getElementById('outlet_id');
            const receipt = document.getElementById('receipt_barcode');
            const receiptButton = document.getElementById('receiptButton');
            const receiptStatus = document.getElementById('receiptStatus');
            const saleId = document.getElementById('penjualan_id');
            const returnInput = document.getElementById('return_barcode');
            const replacementInput = document.getElementById('replacement_barcode');
            const returnRows = document.getElementById('returnRows');
            const replacementRows = document.getElementById('replacementRows');
            const paymentBox = document.getElementById('paymentBox');
            const differencePaid = document.getElementById('difference_paid');
            const paymentMethod = document.getElementById('payment_method_name');
            const paymentReferenceBox = document.getElementById('paymentReferenceBox');
            const paymentReference = document.getElementById('payment_reference');
            const submitButton = document.getElementById('submitButton');
            const form = document.getElementById('returnForm');
            const formError = document.getElementById('formError');
            const state = { sale: null, returns: [], replacements: [] };

            const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
            const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));
            const outletId = () => outlet?.value || '';
            const showError = (message) => { formError.textContent = message; formError.style.display = ''; };
            const clearError = () => { formError.textContent = ''; formError.style.display = 'none'; };

            const updateInputs = () => {
                const ready = Boolean(outletId());
                returnInput.disabled = !ready;
                replacementInput.disabled = !ready || state.returns.length === 0;
                if (!ready) { returnInput.value = ''; replacementInput.value = ''; }
            };

            const totals = () => ({
                returned: state.returns.reduce((sum, item) => sum + Number(item.price) * Number(item.qty), 0),
                replacement: state.replacements.reduce((sum, item) => sum + Number(item.price) * Number(item.qty), 0),
            });

            const render = () => {
                returnRows.innerHTML = state.returns.length ? state.returns.map((item, index) => `
                    <tr><td><strong>${esc(item.code)}</strong> — ${esc(item.name)}${item.item_id ? '<br><small class="text-info">Terhubung ke nota</small>' : ''}</td>
                    <td class="text-center">
                        <input type="number" class="form-control input-sm return-qty" data-index="${index}" min="1"${item.available_qty ? ` max="${item.available_qty}"` : ''} value="${item.qty}" style="width:80px; margin:auto;">
                        ${item.available_qty ? `<small class="text-muted">maks ${item.available_qty}</small>` : ''}
                    </td>
                    <td class="text-right">${money(item.price)}</td><td class="text-right">${money(item.price * item.qty)}</td>
                    <td class="text-center"><button type="button" class="btn btn-xs btn-danger remove-return" data-index="${index}"><i class="fa fa-trash"></i></button></td></tr>`).join('')
                    : '<tr><td colspan="5" class="text-center text-muted empty-row">Belum ada barang retur. Scan barcode untuk mulai.</td></tr>';
                replacementRows.innerHTML = state.replacements.length ? state.replacements.map((item, index) => `
                    <tr><td><strong>${esc(item.code)}</strong> — ${esc(item.name)}</td><td class="text-center"><input type="number" class="form-control input-sm replacement-qty" data-index="${index}" min="1" value="${item.qty}" style="width:80px; margin:auto;"></td>
                    <td class="text-right">${money(item.price)}</td><td class="text-right">${money(item.price * item.qty)}</td>
                    <td class="text-center"><button type="button" class="btn btn-xs btn-danger remove-replacement" data-index="${index}"><i class="fa fa-trash"></i></button></td></tr>`).join('')
                    : '<tr><td colspan="5" class="text-center text-muted empty-row">Scan barang retur terlebih dahulu.</td></tr>';

                const { returned, replacement } = totals();
                const difference = Math.max(0, replacement - returned);
                document.getElementById('returnedTotalText').textContent = money(returned);
                document.getElementById('replacementTotalText').textContent = money(replacement);
                document.getElementById('differenceText').textContent = difference ? money(difference) : 'Tidak ada selisih';
                paymentBox.style.display = difference > 0 ? '' : 'none';
                differencePaid.value = window.formatIdNumber ? window.formatIdNumber(difference) : String(difference);
                const isCash = /tunai|cash/i.test(paymentMethod.value);
                paymentReferenceBox.style.display = difference > 0 && !isCash ? '' : 'none';
                paymentReference.required = difference > 0 && !isCash;
                if (isCash) paymentReference.value = '';
                submitButton.disabled = !state.returns.length || !state.replacements.length || replacement < returned;
                updateInputs();
            };

            const fetchProduct = (barcode, purpose) => {
                const params = new URLSearchParams({ outlet_id: outletId(), barcode, purpose });
                return fetch(`${endpoint}?${params.toString()}`, { headers: { Accept: 'application/json' } }).then(async (response) => {
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || 'Produk tidak dapat ditemukan.');
                    return data;
                });
            };

            const scanReturn = (barcode) => {
                if (!outletId()) return showError('Pilih outlet terlebih dahulu.');
                fetchProduct(barcode, 'return').then((product) => {
                    const invoiceItem = state.sale?.items.find((item) => Number(item.product_id) === Number(product.product_id));
                    if (state.sale && !invoiceItem) throw new Error('Produk ini tidak ada pada nota yang dipilih.');
                    if (invoiceItem) {
                        const current = state.returns.find((item) => Number(item.item_id) === Number(invoiceItem.id));
                        const nextQty = Number(current?.qty || 0) + 1;
                        if (nextQty > Number(invoiceItem.available_qty)) throw new Error(`Sisa retur ${invoiceItem.name} hanya ${invoiceItem.available_qty}.`);
                        if (current) current.qty = nextQty;
                        else state.returns.push({ product_id: invoiceItem.product_id, item_id: invoiceItem.id, owner_stock_id: invoiceItem.owner_stock_id, name: invoiceItem.name, code: invoiceItem.code || product.code, price: invoiceItem.price, qty: 1, available_qty: invoiceItem.available_qty });
                    } else {
                        const current = state.returns.find((item) => Number(item.product_id) === Number(product.product_id));
                        if (current) current.qty += 1;
                        else state.returns.push({ ...product, qty: 1 });
                    }
                    returnInput.value = ''; clearError(); render(); returnInput.focus();
                }).catch((error) => showError(error.message));
            };

            const scanReplacement = (barcode) => {
                if (!state.returns.length) return showError('Scan barang yang dikembalikan terlebih dahulu.');
                fetchProduct(barcode, 'replacement').then((product) => {
                    const current = state.replacements.find((item) => Number(item.product_id) === Number(product.product_id));
                    if (current) current.qty += 1;
                    else state.replacements.push({ ...product, qty: 1 });
                    replacementInput.value = ''; clearError(); render(); replacementInput.focus();
                }).catch((error) => showError(error.message));
            };

            const linkReceipt = () => {
                const code = receipt.value.trim();
                if (!outletId() || !code) return;
                if (state.returns.length) return showError('Nota hanya dapat dihubungkan sebelum scan barang retur.');
                const params = new URLSearchParams({ outlet_id: outletId(), search: code });
                fetch(`${invoiceEndpoint}?${params.toString()}`, { headers: { Accept: 'application/json' } }).then((response) => response.json()).then((sales) => {
                    const sale = (sales || []).find((item) => String(item.code).toLowerCase() === code.toLowerCase());
                    if (!sale) throw new Error('Nota tidak ditemukan di outlet ini.');
                    state.sale = sale; saleId.value = sale.id;
                    receiptStatus.innerHTML = `<span class="text-success"><i class="fa fa-check"></i> Terhubung ke ${esc(sale.code)} · ${esc(sale.date)}</span>`;
                    receipt.value = ''; clearError(); returnInput.focus();
                }).catch((error) => { state.sale = null; saleId.value = ''; receiptStatus.textContent = error.message; });
            };

            receiptButton.addEventListener('click', linkReceipt);
            receipt.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); linkReceipt(); } });
            returnInput.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); const value = returnInput.value.trim(); if (value) scanReturn(value); } });
            replacementInput.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); const value = replacementInput.value.trim(); if (value) scanReplacement(value); } });
            outlet.addEventListener('change', () => { state.sale = null; saleId.value = ''; state.returns = []; state.replacements = []; receiptStatus.textContent = 'Tanpa nota tetap bisa diproses sebagai data retur baru.'; render(); });
            paymentMethod.addEventListener('change', render);
            returnRows.addEventListener('click', (event) => { const button = event.target.closest('.remove-return'); if (button) { state.returns.splice(Number(button.dataset.index), 1); if (!state.returns.length) state.replacements = []; render(); } });
            replacementRows.addEventListener('click', (event) => { const button = event.target.closest('.remove-replacement'); if (button) { state.replacements.splice(Number(button.dataset.index), 1); render(); } });
            returnRows.addEventListener('change', (event) => {
                if (!event.target.matches('.return-qty')) return;
                const item = state.returns[Number(event.target.dataset.index)];
                if (!item) return;
                const requested = Math.max(1, Number.parseInt(event.target.value, 10) || 1);
                item.qty = item.available_qty ? Math.min(requested, Number(item.available_qty)) : requested;
                render();
            });
            replacementRows.addEventListener('change', (event) => {
                if (!event.target.matches('.replacement-qty')) return;
                const item = state.replacements[Number(event.target.dataset.index)];
                if (!item) return;
                item.qty = Math.max(1, Number.parseInt(event.target.value, 10) || 1);
                render();
            });

            form.addEventListener('submit', (event) => {
                clearError();
                const { returned, replacement } = totals();
                if (!state.returns.length || !state.replacements.length || replacement < returned) {
                    event.preventDefault();
                    showError('Scan minimal satu barang retur dan satu barang pengganti. Nilai barang pengganti tidak boleh lebih kecil.');
                    return;
                }
                form.querySelectorAll('.generated-return-field').forEach((field) => field.remove());
                const hidden = (name, value) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value ?? ''; input.className = 'generated-return-field'; form.appendChild(input); };
                state.returns.forEach((item, index) => { hidden(`returns[${index}][product_id]`, item.product_id); hidden(`returns[${index}][item_id]`, item.item_id || ''); hidden(`returns[${index}][owner_stock_id]`, item.owner_stock_id || ''); hidden(`returns[${index}][qty]`, item.qty); });
                state.replacements.forEach((item, index) => { hidden(`replacements[${index}][product_id]`, item.product_id); hidden(`replacements[${index}][owner_stock_id]`, item.owner_stock_id || ''); hidden(`replacements[${index}][qty]`, item.qty); });
                differencePaid.value = String(Math.max(0, replacement - returned));
            });

            render();
            if (outletId()) returnInput.focus();
        })();
    </script>
@endsection
