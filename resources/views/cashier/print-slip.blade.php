<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $slip['docTitle'] }}</title>
    <script src="{{ asset('js/qz-tray.js') }}"></script>
    <script>
        // Pilihan kertas dipakai bersama dengan struk penjualan (tersimpan per perangkat).
        (function () {
            try {
                var url = new URL(window.location.href);
                var chosen = url.searchParams.get('paper');
                if (chosen === '58' || chosen === '68' || chosen === '80') {
                    window.localStorage.setItem('receipt-paper', chosen);
                    return;
                }
                var saved = window.localStorage.getItem('receipt-paper');
                if (saved === '58' || saved === '68' || saved === '80') {
                    url.searchParams.set('paper', saved);
                    window.location.replace(url.toString());
                }
            } catch (e) {}
        })();
    </script>
    @php
        $defaultPaper = '58';
        $paperProfiles = [
            '58' => ['shift' => 0, 'printable' => '48mm', 'base' => '7.5pt', 'small' => '6.75pt', 'title' => '10.5pt', 'tiny' => '6pt', 'logo' => '28mm'],
            '68' => ['shift' => 7, 'heavy' => true, 'printable' => '58mm', 'base' => '8.5pt', 'small' => '8pt', 'title' => '11pt', 'tiny' => '7.5pt', 'logo' => '34mm'],
            '80' => ['shift' => 0, 'printable' => '72mm', 'base' => '9pt', 'small' => '7.5pt', 'title' => '13.5pt', 'tiny' => '6.75pt', 'logo' => '40mm'],
        ];
        $requestedPaper = request('paper');
        $paper = is_string($requestedPaper) && isset($paperProfiles[$requestedPaper]) ? $requestedPaper : $defaultPaper;
        $profile = $paperProfiles[$paper];
        $paperWidth = $paper . 'mm';
        $printable = $profile['printable'];
        $shiftMm = (float) $profile['shift'];
        $heavy = (bool) ($profile['heavy'] ?? false);
        $printableMm = (float) $printable;
        $baseFont = $profile['base'];
        $smallFont = $profile['small'];
        $titleFont = $profile['title'];
        $tinyFont = $profile['tiny'];
        $rawDefaultCols = $paper === '58' ? 32 : ($paper === '68' ? 36 : 40);
        $paperLink = fn ($size) => request()->fullUrlWithQuery(['paper' => $size]);
    @endphp
    <style>
        @page { size: {{ $paperWidth }} auto; margin: 0; }
        * { box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        :root { --shift: {{ $shiftMm }}mm; --print-w: {{ $printable }}; --fs: 9pt; }
        body { width: var(--print-w); margin: 0 auto; padding: 2mm 0 8mm; font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif; font-size: {{ $baseFont }}; color: #000; line-height: 1.3; }
        body.heavy { font-family: Consolas, 'Courier New', 'DejaVu Sans Mono', monospace; font-weight: bold; font-size: var(--fs); letter-spacing: .05pt; line-height: 12pt; font-kerning: none; font-variant-ligatures: none; padding: 6pt 0 24pt; }
        body.heavy .small, body.heavy .tiny, body.heavy .meta, body.heavy .printed-at, body.heavy .footer-msg { font-size: var(--fs); line-height: 12pt; }
        body.heavy .store-name { font-size: calc(var(--fs) * 1.2); line-height: 14pt; }
        body.heavy .doc-title { font-size: var(--fs); line-height: 14pt; }
        body.heavy .rule-dash { border: 0; margin: 3pt 0; height: 12pt; line-height: 12pt; overflow: hidden; white-space: nowrap; }
        body.heavy .rule-dash::before { content: '------------------------------------------------------------------------------------------------'; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .store-name { font-size: {{ $titleFont }}; font-weight: bold; letter-spacing: .5px; word-break: break-word; }
        .doc-title { font-size: {{ $baseFont }}; font-weight: bold; text-align: center; margin: 1mm 0; }
        .small { font-size: {{ $smallFont }}; }
        .tiny { font-size: {{ $tinyFont }}; line-height: 1.25; word-break: break-word; }
        .header-info { margin-top: 1mm; }
        .meta { margin-top: 3mm; font-size: {{ $smallFont }}; }
        .rule-dash { border: none; border-top: 1px dashed #000; margin: 3mm 0; }
        .row { display: flex; justify-content: space-between; align-items: baseline; gap: 2mm; padding: .3px 0; }
        .row .val { text-align: right; white-space: nowrap; }
        .row .key { min-width: 0; word-break: break-word; }
        .sec-title { font-weight: bold; margin-bottom: .6mm; }
        .note { word-break: break-word; margin-top: .6mm; }
        .footer-msg { font-size: {{ $smallFont }}; word-break: break-word; }
        .printed-at { margin-top: 2mm; font-size: {{ $tinyFont }}; }

        .no-print { margin: 14px auto 0; width: 100%; max-width: 340px; font-family: Arial, sans-serif; font-weight: normal; font-size: 14px; }
        .no-print a, .no-print button { display: block; width: 100%; margin-top: 6px; padding: 10px; border: 1px solid #777; background: #fff; color: #000; font: inherit; text-align: center; text-decoration: none; cursor: pointer; border-radius: 4px; }
        .no-print .primary { background: #111; color: #fff; border-color: #111; font-weight: bold; }
        .no-print .papers { display: flex; gap: 6px; }
        .no-print .papers a.active { background: #e8f0fe; border-color: #1a56db; font-weight: bold; }
        .no-print .tips { margin-top: 10px; padding: 8px 10px; background: #fff8e1; border: 1px solid #f0d98a; border-radius: 4px; font-size: 12px; line-height: 1.5; text-align: left; }
        @media screen { body { padding-left: 0; padding-right: 0; } }
        @media print {
            body { margin: 0 0 0 var(--shift); }
            .no-print { display: none !important; }
        }
    </style>
</head>

<body class="{{ $heavy ? 'heavy' : '' }}">
    <div class="center">
        <div class="store-name">{{ $slip['store'] }}</div>
        @foreach ($slip['header'] as $i => $headerLine)
            <div class="tiny {{ $i === 0 ? 'header-info' : '' }}">{{ $headerLine }}</div>
        @endforeach
    </div>

    <div class="rule-dash"></div>
    <div class="doc-title">{{ $slip['title'] }}</div>

    <div class="meta">
        @foreach ($slip['meta'] as $m)
            <div class="row"><span class="key">{{ $m[0] }}</span><span class="val">{{ $m[1] }}</span></div>
        @endforeach
    </div>

    @foreach ($slip['sections'] as $section)
        <div class="rule-dash"></div>
        @if ($section['title'])<div class="sec-title">{{ $section['title'] }}</div>@endif
        @foreach ($section['rows'] as $r)
            <div class="row {{ !empty($r[2]) ? 'bold' : '' }}"><span class="key">{{ $r[0] }}</span><span class="val">{{ $r[1] }}</span></div>
        @endforeach
        @foreach ($section['lines'] as $line)
            <div class="note small">{{ $line }}</div>
        @endforeach
    @endforeach

    <div class="rule-dash"></div>

    @if ($slip['footerHtml'] !== '')
        <div class="center footer-msg">{!! $slip['footerHtml'] !!}</div>
    @else
        <div class="center footer-msg">@foreach ($slip['footerLines'] as $f)<div>{{ $f }}</div>@endforeach</div>
    @endif
    <div class="center printed-at">Dicetak {{ $slip['printedAt'] }}</div>

    <div class="no-print">
        <a href="{{ $back }}">Kembali</a>
        <button type="button" class="primary" onclick="window.printSlip(this); return false;">Print {{ $paper }}mm</button>
        <div class="papers">
            @foreach (['58', '68', '80'] as $size)
                <a href="{{ $paperLink($size) }}" class="{{ $paper === $size ? 'active' : '' }}">Kertas {{ $size }}mm</a>
            @endforeach
        </div>
        <div class="tips">
            Pengaturan printer QZ Tray (nama printer, lebar karakter, tebal, potong kertas) memakai pengaturan yang sama dengan halaman struk penjualan di perangkat ini.
        </div>
    </div>

    <script>
        // Cetak teks langsung via QZ Tray (ESC/POS) dengan pengaturan yang sama seperti struk penjualan.
        // Bila QZ Tray tidak aktif -> dialog print browser.
        (function () {
            var RAW = @json($slip);
            var KEY = 'receipt-raw-{{ $paper }}';
            var def = { on: true, printer: '', cols: {{ $rawDefaultCols }}, cut: false, indent: 2, bold: false, pass: 3 };
            var CERT_URL = @json(route('qz.certificate'));
            var SIGN_URL = @json(route('qz.sign'));
            var CSRF = @json(csrf_token());
            var ESC = '\x1B';

            function load() {
                try {
                    var s = JSON.parse(window.localStorage.getItem(KEY) || 'null');
                    if (s) return { on: s.on !== false, printer: s.printer || '', cols: +s.cols || def.cols, cut: !!s.cut, bold: typeof s.bold === 'boolean' ? s.bold : def.bold, pass: isFinite(s.pass) && s.pass !== null && s.pass !== '' ? Math.max(1, Math.min(5, +s.pass)) : def.pass, indent: isFinite(s.indent) && s.indent !== null && s.indent !== '' ? +s.indent : def.indent };
                } catch (e) {}
                return { on: def.on, printer: def.printer, cols: def.cols, cut: def.cut, bold: def.bold, pass: def.pass, indent: def.indent };
            }
            function clean(s) {
                return String(s == null ? '' : s).replace(/[\u2013\u2014\u00b7]/g, '-').replace(/[^\x20-\x7E]/g, '?');
            }
            function rep(ch, n) { return new Array(Math.max(0, n) + 1).join(ch); }
            function lr(l, r, c) {
                l = clean(l); r = clean(r);
                if (l.length + r.length + 1 > c) l = l.substr(0, Math.max(0, c - r.length - 1));
                return l + rep(' ', c - l.length - r.length) + r;
            }
            function wrap(s, c) {
                var words = clean(s).split(/\s+/), out = [], cur = '';
                words.forEach(function (w) {
                    while (w.length > c) { if (cur) { out.push(cur); cur = ''; } out.push(w.substr(0, c)); w = w.substr(c); }
                    if (!w) return;
                    if (!cur) cur = w;
                    else if ((cur + ' ' + w).length <= c) cur += ' ' + w;
                    else { out.push(cur); cur = w; }
                });
                if (cur) out.push(cur);
                return out;
            }
            function build(o) {
                var ind = Math.max(0, Math.min(20, o.indent | 0)), c = Math.max(16, o.cols - ind);
                var pad = rep(' ', ind), out = [], dash = rep('-', c);
                var passes = Math.max(1, Math.min(5, o.pass | 0 || 1));
                function line(t) {
                    if (passes <= 1 || !t.trim()) { out.push(pad + t + '\n'); return; }
                    out.push(ESC + '3\x00');
                    for (var i = 0; i < passes - 1; i++) out.push(pad + t + '\n');
                    out.push(ESC + '2');
                    out.push(pad + t + '\n');
                }
                function ctr(t) { t = clean(t); return rep(' ', Math.floor((c - t.length) / 2)) + t; }
                function ctrWrap(t) { wrap(t, c).forEach(function (w) { line(ctr(w)); }); }
                function strong() { out.push(ESC + 'E\x01' + ESC + 'G\x01'); }
                function normal() { out.push(ESC + 'E\x00' + ESC + 'G\x00'); if (o.bold) strong(); }
                out.push(ESC + '@');
                strong();
                ctrWrap(RAW.store);
                normal();
                RAW.header.forEach(ctrWrap);
                line(dash);
                strong(); ctrWrap(RAW.title); normal();
                line('');
                RAW.meta.forEach(function (m) { line(lr(m[0], m[1], c)); });
                RAW.sections.forEach(function (sec) {
                    line(dash);
                    if (sec.title) { strong(); line(clean(sec.title)); normal(); }
                    sec.rows.forEach(function (r) {
                        if (r[2]) strong();
                        line(lr(r[0], r[1], c));
                        if (r[2]) normal();
                    });
                    sec.lines.forEach(function (t) { wrap(t, c).forEach(line); });
                });
                line(dash);
                line(dash);
                RAW.footerLines.forEach(ctrWrap);
                line(ctr('Dicetak ' + RAW.printedAt));
                out.push(ESC + 'd\x05');
                if (o.cut) out.push(ESC + 'm');
                return out;
            }
            var secured = false;
            function setupSecurity() {
                if (secured) return;
                secured = true;
                qz.security.setCertificatePromise(function (resolve) {
                    fetch(CERT_URL, { credentials: 'same-origin', cache: 'no-store' })
                        .then(function (r) { return r.ok ? r.text() : ''; })
                        .then(function (t) { if (t) resolve(t); else resolve(); }, function () { resolve(); });
                });
                qz.security.setSignatureAlgorithm('SHA512');
                qz.security.setSignaturePromise(function (toSign) {
                    return function (resolve) {
                        fetch(SIGN_URL, {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': CSRF },
                            body: 'request=' + encodeURIComponent(toSign)
                        }).then(function (r) { return r.ok ? r.text() : ''; })
                          .then(function (t) { if (t) resolve(t); else resolve(); }, function () { resolve(); });
                    };
                });
            }
            function connect() {
                if (typeof qz === 'undefined') return Promise.reject(new Error('QZ Tray tidak termuat'));
                setupSecurity();
                return qz.websocket.isActive() ? Promise.resolve() : qz.websocket.connect({ retries: 0, delay: 0 });
            }
            function print() {
                var o = load();
                if (!o.on) return Promise.reject(new Error('dimatikan'));
                return connect()
                    .then(function () { return o.printer || qz.printers.getDefault(); })
                    .then(function (name) { return qz.print(qz.configs.create(name), build(o)); });
            }

            window.SlipRaw = { print: print };

            window.printSlip = function (btn) {
                var label = btn ? btn.textContent : '';
                print().then(function () {
                    if (btn) { btn.textContent = 'Terkirim ke printer ✓'; setTimeout(function () { btn.textContent = label; }, 2000); }
                }, function () { window.print(); });
            };

            var enterLock = false;
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' || e.repeat || e.ctrlKey || e.altKey || e.metaKey) return;
                var t = e.target, tag = t && t.tagName ? t.tagName.toLowerCase() : '';
                if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
                e.preventDefault();
                if (enterLock) return;
                enterLock = true;
                setTimeout(function () { enterLock = false; }, 1500);
                window.printSlip(document.querySelector('.no-print .primary'));
            });
        })();

        // Terapkan geser/lebar cetak yang sudah disimpan di halaman struk penjualan (untuk cetak browser).
        (function () {
            try {
                var saved = JSON.parse(window.localStorage.getItem('receipt-adjust-{{ $paper }}') || 'null');
                if (!saved || !isFinite(saved.shift) || !isFinite(saved.width)) return;
                var root = document.documentElement;
                root.style.setProperty('--shift', saved.shift + 'mm');
                root.style.setProperty('--print-w', saved.width + 'mm');
                if (saved.fs && isFinite(saved.fs)) root.style.setProperty('--fs', saved.fs + 'pt');
            } catch (e) {}
        })();
    </script>

    @if (request('auto'))
        <script>
            (function () {
                var back = @json($back);
                var printed = false;
                function doPrint() {
                    if (printed) return;
                    printed = true;
                    setTimeout(function () {
                        // Cetak teks langsung (QZ Tray); bila gagal / tidak aktif, pakai dialog print browser.
                        window.SlipRaw.print().then(function () { window.location.href = back; }, function () { window.print(); });
                    }, 150);
                }
                window.addEventListener('load', doPrint);
                window.addEventListener('afterprint', function () { window.location.href = back; });
            })();
        </script>
    @endif
</body>

</html>