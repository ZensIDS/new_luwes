@extends('layouts.master')

@section('title', 'Proses Ganti Barang')

@section('container')
    <section class="content-header">
        <h1>Proses Ganti Barang <small>Penjualan</small></h1>
    </section>

    <section class="content">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul style="margin-bottom:0">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        <form method="POST" action="{{ route('refundPenjualan.store') }}" id="exchangeForm">
            @csrf
            <input type="hidden" name="code" value="{{ $code }}">

            <div class="box">
                <div class="box-header with-border"><h3 class="box-title">1. Pilih invoice dan barang yang diretur</h3></div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4">
                            <label>Outlet</label>
                            @if ($outlets->count() === 1)
                                <input type="hidden" name="outlet_id" id="outlet_id" value="{{ $outlets->first()->id }}">
                                <input type="text" class="form-control" value="{{ $outlets->first()->name }}" readonly>
                            @else
                                <select name="outlet_id" id="outlet_id" class="form-control" required>
                                    <option value="">Pilih outlet</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}" @selected((string) $outletId === (string) $outlet->id)>{{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="col-md-8">
                            <label>Cari invoice</label>
                            <input type="search" id="invoice_search" class="form-control" placeholder="Ketik kode invoice, misalnya INV000123">
                            <select id="penjualan_id_select" class="form-control" style="margin-top:6px" required>
                                <option value="">Pilih invoice</option>
                            </select>
                            <input type="hidden" name="penjualan_id" id="penjualan_id">
                        </div>
                    </div>

                    <div id="returnBox" class="table-responsive" style="margin-top:20px; display:none">
                        <p class="text-muted">Centang satu atau beberapa barang yang dikembalikan, lalu isi qty masing-masing.</p>
                        <table class="table table-bordered table-striped">
                            <thead><tr><th></th><th>Produk</th><th>Terjual</th><th>Sisa boleh retur</th><th>Harga</th><th>Qty retur</th></tr></thead>
                            <tbody id="returnRows"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border"><h3 class="box-title">2. Pilih barang pengganti</h3></div>
                <div class="box-body">
                    <div class="row" style="margin-bottom:10px">
                        <div class="col-md-8">
                            <label for="replacement_search">Cari barang pengganti</label>
                            <input type="search" id="replacement_search" class="form-control" placeholder="Cari nama, kode, atau SKU">
                        </div>
                        <div class="col-md-4" style="padding-top:25px"><span class="text-muted" id="replacementResultHint">Maksimal 10 hasil ditampilkan.</span></div>
                    </div>
                    <div id="stockHint" class="alert alert-warning">Pilih invoice terlebih dahulu.</div>
                    <div id="replacementBox" class="table-responsive" style="display:none">
                        <table class="table table-bordered table-striped">
                            <thead><tr><th></th><th>Produk</th><th>SKU / Batch</th><th>Stock toko</th><th>Harga</th><th>Qty</th></tr></thead>
                            <tbody id="replacementRows"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border"><h3 class="box-title">3. Konfirmasi</h3></div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-sm-4"><strong>Nilai barang diretur</strong><div id="returnedTotalText">Rp 0</div></div>
                        <div class="col-sm-4"><strong>Nilai barang pengganti</strong><div id="replacementTotalText">Rp 0</div></div>
                        <div class="col-sm-4"><strong>Selisih yang dibayar</strong><div id="differenceText" class="text-warning">Rp 0</div></div>
                    </div>
                    <hr>
                    <div class="row" id="paymentBox" style="display:none">
                        <div class="col-md-4">
                            <label>Selisih dibayar</label>
                            <input type="text" inputmode="numeric" name="difference_paid" id="difference_paid" class="form-control" value="0" data-currency-input data-currency-decimals="0">
                        </div>
                        <div class="col-md-4">
                            <label>Metode pembayaran selisih</label>
                            <select name="payment_method_name" id="payment_method_name" class="form-control">
                                <option value="Tunai">Tunai</option>
                                <option value="Transfer">Transfer</option>
                                <option value="QRIS">QRIS</option>
                                <option value="Debit">Debit</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="paymentReferenceBox" style="display:none">
                            <label>Nomor referensi <small>(non-tunai)</small></label>
                            <input type="text" name="payment_reference" id="payment_reference" class="form-control" maxlength="150">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:15px">
                        <label>Catatan (opsional)</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <div id="formError" class="alert alert-danger" style="display:none"></div>
                </div>
                <div class="box-footer">
                    <a href="{{ route('refundPenjualan.index') }}" class="btn btn-default">Batal</a>
                    <button type="submit" class="btn btn-primary" id="submitButton" disabled><i class="fa fa-exchange"></i> Simpan Ganti Barang</button>
                </div>
            </div>
        </form>
    </section>

    <script>
        (() => {
            const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
            const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));
            const outlet = document.getElementById('outlet_id');
            const invoiceSearch = document.getElementById('invoice_search');
            const invoiceSelect = document.getElementById('penjualan_id_select');
            const replacementSearch = document.getElementById('replacement_search');
            const invoiceId = document.getElementById('penjualan_id');
            const returnRows = document.getElementById('returnRows');
            const replacementRows = document.getElementById('replacementRows');
            const returnBox = document.getElementById('returnBox');
            const replacementBox = document.getElementById('replacementBox');
            const stockHint = document.getElementById('stockHint');
            const replacementResultHint = document.getElementById('replacementResultHint');
            const submitButton = document.getElementById('submitButton');
            const paymentBox = document.getElementById('paymentBox');
            const differencePaid = document.getElementById('difference_paid');
            const paymentMethod = document.getElementById('payment_method_name');
            const paymentReferenceBox = document.getElementById('paymentReferenceBox');
            const paymentReference = document.getElementById('payment_reference');
            let invoices = [];
            let stocks = [];
            let replacementSelections = new Map();
            let replacementSearchTimer;

            const selectedReturnInputs = () => [...returnRows.querySelectorAll('.return-select:checked')];
            const selectedReturnQtys = () => selectedReturnInputs().map((input) => ({
                itemId: String(input.value),
                qty: Number(input.closest('tr').querySelector('.return-qty').value || 0),
                ownerStockId: String(input.dataset.ownerStockId || ''),
            }));

            const captureReplacementSelections = () => {
                replacementRows.querySelectorAll('.replacement-select:checked').forEach((input) => {
                    replacementSelections.set(String(input.value), {
                        id: String(input.value),
                        price: Number(input.dataset.price || 0),
                        qty: Number(input.closest('tr').querySelector('.replacement-qty').value || 0),
                    });
                });
            };

            const refreshReturnedStockCandidates = () => {
                const quantities = {};
                selectedReturnQtys().forEach((item) => {
                    if (item.ownerStockId) quantities[item.ownerStockId] = (quantities[item.ownerStockId] || 0) + item.qty;
                });
                loadStocks(quantities);
            };

            const updateSummary = () => {
                const returned = selectedReturnQtys().reduce((sum, item) => {
                    const input = returnRows.querySelector(`.return-select[value="${item.itemId}"]`);
                    return sum + Number(input?.dataset.price || 0) * item.qty;
                }, 0);
                captureReplacementSelections();
                const replacements = [...replacementSelections.values()];
                const replacement = replacements.reduce((sum, item) => sum + item.price * item.qty, 0);
                const difference = Math.max(0, replacement - returned);
                document.getElementById('returnedTotalText').textContent = money(returned);
                document.getElementById('replacementTotalText').textContent = money(replacement);
                document.getElementById('differenceText').textContent = difference ? money(difference) : 'Tidak ada selisih';
                paymentBox.style.display = difference > 0 ? '' : 'none';
                differencePaid.dataset.rawValue = String(difference);
                differencePaid.value = window.formatIdNumber ? window.formatIdNumber(difference) : String(difference);
                const isCash = /tunai|cash/i.test(paymentMethod.value);
                paymentReferenceBox.style.display = difference > 0 && !isCash ? '' : 'none';
                paymentReference.required = difference > 0 && !isCash;
                if (isCash) paymentReference.value = '';
                submitButton.disabled = !selectedReturnInputs().length || !replacements.length || replacement < returned;
            };

            const renderReturnRows = (sale) => {
                returnRows.innerHTML = sale.items.map((item) => `
                    <tr class="${item.available_qty < 1 ? 'text-muted' : ''}">
                        <td><input type="checkbox" class="return-select" value="${item.id}" data-price="${item.price}" data-owner-stock-id="${item.owner_stock_id || ''}" ${item.available_qty < 1 ? 'disabled' : ''}></td>
                        <td>${esc(item.code || '')} - ${esc(item.name)}</td>
                        <td>${item.qty}</td>
                        <td>${item.available_qty}</td>
                        <td>${money(item.price)}</td>
                        <td><input type="number" class="form-control input-sm return-qty" min="1" max="${item.available_qty}" value="1" ${item.available_qty < 1 ? 'disabled' : ''}></td>
                    </tr>`).join('');
                returnRows.querySelectorAll('.return-select').forEach((input) => input.addEventListener('change', () => {
                    updateSummary();
                    refreshReturnedStockCandidates();
                }));
                returnRows.querySelectorAll('.return-qty').forEach((input) => input.addEventListener('input', () => {
                    updateSummary();
                    refreshReturnedStockCandidates();
                }));
                returnBox.style.display = '';
                updateSummary();
                refreshReturnedStockCandidates();
            };

            const renderReplacementRows = () => {
                captureReplacementSelections();
                replacementRows.innerHTML = stocks.map((stock) => {
                    const selected = replacementSelections.get(String(stock.id));
                    const qty = selected?.qty || 1;
                    return `
                        <tr>
                            <td><input type="checkbox" class="replacement-select" value="${stock.id}" data-price="${stock.price}" ${selected ? 'checked' : ''}></td>
                            <td>${esc(stock.code || '')} - ${esc(stock.name)}</td>
                            <td>${esc(stock.sku || '-')}</td>
                            <td>${stock.qty}${stock.available_qty > stock.qty ? ` <small class="text-success">(+${stock.available_qty - stock.qty} retur)</small>` : ''}</td>
                            <td>${money(stock.price)}</td>
                            <td><input type="number" class="form-control input-sm replacement-qty" min="1" max="${stock.available_qty}" value="${qty}"></td>
                        </tr>`;
                }).join('');
                replacementRows.querySelectorAll('.replacement-select').forEach((input) => input.addEventListener('change', () => {
                    const row = input.closest('tr');
                    if (input.checked) {
                        replacementSelections.set(String(input.value), { id: String(input.value), price: Number(input.dataset.price || 0), qty: Number(row.querySelector('.replacement-qty').value || 1) });
                    } else {
                        replacementSelections.delete(String(input.value));
                    }
                    updateSummary();
                }));
                replacementRows.querySelectorAll('.replacement-qty').forEach((input) => input.addEventListener('input', () => {
                    const checkbox = input.closest('tr').querySelector('.replacement-select');
                    const selected = replacementSelections.get(String(checkbox.value));
                    if (selected) selected.qty = Number(input.value || 0);
                    updateSummary();
                }));
                replacementBox.style.display = stocks.length ? '' : 'none';
                stockHint.style.display = stocks.length ? 'none' : '';
                replacementResultHint.textContent = stocks.length ? `${stocks.length} hasil ditampilkan. Centang satu atau beberapa barang.` : 'Tidak ada stock yang cocok.';
                updateSummary();
            };

            const loadStocks = (returnedStockQtys = {}) => {
                if (!outlet.value) return;
                const params = new URLSearchParams({ outlet_id: outlet.value, search: replacementSearch.value || '' });
                Object.entries(returnedStockQtys).forEach(([id, qty]) => params.append(`returned_stock_qtys[${id}]`, qty));
                fetch(`{{ route('refundPenjualan.stocks') }}?${params.toString()}`, { headers: { 'Accept': 'application/json' } })
                    .then((response) => {
                        if (!response.ok) throw new Error('Stock request failed');
                        return response.json();
                    })
                    .then((data) => { stocks = data || []; renderReplacementRows(); })
                    .catch(() => { stockHint.textContent = 'Stock toko gagal dimuat. Coba cari ulang.'; stockHint.style.display = ''; });
            };

            const loadInvoices = () => {
                invoiceSelect.innerHTML = '<option value="">Memuat invoice...</option>';
                invoiceId.value = '';
                returnBox.style.display = 'none';
                replacementSelections.clear();
                if (!outlet.value) return;
                const params = new URLSearchParams({ outlet_id: outlet.value, search: invoiceSearch.value || '' });
                fetch(`{{ route('refundPenjualan.invoices') }}?${params.toString()}`, { headers: { 'Accept': 'application/json' } })
                    .then((response) => response.json())
                    .then((data) => {
                        invoices = data || [];
                        invoiceSelect.innerHTML = '<option value="">Pilih invoice</option>' + invoices.map((sale) => `<option value="${sale.id}">${esc(sale.code)} — ${esc(sale.date)} — ${money(sale.total)}</option>`).join('');
                    })
                    .catch(() => { invoiceSelect.innerHTML = '<option value="">Invoice gagal dimuat</option>'; });
            };

            outlet.addEventListener('change', () => { loadInvoices(); loadStocks(); });
            invoiceSearch.addEventListener('input', loadInvoices);
            replacementSearch.addEventListener('input', () => {
                window.clearTimeout(replacementSearchTimer);
                replacementSearchTimer = window.setTimeout(() => loadStocks(Object.fromEntries(selectedReturnQtys().filter((item) => item.ownerStockId).map((item) => [item.ownerStockId, item.qty]))), 250);
            });
            paymentMethod.addEventListener('change', updateSummary);
            invoiceSelect.addEventListener('change', () => {
                const sale = invoices.find((value) => String(value.id) === String(invoiceSelect.value));
                invoiceId.value = sale?.id || '';
                replacementSelections.clear();
                if (sale) renderReturnRows(sale); else returnBox.style.display = 'none';
                updateSummary();
            });

            document.getElementById('exchangeForm').addEventListener('submit', (event) => {
                captureReplacementSelections();
                const returns = selectedReturnQtys().filter((item) => item.qty > 0);
                const replacements = [...replacementSelections.values()].filter((item) => item.qty > 0);
                if (!returns.length || !replacements.length) {
                    event.preventDefault();
                    const error = document.getElementById('formError');
                    error.textContent = 'Centang minimal satu barang yang diretur dan satu barang pengganti.';
                    error.style.display = '';
                    return;
                }
                returns.forEach((item, index) => {
                    document.getElementById('exchangeForm').insertAdjacentHTML('beforeend', `<input class="generated-exchange-field" type="hidden" name="returns[${index}][item_id]" value="${item.itemId}"><input class="generated-exchange-field" type="hidden" name="returns[${index}][qty]" value="${item.qty}">`);
                });
                replacements.forEach((item, index) => {
                    document.getElementById('exchangeForm').insertAdjacentHTML('beforeend', `<input class="generated-exchange-field" type="hidden" name="replacements[${index}][owner_stock_id]" value="${item.id}"><input class="generated-exchange-field" type="hidden" name="replacements[${index}][qty]" value="${item.qty}">`);
                });
            });

            if (outlet.value) { loadInvoices(); loadStocks(); }
        })();
    </script>
@endsection
