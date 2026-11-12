{{--
    JS modal "Edit tutup kasir" di History Kasir.
    - superadmin & staff-outlet: selisih dihitung di browser dari data-expected pada tombol Edit.
    - role lain: angka seharusnya tidak ada di HTML; selisih ditanya ke server (hanya selisih yang kembali).
--}}
<script>
    (function () {
        const modal = document.getElementById('editSessionModal');
        const form = document.getElementById('editSessionForm');
        if (!modal || !form || !window.jQuery) return;

        const closing = document.getElementById('edit_closing_cash');
        const carry = document.getElementById('edit_carry_over_cash');
        const note = document.getElementById('edit_closing_note');
        const diffRow = document.getElementById('editDifferenceRow');
        const diffText = document.getElementById('editDifferenceText');
        const drawerText = document.getElementById('editDrawerTotal');
        const expectedText = document.getElementById('editExpectedText');
        const titleEl = document.getElementById('editSessionTitle');
        const limitInfo = document.getElementById('editSessionLimitInfo');

        const canSee = Boolean(window.CASH_CAN_SEE);
        const isKasir = window.CASH_ROLE === 'kasir';
        const limit = Number(window.CASH_KASIR_EDIT_LIMIT || 2);

        const toNumber = (value) => Number(String(value || '').replace(/[^\d]/g, '')) || 0;
        const rp = (value) => 'Rp ' + Math.abs(value).toLocaleString('id-ID');

        let state = { expected: 0, differenceUrl: '', edits: 0 };
        let timer = null;
        let seq = 0;

        const paint = (cls) => {
            ['success', 'danger'].forEach((c) => diffRow.classList.remove(c));
            if (cls) diffRow.classList.add(cls);
        };

        const showDiff = (diff) => {
            if (diff === 0) {
                diffText.textContent = 'Pas (Rp 0)';
                paint('success');
            } else {
                diffText.textContent = (diff > 0 ? '+ ' : '- ') + rp(diff);
                paint('danger');
            }
        };

        const fetchDiff = (physical) => {
            const mySeq = ++seq;
            diffText.textContent = 'menghitung...';
            fetch(state.differenceUrl + '?closing_cash=' + encodeURIComponent(physical), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then((response) => (response.ok ? response.json() : Promise.reject(response)))
                .then((json) => { if (mySeq === seq) showDiff(Number(json.difference || 0)); })
                .catch(() => { if (mySeq === seq) diffText.textContent = 'gagal menghitung selisih'; });
        };

        const render = () => {
            const hasClosing = String(closing.value || '').trim() !== '';
            const physical = toNumber(closing.value);
            drawerText.textContent = rp(physical + toNumber(carry.value));

            if (!hasClosing) {
                seq++;
                clearTimeout(timer);
                diffText.textContent = 'isi uang fisik';
                paint(null);
                return;
            }

            if (canSee) {
                showDiff(physical - state.expected);
                return;
            }

            clearTimeout(timer);
            timer = setTimeout(() => fetchDiff(physical), 250);
        };

        const setCurrency = (input, value) => {
            input.value = String(Math.round(Number(value) || 0));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };

        document.addEventListener('click', function (event) {
            const button = event.target.closest('.js-edit-session');
            if (!button) return;

            state = {
                expected: Number(button.dataset.expected || 0),
                differenceUrl: button.dataset.differenceUrl || '',
                edits: Number(button.dataset.edits || 0),
            };

            form.action = button.dataset.action;
            titleEl.textContent = button.dataset.title || '';
            setCurrency(closing, button.dataset.closing);
            setCurrency(carry, button.dataset.carry);
            note.value = button.dataset.note || '';
            if (expectedText) expectedText.textContent = rp(state.expected);

            if (isKasir) {
                const next = state.edits + 1;
                limitInfo.style.display = '';
                limitInfo.textContent = next >= limit
                    ? 'Ini edit terakhir (ke-' + next + ' dari maksimal ' + limit + '). Setelah ini tombol Edit tidak tersedia lagi.'
                    : 'Edit ke-' + next + ' dari maksimal ' + limit + '.';
            } else {
                limitInfo.style.display = 'none';
            }

            render();
            window.jQuery(modal).modal('show');
        });

        window.jQuery(modal).on('shown.bs.modal', function () {
            closing.focus();
            closing.select();
        });

        closing.addEventListener('input', render);
        carry.addEventListener('input', render);

        form.addEventListener('submit', function (event) {
            const confirmText = isKasir && state.edits + 1 >= limit
                ? 'Ini edit terakhir dan tidak bisa diubah lagi setelahnya. Simpan perubahan?'
                : 'Simpan perubahan tutup kasir ini?';
            if (!window.confirm(confirmText)) {
                event.preventDefault();
            }
        });
    })();
</script>