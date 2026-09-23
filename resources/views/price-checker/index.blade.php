<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cek Harga &amp; Promo — {{ config('app.name', 'LUWES') }}</title>
    <style>
        :root {
            --ink: #27253d;
            --muted: #77758b;
            --purple: #6251d8;
            --purple-soft: #f0eeff;
            --line: #e8e6f2;
            --surface: #ffffff;
            --background: #f7f6fb;
            --danger: #c0445b;
            --success: #25855b;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            background: var(--background);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .checker-shell {
            width: min(1160px, calc(100% - 40px));
            margin: 0 auto;
            padding: 28px 0 56px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 42px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: var(--ink);
            text-decoration: none;
            font-size: 21px;
            font-weight: 800;
            letter-spacing: -.03em;
        }

        .brand-mark {
            display: grid;
            width: 40px;
            height: 40px;
            place-items: center;
            color: #fff;
            background: var(--purple);
            border-radius: 13px;
            box-shadow: 0 8px 20px rgba(98, 81, 216, .24);
        }

        .brand-mark svg { width: 21px; height: 21px; }

        .store-label {
            display: flex;
            align-items: center;
            gap: 9px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
        }

        .store-dot {
            width: 8px;
            height: 8px;
            background: #f19a52;
            border-radius: 50%;
        }

        .hero {
            max-width: 720px;
            margin: 0 auto 30px;
            text-align: center;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin: 0 0 15px;
            color: var(--purple);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            width: 7px;
            height: 7px;
            content: "";
            background: #f19a52;
            border-radius: 50%;
        }

        h1 {
            margin: 0;
            font-size: clamp(30px, 5vw, 48px);
            line-height: 1.08;
            letter-spacing: -.055em;
        }

        .hero p {
            max-width: 520px;
            margin: 15px auto 0;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.6;
        }

        .scan-card {
            max-width: 860px;
            margin: 0 auto 22px;
            padding: 9px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 17px;
            box-shadow: 0 18px 48px rgba(49, 39, 109, .08);
        }

        .scan-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .scan-input-wrap {
            display: flex;
            align-items: center;
            flex: 1;
            gap: 11px;
            min-width: 0;
            padding: 0 14px;
            color: var(--muted);
        }

        .scan-input-wrap svg { flex: 0 0 auto; width: 23px; height: 23px; }

        .scan-input {
            width: 100%;
            min-width: 0;
            padding: 14px 0;
            color: var(--ink);
            background: transparent;
            border: 0;
            outline: 0;
            font: inherit;
            font-size: 17px;
        }

        .scan-input::placeholder { color: #aaa8b7; }

        .scan-help {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 5px 14px 3px;
            color: var(--muted);
            font-size: 12px;
        }

        .notice {
            display: none;
            max-width: 860px;
            margin: 0 auto 18px;
            padding: 13px 16px;
            color: var(--danger);
            background: #fff5f7;
            border: 1px solid #f3d2d9;
            border-radius: 11px;
            font-size: 14px;
        }

        .notice.is-visible { display: block; }

        .results-card {
            overflow: hidden;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 17px;
            box-shadow: 0 12px 35px rgba(49, 39, 109, .05);
        }

        .results-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 21px 24px;
            border-bottom: 1px solid var(--line);
        }

        .results-heading h2 { margin: 0; font-size: 17px; letter-spacing: -.02em; }

        .result-count {
            padding: 5px 10px;
            color: var(--purple);
            background: var(--purple-soft);
            border-radius: 999px;
            font-size: 12px;
            font-weight: 750;
        }

        .empty-state {
            padding: 70px 20px 76px;
            color: var(--muted);
            text-align: center;
        }

        .empty-icon {
            display: grid;
            width: 58px;
            height: 58px;
            margin: 0 auto 16px;
            place-items: center;
            color: var(--purple);
            background: var(--purple-soft);
            border-radius: 18px;
        }

        .empty-icon svg { width: 28px; height: 28px; }
        .empty-state strong { display: block; margin-bottom: 7px; color: var(--ink); font-size: 16px; }
        .empty-state p { margin: 0; font-size: 13px; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th, td { padding: 17px 24px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: middle; }
        th { color: var(--muted); background: #fbfaff; font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        td { font-size: 14px; }
        tbody tr:last-child td { border-bottom: 0; }
        .product-name { color: var(--ink); font-weight: 750; }
        .product-code { margin-top: 4px; color: var(--muted); font-size: 11px; }
        .price { color: var(--ink); font-size: 16px; font-weight: 800; white-space: nowrap; }
        .promo-list { display: grid; gap: 8px; min-width: 270px; }
        .promo-item { display: grid; gap: 3px; }
        .promo-name { color: var(--purple); font-size: 12px; font-weight: 800; }
        .promo-summary { color: #5d5a70; font-size: 12px; line-height: 1.45; }
        .no-promo { color: #aaa8b7; }
        .promo-price { color: var(--success); font-size: 16px; font-weight: 800; white-space: nowrap; }
        .promo-price small { display: block; margin-top: 3px; color: var(--muted); font-size: 11px; font-weight: 500; text-decoration: line-through; }
        .qty { color: var(--ink); font-weight: 750; text-align: center; }

        .footer-note {
            margin-top: 20px;
            color: #9a98a8;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 640px) {
            .checker-shell { width: min(100% - 24px, 1160px); padding-top: 18px; }
            .topbar { align-items: flex-start; flex-direction: column; gap: 16px; margin-bottom: 36px; }
            .store-label { width: 100%; justify-content: flex-end; }
            .scan-input-wrap { padding: 0 10px; }
            .scan-help { flex-direction: column; align-items: flex-start; }
            .results-heading { padding: 18px; }
            th, td { padding: 15px 18px; }
        }
    </style>
</head>

<body>
    <main class="checker-shell">
        <header class="topbar">
            <a class="brand" href="{{ route('price-checker.index', $selectedOutletId ? ['outlet_id' => $selectedOutletId] : []) }}" aria-label="LUWES Price Checker">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 5v10a4 4 0 0 0 4 4h8a4 4 0 0 0 4-4V5" />
                        <path d="M8 9h8M8 13h5" />
                    </svg>
                </span>
                <span>LUWES</span>
            </a>

            @if ($selectedOutlet)
                <div class="store-label" aria-label="Toko yang sedang digunakan">
                    <span class="store-dot" aria-hidden="true"></span>
                    <span>{{ $selectedOutlet->name ?: 'Toko ' . $selectedOutlet->id }}</span>
                </div>
            @endif
        </header>

        <section class="hero">
            <p class="eyebrow">Price checker</p>
            <h1>Cek harga dan promo<br>produk dengan mudah.</h1>
            <p>Scan barcode produk untuk melihat harga terbaru dan promo yang sedang berlaku di toko ini.</p>
        </section>

        <section class="scan-card" aria-label="Scan barcode produk">
            <form class="scan-form" id="scan-form">
                <div class="scan-input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 5v4M3 15v4M21 5v4M21 15v4M5 3h4M15 3h4M5 21h4M15 21h4" />
                        <path d="M7 8v8M10 8v8M14 8v8M17 8v8" />
                    </svg>
                    <input class="scan-input" id="barcode-input" type="text" inputmode="none" autocomplete="off" autofocus placeholder="Scan barcode produk di sini" aria-label="Barcode produk">
                </div>
            </form>
            <div class="scan-help">
                <span>Gunakan scanner barcode. Pencarian berjalan otomatis setelah scan.</span>
                <span>Hasil dikosongkan setelah 30 detik tanpa scan baru.</span>
            </div>
        </section>

        <div class="notice" id="notice" role="alert"></div>

        <section class="results-card" aria-live="polite">
            <div class="results-heading">
                <h2>Hasil pengecekan</h2>
                <span class="result-count" id="result-count">0 produk</span>
            </div>

            <div class="empty-state" id="empty-state">
                <div class="empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        <path d="M7 4v16M17 4v16" />
                    </svg>
                </div>
                <strong>Belum ada produk yang dicek</strong>
                <p>Silakan scan barcode produk untuk melihat informasinya.</p>
            </div>

            <div class="table-wrap" id="table-wrap" hidden>
                <table>
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Harga</th>
                            <th>Promo aktif</th>
                            <th>Harga promo</th>
                            <th>Qty</th>
                        </tr>
                    </thead>
                    <tbody id="results-body"></tbody>
                </table>
            </div>
        </section>

        <p class="footer-note">Harga dan promo dapat berubah sewaktu-waktu. Silakan konfirmasi kembali di kasir.</p>
    </main>

    <script>
        (() => {
            const lookupUrl = @json(route('price-checker.lookup'));
            const input = document.getElementById('barcode-input');
            const outletId = @json($selectedOutletId);
            const form = document.getElementById('scan-form');
            const notice = document.getElementById('notice');
            const emptyState = document.getElementById('empty-state');
            const tableWrap = document.getElementById('table-wrap');
            const resultsBody = document.getElementById('results-body');
            const resultCount = document.getElementById('result-count');
            const results = new Map();
            let lookupTimer = null;
            let inactivityTimer = null;
            let lookupInProgress = null;

            const rupiah = (value) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(value || 0));
            const escapeHtml = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const showNotice = (message) => {
                notice.textContent = message;
                notice.classList.add('is-visible');
            };

            const hideNotice = () => {
                notice.textContent = '';
                notice.classList.remove('is-visible');
            };

            const clearResults = () => {
                results.clear();
                input.value = '';
                hideNotice();
                render();
                input.focus();
            };

            const resetInactivityTimer = () => {
                clearTimeout(inactivityTimer);
                inactivityTimer = setTimeout(clearResults, 30000);
            };

            const render = () => {
                const items = Array.from(results.values());
                const totalQty = items.reduce((total, item) => total + item.quantity, 0);
                resultCount.textContent = `${items.length} ${items.length === 1 ? 'produk' : 'produk'}${totalQty > items.length ? ` · ${totalQty} item` : ''}`;
                emptyState.hidden = items.length > 0;
                tableWrap.hidden = items.length === 0;

                resultsBody.innerHTML = items.map((item) => {
                    const promoHtml = item.promotions.length
                        ? `<div class="promo-list">${item.promotions.map((promo) => `
                            <div class="promo-item">
                                <span class="promo-name">${escapeHtml(promo.name)}</span>
                                <span class="promo-summary">${escapeHtml(promo.summary)}</span>
                            </div>`).join('')}</div>`
                        : '<span class="no-promo">Tidak ada promo</span>';
                    const promoPrice = item.promotions.find((promo) => promo.price !== null && promo.price !== undefined);
                    const promoPriceHtml = promoPrice
                        ? `<span class="promo-price">${rupiah(promoPrice.price)}<small>dari ${rupiah(item.price)}</small></span>`
                        : '<span class="no-promo">—</span>';

                    return `<tr>
                        <td><div class="product-name">${escapeHtml(item.product.name)}</div><div class="product-code">${escapeHtml(item.product.barcode)}</div></td>
                        <td><span class="price">${rupiah(item.price)}</span></td>
                        <td>${promoHtml}</td>
                        <td>${promoPriceHtml}</td>
                        <td class="qty">${item.quantity}</td>
                    </tr>`;
                }).join('');
            };

            const lookup = async (barcode) => {
                if (lookupInProgress === barcode) return;

                lookupInProgress = barcode;
                const params = new URLSearchParams({ barcode });
                if (outletId) params.set('outlet_id', outletId);

                hideNotice();

                try {
                    const response = await fetch(`${lookupUrl}?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload.message || 'Produk tidak ditemukan.');
                    }

                    const key = String(payload.product.barcode);
                    const existing = results.get(key);
                    results.set(key, {
                        ...payload,
                        quantity: existing ? existing.quantity + 1 : 1,
                    });
                    render();
                    input.value = '';
                    resetInactivityTimer();
                } catch (error) {
                    showNotice(error.message || 'Terjadi kendala saat mengecek produk.');
                } finally {
                    lookupInProgress = null;
                    input.focus();
                }
            };

            input.addEventListener('input', () => {
                resetInactivityTimer();
                clearTimeout(lookupTimer);
                const barcode = input.value.trim();
                if (!barcode || lookupInProgress) return;

                // Most cashier scanners send an Enter key, but the short
                // debounce also supports scanners that only send characters.
                lookupTimer = setTimeout(() => lookup(input.value.trim()), 120);
            });

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                clearTimeout(lookupTimer);
                const barcode = input.value.trim();
                if (barcode) lookup(barcode);
            });

            render();
            input.focus();
        })();
    </script>
</body>

</html>
