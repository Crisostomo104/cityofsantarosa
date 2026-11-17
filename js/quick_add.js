// ======================
// QUICK ADD (MULTIPLE) MANUAL REQUESTS
// Load AFTER employee_dashboard.js. Uses the page's global `documentFees`.
// ======================
document.addEventListener('DOMContentLoaded', function () {
    const openBtn   = document.getElementById('quickAddRequestBtn');
    const addRowBtn = document.getElementById('quickAddRowBtn');
    const rowsEl    = document.getElementById('quickAddRows');
    const tpl       = document.getElementById('quickAddRowTemplate');
    const countEl   = document.getElementById('quickAddCount');
    const submitBtn = document.getElementById('quickAddSubmitBtn');
    const form      = document.getElementById('quickAddForm');
    const modalEl   = document.getElementById('quickAddModal');
    if (!openBtn || !form || !tpl || !modalEl || typeof bootstrap === 'undefined') return;

    const MAX_ROWS = 100;
    const todayStr = () => new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD, local time

    function feeFor(docType) {
        const s = (typeof documentFees !== 'undefined') ? documentFees[docType] : null;
        return s ? parseFloat(s.price ?? s.fee_amount ?? 0) : null;
    }

    function updateCount() {
        const n = rowsEl.querySelectorAll('.quick-add-row').length;
        countEl.textContent = n + ' request(s) ready';
        submitBtn.disabled = (n === 0);
        addRowBtn.disabled = (n >= MAX_ROWS);
    }

    function addRow(prefillDate, focus = true) {
        if (rowsEl.querySelectorAll('.quick-add-row').length >= MAX_ROWS) return;

        const frag = tpl.content.cloneNode(true);
        const row  = frag.querySelector('tr');
        const doc  = row.querySelector('.q-doc-type');
        const amt  = row.querySelector('.q-amount');
        const pwd  = row.querySelector('.q-pwd');
        const flag = row.querySelector('.q-pwd-flag');
        const date = row.querySelector('.q-date');
        const name = row.querySelector('input[type=text]');
        const purpose = row.querySelector('.q-purpose');

        // Undiscounted price of this row. Lets un-checking PWD/Senior restore
        // the right amount even for documents with no fixed fee.
        row.dataset.base = '';
        date.value = prefillDate || todayStr();

        // Same behavior as the single-entry form: names are uppercase
        name.addEventListener('input', () => { name.value = name.value.toUpperCase(); });
        purpose.addEventListener('input', () => { purpose.value = purpose.value.toUpperCase(); });

        doc.addEventListener('change', function () {
            const fee = feeFor(this.value);
            pwd.checked = false;
            flag.value = '0';
            if (fee === null) {
                row.dataset.base = '';
                amt.value = '';
            } else {
                row.dataset.base = fee.toFixed(2);
                amt.value = fee.toFixed(2);
            }
        });

        // A manual edit becomes the new base (only while the discount is off)
        amt.addEventListener('input', function () {
            if (!pwd.checked) row.dataset.base = this.value;
        });

        pwd.addEventListener('change', function () {
            if (this.checked && row.dataset.base === '') row.dataset.base = amt.value || '';
            flag.value = this.checked ? '1' : '0';
            const base = parseFloat(row.dataset.base);
            if (!isNaN(base)) amt.value = (this.checked ? base * 0.8 : base).toFixed(2);
        });

        row.querySelector('.q-remove-row').addEventListener('click', function () {
            row.remove();
            updateCount();
        });

        rowsEl.appendChild(frag);
        updateCount();
        if (focus) name.focus();
    }

    openBtn.addEventListener('click', function () {
        rowsEl.innerHTML = '';
        addRow(null, false);
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    // A new row inherits the previous row's date: after an outage most rows share a day.
    addRowBtn.addEventListener('click', function () {
        const last = rowsEl.querySelector('.quick-add-row:last-child .q-date');
        addRow(last ? last.value : null);
    });

    form.addEventListener('submit', function (e) {
        if (rowsEl.querySelectorAll('.quick-add-row').length === 0) {
            e.preventDefault();
            return;
        }
        if (!form.checkValidity()) {
            e.preventDefault();
            form.reportValidity();
            return;
        }
        submitBtn.disabled = true;          // block double submit
        submitBtn.textContent = 'Saving...';
    });

    modalEl.addEventListener('show.bs.modal', function () {
        submitBtn.textContent = 'Save All Requests';
        updateCount();
    });

    modalEl.addEventListener('shown.bs.modal', function () {
        const first = rowsEl.querySelector('input[type=text]');
        if (first) first.focus();
    });
});