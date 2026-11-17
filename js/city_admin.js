/**
 * City Administrator Portal client-side logic
 * Refactored from inline scripts in city_administrator.php
 */

const cityAdminConfig = window.cityAdminConfig || {};
const chartStatusData = cityAdminConfig.chartStatusData || [];
const chartDailyLabels = cityAdminConfig.chartDailyLabels || [];
const chartDailyData = cityAdminConfig.chartDailyData || [];
const chartBrgyLabels = cityAdminConfig.chartBrgyLabels || [];
const chartBrgyData = cityAdminConfig.chartBrgyData || [];
const docRevenueLabels = cityAdminConfig.docRevenueLabels || [];
const docRevenueValues = cityAdminConfig.docRevenueValues || [];
const initialPanel = cityAdminConfig.initialPanel || '';

// ── Overview sub-tabs switcher ──
var _ovBrgyInited = false;
function showOvTab(tab, btn) {
    const panelName = tab === 'top-documents' ? 'top-documents' : 'overview';
    showPanel(panelName, null);
    document.querySelectorAll('.ov-tab[data-group="overview"]').forEach(button => {
        button.classList.toggle('active', button.dataset.tab === tab);
    });

    if (panelName === 'overview') {
        document.querySelectorAll('.ov-tab-content').forEach(content => content.style.display = 'none');
        const contentEl = document.getElementById('ov-' + tab);
        if (contentEl) contentEl.style.display = '';
    }

    const overviewLink = Array.from(document.querySelectorAll('.sidebar a'))
        .find(link => (link.getAttribute('onclick') || '').includes("showOvTab('dashboard'"));
    document.querySelectorAll('.sidebar a').forEach(link => link.classList.remove('active'));
    if (overviewLink) overviewLink.classList.add('active');

    if (tab === 'barangay' && !_ovBrgyInited) {
        _ovBrgyInited = true;
        setTimeout(() => typeof window._initOvBrgy === 'function' && window._initOvBrgy(), 80);
    }
    if (tab === 'top-documents' && typeof window.initDocRevenueChart === 'function') {
        window.initDocRevenueChart();
    }
    localStorage.setItem('adminOvTab', tab);
    localStorage.setItem('adminPanel', panelName);
}
window.showOvTab = showOvTab;

// ── Mobile sidebar toggle ──
(function() {
    const btn = document.getElementById('hamburgerBtn');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!btn || !sidebar) return;

    function toggle() {
        sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('open');
    }

    btn.addEventListener('click', toggle);
    if (overlay) overlay.addEventListener('click', toggle);

    sidebar.querySelectorAll('a').forEach(a => {
        a.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('open');
                if (overlay) overlay.classList.remove('open');
            }
        });
    });
})();

// ── Panel and Group Tab switcher ──
function showPanel(name, el) {
    document.querySelectorAll('.panel').forEach(p => {
        p.classList.remove('active');
        p.style.display = 'none';
    });
    document.querySelectorAll('.sidebar a').forEach(a => a.classList.remove('active'));
    const panel = document.getElementById('panel-' + name);
    if (panel) {
        panel.classList.add('active');
        panel.style.display = 'block';
    }
    if (el) el.classList.add('active');
    const matchingTab = document.querySelector(`.ov-tab[data-tab="${name}"]`);
    if (matchingTab) matchingTab.classList.add('active');
    localStorage.setItem('adminPanel', name);
    sessionStorage.removeItem('adminPanel');
}

function showGroupTab(panelName, tabEl, groupName) {
    document.querySelectorAll('.panel').forEach(p => {
        p.classList.remove('active');
        p.style.display = 'none';
    });
    document.querySelectorAll('.sidebar a').forEach(a => a.classList.remove('active'));
    const sidebarLink = document.querySelector(`.sidebar a[data-group="${groupName}"]`);
    if (sidebarLink) sidebarLink.classList.add('active');
    const panel = document.getElementById('panel-' + panelName);
    if (panel) {
        panel.classList.add('active');
        panel.style.display = 'block';
    }
    document.querySelectorAll(`.ov-tab[data-group="${groupName}"]`).forEach(b => {
        b.classList.toggle('active', b.dataset.tab === panelName);
    });
    if (groupName === 'officials') {
        sessionStorage.setItem('adminPanel', panelName);
    } else {
        localStorage.setItem('adminPanel', panelName);
        sessionStorage.removeItem('adminPanel');
    }
    if (panelName === 'logs' && typeof window.loadSystemLogs === 'function') {
        window.loadSystemLogs(1);
    }
    if (panelName === 'top-documents' && typeof window.initDocRevenueChart === 'function') {
        window.initDocRevenueChart();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const getParam    = initialPanel;
    const sessionSaved = sessionStorage.getItem('adminPanel');
    const lsSaved      = localStorage.getItem('adminPanel') || 'overview';
    const accounts  = ['profile','approvals','employees','residents'];
    const system = ['requests','payments'];
    const sysSettings = ['announcements','doc-settings','chatbot','maintenance','data-management','force-logout','logs'];
    const officials = ['officials-Aplaya','officials-Balibago','officials-Caingin','officials-Dita','officials-Don-Jose','officials-Ibaba','officials-Kanluran','officials-Labas','officials-Macabling','officials-Malitlit','officials-Malusak','officials-Market-Area','officials-Pooc','officials-Pulong-Santa-Cruz','officials-Santo-Domingo','officials-Sinalhan','officials-Tagapo','officials-requests'];

    const validGetParam = (getParam && !officials.includes(getParam)) ? getParam : null;
    const saved = validGetParam || sessionSaved || lsSaved;

    if (saved === 'top-documents') showOvTab('top-documents', document.querySelector('.ov-tab[data-group="overview"][data-tab="top-documents"]'));
    else if (saved === 'overview') {
        const storedOvTab = localStorage.getItem('adminOvTab');
        const savedOvTab = ['dashboard', 'barangay'].includes(storedOvTab) ? storedOvTab : 'dashboard';
        showOvTab(savedOvTab, document.querySelector(`.ov-tab[data-group="overview"][data-tab="${savedOvTab}"]`));
    }
    else if (saved === 'dashboard') showOvTab('dashboard', document.querySelector('.ov-tab[data-group="overview"][data-tab="dashboard"]'));
    else if (accounts.includes(saved)) showGroupTab(saved, null, 'accounts');
    else if (system.includes(saved)) showGroupTab(saved, null, 'system');
    else if (sysSettings.includes(saved)) showGroupTab(saved, null, 'sys-settings');
    else if (officials.includes(saved)) showGroupTab(saved, null, 'officials');
    else { const link = document.querySelector(`.sidebar a[onclick*="'${saved}'"]`); showPanel(saved, link); }

    if (getParam && window.history.replaceState) {
        const cleanUrl = window.location.pathname;
        window.history.replaceState(null, '', cleanUrl);
    }

    setTimeout(() => typeof window._initOvAnalytics === 'function' && window._initOvAnalytics(), 150);
    setTimeout(() => typeof window.initDocRevenueChart === 'function' && window.initDocRevenueChart(), 150);
});

// ── Sidebar clock & user dropdown ──

function updateSidebarClock() {
    const el = document.getElementById('sidebarClock');
    if (!el) return;
    const now = new Date();
    const date = now.toLocaleDateString(undefined, { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
    const time = now.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    el.textContent = `${date} ${time}`;
}
setInterval(updateSidebarClock, 1000);
document.addEventListener('DOMContentLoaded', updateSidebarClock);

function positionUserDropdown(dropdown, trigger) {
    if (!dropdown || !trigger) return;
    dropdown.classList.add('show');

    const rect = trigger.getBoundingClientRect();
    const dropdownWidth = dropdown.offsetWidth || 260;
    const dropdownHeight = dropdown.offsetHeight || 120;

    if (window.innerWidth <= 768) {
        const container = dropdown.parentElement || trigger.closest('.user-dropdown-container') || document.querySelector('.user-dropdown-container');
        if (container) {
            dropdown.style.position = 'absolute';
            const cRect = container.getBoundingClientRect();
            let left = 8;
            let top = rect.bottom - cRect.top + 8;
            const availableWidth = Math.max(0, container.clientWidth - 16);
            dropdown.style.left = `${left}px`;
            dropdown.style.top = `${top}px`;
            dropdown.style.width = `${Math.min(dropdownWidth, availableWidth)}px`;
            const arrowLeft = (rect.left + rect.width / 2) - (cRect.left + left) - 6;
            dropdown.style.setProperty('--arrow-left', `${Math.max(12, Math.min(availableWidth - 24, arrowLeft))}px`);
            return;
        }
    }

    dropdown.style.position = 'fixed';
    let top = rect.bottom + 8;
    if (top + dropdownHeight + 16 > window.innerHeight) {
        top = rect.top - dropdownHeight - 8;
        if (top < 8) top = 8;
    }
    let left = rect.left + (rect.width / 2) - (dropdownWidth / 2);
    if (left < 8) left = 8;
    if (left + dropdownWidth > window.innerWidth - 8) left = window.innerWidth - dropdownWidth - 8;
    dropdown.style.left = `${left}px`;
    dropdown.style.top = `${top}px`;
    const arrowLeft = (rect.left + rect.width / 2) - left - 6;
    dropdown.style.setProperty('--arrow-left', `${Math.max(12, Math.min(dropdownWidth - 24, arrowLeft))}px`);
}

function toggleUserDropdown(trigger) {
    const dropdown = document.getElementById('userDropdown');
    if (!dropdown) return;

    if (dropdown.classList.contains('show')) {
        dropdown.classList.remove('show');
        dropdown.style.left = '';
        dropdown.style.top = '';
        return;
    }

    dropdown.classList.add('show');
    requestAnimationFrame(() => positionUserDropdown(dropdown, trigger));
}
window.toggleUserDropdown = toggleUserDropdown;
window.positionUserDropdown = positionUserDropdown;

// ── CSRF-SAFE POST HELPER ──
function csrfPost(url, params = {}) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    form.style.display = 'none';

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = 'csrf_token';
        csrf.value = csrfMeta.content;
        form.appendChild(csrf);
    }

    for (const [key, value] of Object.entries(params)) {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = key;
        inp.value = value;
        form.appendChild(inp);
    }

    document.body.appendChild(form);
    form.submit();
}
window.csrfPost = csrfPost;

function swalGo(title, text, icon, confirmText, confirmColor, url, params = {}) {
    Swal.fire({
        title: title,
        text: text,
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#64748b',
        confirmButtonText: confirmText,
        cancelButtonText: 'Cancel',
        borderRadius: '12px'
    }).then(result => {
        if (result.isConfirmed) {
            csrfPost(url, params);
        }
    });
}
window.swalGo = swalGo;

function rejectEmployee(id, name) {
    Swal.fire({
        title: 'Reject Account',
        text: 'Reason for rejecting ' + name + ':',
        input: 'text',
        inputPlaceholder: 'e.g. Incomplete information',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Reject'
    }).then(res => {
        if (res.isConfirmed) {
            csrfPost('../action/reject_employee', { id: id, reason: res.value || '' });
        }
    });
}
window.rejectEmployee = rejectEmployee;

function reviewPayout(id, status) {
    const needsNote = status === 'Rejected';
    Swal.fire({
        title: status + ' Payout',
        text: needsNote ? 'Enter reason for rejection:' : 'Add an optional note:',
        input: 'text',
        inputPlaceholder: needsNote ? 'Reason is required' : 'Optional note',
        showCancelButton: true,
        confirmButtonText: status,
        inputValidator: (value) => {
            if (needsNote && !value) return 'Reason is required.';
        }
    }).then(res => {
        if (!res.isConfirmed) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../action/review_payout';

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'payout_id';
        idInput.value = id;

        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'status';
        statusInput.value = status;

        const noteInput = document.createElement('input');
        noteInput.type = 'hidden';
        noteInput.name = 'admin_note';
        noteInput.value = res.value || '';

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = 'csrf_token';
            csrf.value = csrfMeta.content;
            form.appendChild(csrf);
        }

        form.appendChild(idInput);
        form.appendChild(statusInput);
        form.appendChild(noteInput);
        document.body.appendChild(form);
        form.submit();
    });
}
window.reviewPayout = reviewPayout;

function initTablePagination(tableId, pageSize = 15, infoId, pagerId) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    if (!rows.length) return;

    const state = {
        currentPage: 1,
        search: '',
        columnFilters: {}
    };

    let sortColumnIndex = -1;
    let sortAscending = true;

    const info = document.getElementById(infoId);
    const pager = document.getElementById(pagerId);

    function getVisibleRows() {
        const query = state.search;
        const statusColumnIndex = Array.from(table.querySelectorAll('thead th')).findIndex(th =>
            th.textContent.trim().toLowerCase().startsWith('status')
        );
        const paymentColumnIndex = table.id === 'reqTable'
            ? Array.from(table.querySelectorAll('thead th')).findIndex(th =>
                th.textContent.trim().toLowerCase().startsWith('payment')
            )
            : -1;
        return rows.filter(row => {
            // 1. Global search
            if (query) {
                const text = Array.from(row.querySelectorAll('td'))
                    .filter((_, index) => index !== statusColumnIndex && index !== paymentColumnIndex)
                    .map(td => td.innerText)
                    .join(' ')
                    .toLowerCase();
                if (!text.includes(query)) return false;
            }

            // 2. Cyclic column filters
            for (const [colIdxStr, filterVal] of Object.entries(state.columnFilters)) {
                if (!filterVal) continue;
                const colIdx = Number(colIdxStr);
                const cell = row.cells[colIdx];
                if (!cell) return false;
                const cellText = cell.innerText.trim().toLowerCase();
                
                const th = table.querySelectorAll('thead th')[colIdx];
                let colName = '';
                if (th) {
                    colName = th.getAttribute('data-col-name');
                    if (!colName) {
                        colName = Array.from(th.childNodes)
                            .filter(node => node.nodeType === Node.TEXT_NODE)
                            .map(node => node.textContent.trim())
                            .join('')
                            .toLowerCase();
                        th.setAttribute('data-col-name', colName);
                    }
                }
                
                const isPaymentCol = colName === 'payment';
                
                if (isPaymentCol) {
                    if (filterVal === 'paid') {
                        const isPaid = cell.querySelector('.bg-success') !== null;
                        if (!isPaid) return false;
                    } else if (filterVal === 'pending') {
                        const isPending = cell.querySelector('.bg-warning') !== null;
                        if (!isPending) return false;
                    } else if (filterVal === 'failed') {
                        const isFailed = cell.querySelector('.bg-danger') !== null;
                        if (!isFailed) return false;
                    } else {
                        if (!cellText.includes(filterVal)) return false;
                    }
                } else {
                    if (!cellText.includes(filterVal)) return false;
                }
            }
            return true;
        });
    }

    function getCycleForColumn(colIdx, colText) {
        if (colText === 'status') {
            if (table.id === 'reqTable') {
                return [null, 'pending', 'approved', 'completed', 'rejected', 'expired'];
            } else if (table.id === 'empTable' || table.id === 'resTable') {
                return [null, 'active', 'deactivated'];
            } else {
                return [null, 'pending', 'approved', 'completed', 'rejected', 'active', 'deactivated'];
            }
        } else if (colText === 'payment') {
            return [null, 'paid', 'pending', 'failed', 'online', 'walk-in'];
        }
        return [null];
    }
    
    function cycleColumnFilter(colIdx, colText) {
        sortColumnIndex = -1;
        sortAscending = true;

        for (const idx in state.columnFilters) {
            if (Number(idx) !== colIdx) {
                state.columnFilters[idx] = null;
            }
        }

        const cycle = getCycleForColumn(colIdx, colText);
        const currentVal = state.columnFilters[colIdx] || null;
        const currentIdx = cycle.indexOf(currentVal);
        const nextIdx = (currentIdx + 1) % cycle.length;
        state.columnFilters[colIdx] = cycle[nextIdx];

        updateSortIndicators();
        state.currentPage = 1;
        renderPage();
    }

    function sortTable(colIdx) {
        state.columnFilters = {};

        if (sortColumnIndex === colIdx) {
            sortAscending = !sortAscending;
        } else {
            sortColumnIndex = colIdx;
            sortAscending = true;
        }

        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        rows.sort((a, b) => {
            const cellA = a.cells[colIdx];
            const cellB = b.cells[colIdx];
            if (!cellA || !cellB) return 0;

            let valA = cellA.innerText.trim();
            let valB = cellB.innerText.trim();

            const dateA = Date.parse(valA);
            const dateB = Date.parse(valB);
            const isDate = !isNaN(dateA) && !isNaN(dateB) && isNaN(Number(valA)) && isNaN(Number(valB));
            if (isDate) {
                return sortAscending ? dateA - dateB : dateB - dateA;
            }

            const numA = parseFloat(valA.replace(/[^0-9.-]/g, ''));
            const numB = parseFloat(valB.replace(/[^0-9.-]/g, ''));
            const isNumeric = !isNaN(numA) && !isNaN(numB) && /^\d+(\.\d+)?$/.test(valA.replace(/[^0-9.]/g, ''));
            if (isNumeric) {
                return sortAscending ? numA - numB : numB - numA;
            }

            valA = valA.toLowerCase();
            valB = valB.toLowerCase();

            if (valA < valB) return sortAscending ? -1 : 1;
            if (valA > valB) return sortAscending ? 1 : -1;
            return 0;
        });

        rows.forEach(row => tbody.appendChild(row));
        updateSortIndicators();
        state.currentPage = 1;
        renderPage();
    }

    function updateSortIndicators() {
        const headerThs = table.querySelectorAll('thead tr:first-child th');
        headerThs.forEach((th, idx) => {
            let colName = th.getAttribute('data-col-name');
            if (!colName) {
                colName = Array.from(th.childNodes)
                    .filter(node => node.nodeType === Node.TEXT_NODE)
                    .map(node => node.textContent.trim())
                    .join('')
                    .toLowerCase();
                th.setAttribute('data-col-name', colName);
            }

            if (th.getAttribute('colspan') || colName === 'action' || colName === 'actions') {
                return;
            }

            const isCyclic = colName === 'status' || colName === 'payment';

            let indicator = th.querySelector('.sort-indicator');
            if (!indicator) {
                indicator = document.createElement('span');
                indicator.className = 'sort-indicator ms-1 text-muted';
                indicator.style.fontSize = '0.75rem';
                th.appendChild(indicator);

                th.style.cursor = 'pointer';
                th.title = isCyclic ? 'Click to filter' : 'Click to sort';

                if (isCyclic) {
                    th.addEventListener('click', () => cycleColumnFilter(idx, colName));
                } else {
                    th.addEventListener('click', () => sortTable(idx));
                }
            }

            if (isCyclic) {
                const activeVal = state.columnFilters[idx];
                if (activeVal) {
                    indicator.className = 'sort-indicator filter-chip';
                    indicator.textContent = activeVal.charAt(0).toUpperCase() + activeVal.slice(1);
                    th.style.color = '#8E1B1B';
                } else {
                    indicator.className = 'sort-indicator ms-1 text-muted';
                    indicator.textContent = '⇅';
                    th.style.color = '';
                }
            } else {
                const isSorted = idx === sortColumnIndex;
                indicator.className = 'sort-indicator ms-1' + (isSorted ? '' : ' text-muted');
                indicator.textContent = isSorted ? (sortAscending ? '▲' : '▼') : '⇅';
                th.style.color = isSorted ? '#8E1B1B' : '';
            }
        });
    }

    function renderPage() {
        const visibleRows = getVisibleRows();
        const total = visibleRows.length;
        const pages = Math.max(1, Math.ceil(total / pageSize));
        state.currentPage = Math.min(state.currentPage, pages);
        const start = (state.currentPage - 1) * pageSize;

        rows.forEach(row => {
            row.style.display = 'none';
        });

        visibleRows.slice(start, start + pageSize).forEach(row => {
            row.style.display = '';
        });

        if (info) {
            info.textContent = total
                ? `Showing ${Math.min(start + 1, total)}–${Math.min(start + pageSize, total)} of ${total}`
                : 'No matching records';
        }

        if (pager) {
            pager.innerHTML = '';
            if (pages <= 1) return;

            const prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'btn btn-sm btn-outline-secondary';
            prev.textContent = '← Prev';
            prev.disabled = state.currentPage === 1;
            prev.onclick = () => { state.currentPage--; renderPage(); };
            pager.appendChild(prev);

            const pageInfo = document.createElement('span');
            pageInfo.className = 'text-muted small px-2';
            pageInfo.textContent = `Page ${state.currentPage} of ${pages}`;
            pager.appendChild(pageInfo);

            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'btn btn-sm btn-outline-secondary';
            next.textContent = 'Next →';
            next.disabled = state.currentPage === pages;
            next.onclick = () => { state.currentPage++; renderPage(); };
            pager.appendChild(next);
        }
    }

    window[tableId + '_setSearch'] = (query) => {
        state.search = (query || '').toLowerCase().trim();
        state.currentPage = 1;
        renderPage();
    };

    updateSortIndicators();
    renderPage();
    return { renderPage };
}
window.initTablePagination = initTablePagination;

function filterTable(tableId, query) {
    if (window[tableId + '_setSearch']) {
        window[tableId + '_setSearch'](query);
    }
}
window.filterTable = filterTable;

function handleRequestSearch(input, filterName) {
    const filter = input.form.elements.namedItem(filterName);
    if (!filter || filter.value !== 'all_requests') {
        clearTimeout(input.requestSearchTimer);
        filterTable('reqTable', input.value);
        return;
    }

    clearTimeout(input.requestSearchTimer);
    if (input.value.trim() === '') {
        input.form.requestSubmit();
    }
}

function submitRequestSearchOnEnter(event, input, filterName) {
    if (event.key !== 'Enter') return;

    event.preventDefault();
    clearTimeout(input.requestSearchTimer);
    const filter = input.form.elements.namedItem(filterName);
    if (filter && filter.value === 'all_requests') {
        input.form.requestSubmit();
    }
}

async function loadServerRequestPage(url, updateHistory = true) {
    const requestTable = document.getElementById('reqTable');
    const requestInfo = document.getElementById('reqPageInfo');
    const requestPagination = document.getElementById('reqPagination');
    if (!requestTable || !requestInfo || !requestPagination) return;

    requestPagination.setAttribute('aria-busy', 'true');
    try {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok) throw new Error(`Request page load failed: ${response.status}`);

        const html = await response.text();
        const parsedPage = new DOMParser().parseFromString(html, 'text/html');
        const nextTable = parsedPage.querySelector('#reqTable[data-server-paginated="true"]');
        const nextInfo = parsedPage.querySelector('#reqPageInfo');
        const nextPagination = parsedPage.querySelector('#reqPagination');
        if (!nextTable || !nextInfo || !nextPagination) {
            throw new Error('The server response did not contain the request table page.');
        }

        requestTable.querySelector('tbody').innerHTML = nextTable.querySelector('tbody').innerHTML;
        requestInfo.textContent = nextInfo.textContent;
        requestPagination.innerHTML = nextPagination.innerHTML;
        if (updateHistory) window.history.pushState(null, '', url);
    } catch (error) {
        console.error('Unable to load request page without reloading:', error);
        window.location.assign(url);
    } finally {
        requestPagination.removeAttribute('aria-busy');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', (event) => {
        const link = event.target.closest('#reqPagination a[href]');
        const requestTable = document.getElementById('reqTable');
        if (!link || !requestTable || requestTable.dataset.serverPaginated !== 'true') return;

        event.preventDefault();
        loadServerRequestPage(link.href);
    });

    window.addEventListener('popstate', () => {
        const requestTable = document.getElementById('reqTable');
        if (requestTable && requestTable.dataset.serverPaginated === 'true') {
            loadServerRequestPage(window.location.href, false);
        }
    });

    requestAnimationFrame(() => {
        initTablePagination('empTable', 15, 'empPageInfo', 'empPagination');
        initTablePagination('resTable', 15, 'resPageInfo', 'resPagination');
        const requestTable = document.getElementById('reqTable');
        if (!requestTable || requestTable.dataset.serverPaginated !== 'true') {
            initTablePagination('reqTable', 15, 'reqPageInfo', 'reqPagination');
            const requestSearch = document.getElementById('reqSearch');
            if (requestSearch && requestSearch.value) {
                filterTable('reqTable', requestSearch.value);
            }
        }
        initTablePagination('brgyTotalsTable', 5, 'brgyTotalsInfo', 'brgyTotalsPager');
        initTablePagination('unpaidCompletedTable', 15, 'unpaidCompletedInfo', 'unpaidCompletedPager');
        initTablePagination('payoutRequestsTable', 5, 'payoutRequestsInfo', 'payoutRequestsPager');
    });

    document.addEventListener('click', (e) => {
        const dropdown = document.getElementById('userDropdown');
        const container = document.querySelector('.user-dropdown-container');
        if (dropdown && container && !container.contains(e.target)) {
            dropdown.classList.remove('show');
            dropdown.style.left = '';
            dropdown.style.top = '';
            dropdown.style.position = '';
            dropdown.style.width = '';
        }
    });

    window.addEventListener('resize', () => {
        const dropdown = document.getElementById('userDropdown');
        const trigger = document.querySelector('.sidebar-user');
        if (dropdown && dropdown.classList.contains('show') && trigger) {
            positionUserDropdown(dropdown, trigger);
        }
    });
});

// ── AWS S3 Cloud Backup & Heartbeat ──
document.addEventListener('DOMContentLoaded', () => {
    const s3Btn    = document.getElementById('cloudBackupS3Btn');
    const s3Result = document.getElementById('cloudBackupS3Result');
    if (s3Btn) {
        s3Btn.addEventListener('click', () => {
            s3Btn.disabled = true;
            s3Btn.innerHTML = 'Backing up…';
            s3Result.innerHTML = '<span class="text-muted">Uploading to AWS S3, please wait…</span>';

            fetch('../action/cloud_backup_s3', { method: 'POST' })
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(data => {
                    s3Btn.disabled = false;
                    s3Btn.innerHTML = 'Backup to AWS S3';

                    if (data.success) {
                        let html = '<span class="text-success fw-semibold">' + data.message + '</span>';
                        if (data.link) {
                            html += '<br><a href="' + data.link + '" target="_blank" class="btn btn-sm btn-outline-success mt-2">Download from S3 (valid 1 hour)</a>';
                        }
                        s3Result.innerHTML = html;
                    } else {
                        s3Result.innerHTML = '<span class="text-danger">' + (data.message || 'Backup failed') + '</span>';
                    }
                })
                .catch(err => {
                    s3Btn.disabled = false;
                    s3Btn.innerHTML = 'Backup to AWS S3';
                    s3Result.innerHTML = '<span class="text-danger">Request failed: ' + err.message + '</span>';
                });
        });
    }

    setInterval(() => {
        fetch('../action/heartbeat')
            .then(r => r.json())
            .then(data => {
                if (data.status === 'unauthorized') {
                    window.location.href = '../auth/login?expired=1';
                }
            })
            .catch(() => {});
    }, 15000);
});

// ── Overview Analytics Charts ──
(function() {
    var chartsInited = false;
    function initAnalytics() {
        if (chartsInited) return;
        var s = document.getElementById('chartStatus');
        var d = document.getElementById('chartDaily');
        if (!s || !d) return;
        chartsInited = true;
        new Chart(s, {
            type: 'doughnut',
            data: {
                labels: ['Pending','Approved','To be Claimed','Rejected','Completed'],
                datasets: [{ data: chartStatusData, backgroundColor: ['#f59e0b','#3b82f6','#6366f1','#ef4444','#22c55e'], borderWidth: 2 }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 12 } } } } }
        });
        new Chart(d, {
            type: 'line',
            data: {
                labels: chartDailyLabels,
                datasets: [{ label: 'Requests', data: chartDailyData, fill: true,
                    backgroundColor: 'rgba(139,92,246,.1)', borderColor: '#8b5cf6', tension: 0.4, pointRadius: 3 }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    }
    window._initOvAnalytics = initAnalytics;
})();

// ── Barangay Chart and filters/pagination ──
(function() {
    var brgyInited = false;
    function initBrgy() {
        if (brgyInited) return;
        var b = document.getElementById('chartBrgy');
        if (!b) return;
        brgyInited = true;
        new Chart(b, {
            type: 'bar',
            data: {
                labels: chartBrgyLabels,
                datasets: [{ label: 'Requests', data: chartBrgyData, backgroundColor: '#3b82f6', borderRadius: 5 }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    }

    const PAGE_SIZE = 6;
    let currentPage = 1;
    let sortColumnIndex = -1;
    let sortAscending = true;
    const dataKeys = ['brgy', 'res', 'emp', 'req', 'pend', 'appr', 'toClaim', 'comp', 'rej'];

    function getRows() {
        return Array.from(document.querySelectorAll('#brgyTbody tr'));
    }

    function updateSortIndicators() {
        const headers = document.querySelectorAll('#brgyTable thead tr:first-child th');
        headers.forEach((th, idx) => {
            let indicator = th.querySelector('.sort-indicator');
            if (!indicator) {
                indicator = document.createElement('span');
                indicator.className = 'sort-indicator ms-1 text-muted';
                indicator.style.fontSize = '0.75rem';
                th.appendChild(indicator);
            }
            if (idx === sortColumnIndex) {
                indicator.innerHTML = sortAscending ? ' ▲' : ' ▼';
                indicator.classList.remove('text-muted');
            } else {
                indicator.innerHTML = ' ⇅';
                indicator.classList.add('text-muted');
            }
        });
    }

    function sortTable(colIdx) {
        const key = dataKeys[colIdx];
        if (!key) return;

        if (sortColumnIndex === colIdx) {
            sortAscending = !sortAscending;
        } else {
            sortColumnIndex = colIdx;
            sortAscending = true;
        }

        const tbody = document.getElementById('brgyTbody');
        if (!tbody) return;

        const rows = getRows();
        rows.sort((a, b) => {
            let valA = a.dataset[key];
            let valB = b.dataset[key];

            if (key !== 'brgy') {
                valA = parseInt(valA) || 0;
                valB = parseInt(valB) || 0;
            } else {
                valA = valA.toLowerCase();
                valB = valB.toLowerCase();
            }

            if (valA < valB) return sortAscending ? -1 : 1;
            if (valA > valB) return sortAscending ? 1 : -1;
            return 0;
        });

        rows.forEach(row => tbody.appendChild(row));
        updateSortIndicators();
        currentPage = 1;
        renderPage();
    }

    function renderPage() {
        const visible = getRows();
        const total   = visible.length;
        const pages   = Math.max(1, Math.ceil(total / PAGE_SIZE));
        currentPage   = Math.min(currentPage, pages);
        const start   = (currentPage - 1) * PAGE_SIZE;

        getRows().forEach(r => r.style.display = 'none');
        visible.slice(start, start + PAGE_SIZE).forEach(r => r.style.display = '');

        const countEl = document.getElementById('brgy-count-info');
        if (countEl) {
            countEl.textContent = `Showing ${Math.min(start+1, total)}–${Math.min(start+PAGE_SIZE, total)} of ${total}`;
        }

        const pg = document.getElementById('brgy-pagination');
        if (!pg) return;
        pg.innerHTML = '';
        if (pages <= 1) return;

        const prev = document.createElement('button');
        prev.className = 'btn btn-sm btn-light'; prev.textContent = '← Prev';
        prev.disabled = currentPage === 1;
        prev.onclick = () => { currentPage--; renderPage(); };
        pg.appendChild(prev);

        const info = document.createElement('span');
        info.className = 'text-muted small'; info.textContent = `Page ${currentPage} of ${pages}`;
        pg.appendChild(info);

        const next = document.createElement('button');
        next.className = 'btn btn-sm btn-dark'; next.textContent = 'Next →';
        next.disabled = currentPage === pages;
        next.onclick = () => { currentPage++; renderPage(); };
        pg.appendChild(next);
    }

    function setupHeaderClickListeners() {
        const headers = document.querySelectorAll('#brgyTable thead tr:first-child th');
        headers.forEach((th, idx) => {
            th.style.cursor = 'pointer';
            th.style.userSelect = 'none';
            // Prevent binding duplicate click listeners
            if (!th.dataset.sortBound) {
                th.dataset.sortBound = 'true';
                th.addEventListener('click', () => sortTable(idx));
            }
        });
        updateSortIndicators();
    }

    window._initOvBrgy = function() { 
        initBrgy(); 
        renderPage(); 
        setupHeaderClickListeners();
    };

    document.addEventListener('DOMContentLoaded', () => { 
        renderPage(); 
        setupHeaderClickListeners();
    });
})();

// ── Top Documents Revenue Chart ──
function initDocRevenueChart() {
    if (window._docRevenueChartInited) return;
    const ctx = document.getElementById('chartDocRevenue');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: docRevenueLabels,
            datasets: [{
                label: 'Revenue (PHP)',
                data: docRevenueValues,
                backgroundColor: '#0d6efd',
                borderRadius: 6,
                barThickness: 24,
                maxBarThickness: 30
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => 'PHP ' + ctx.parsed.x.toLocaleString(undefined, { minimumFractionDigits: 2 })
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        callback: (val) => 'PHP ' + val.toLocaleString()
                    }
                }
            }
        }
    });
    window._docRevenueChartInited = true;
}
window.initDocRevenueChart = initDocRevenueChart;

// ── Announcements Management (view, toggle, delete, edit, switch tabs, cropper) ──
function viewAnnouncement(title, body, author, date, imageUrl) {
    let imgHtml = '';
    if (imageUrl) {
        imgHtml = `<div style="margin-bottom:12px;"><img src="${imageUrl}" alt="Announcement image" style="max-width:100%;max-height:280px;border-radius:10px;border:1px solid #dee2e6;"></div>`;
    }
    Swal.fire({
        title: title,
        html: `<div style="text-align:left;max-height:400px;overflow-y:auto;padding:8px 0;">
                   ${imgHtml}
                   <div style="white-space:pre-wrap;font-size:14px;line-height:1.6;">${body.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')}</div>
                   <hr style="margin:12px 0;">
                   <small class="text-muted"> ${author} &mdash; ${date}</small>
               </div>`,
        width: 620,
        confirmButtonText: 'Close',
        confirmButtonColor: '#3b82f6'
    });
}
window.viewAnnouncement = viewAnnouncement;

function toggleAnnouncement(id, isCurrentlyActive) {
    const action = isCurrentlyActive ? 'Deactivate' : 'Activate';
    const icon   = isCurrentlyActive ? 'warning' : 'success';
    const color  = isCurrentlyActive ? '#f59e0b' : '#22c55e';
    Swal.fire({
        title: action + ' Announcement?',
        text: isCurrentlyActive
            ? 'This announcement will be hidden from all dashboards and the public page.'
            : 'This announcement will become visible on dashboards again.',
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: color,
        confirmButtonText: action,
        cancelButtonColor: '#64748b'
    }).then(result => {
        if (result.isConfirmed) csrfPost('../action/manage_announcement', { id: id, action: 'toggle' });
    });
}
window.toggleAnnouncement = toggleAnnouncement;

function deleteAnnouncement(id, title) {
    Swal.fire({
        title: 'Delete Announcement?',
        html: `<p>Permanently delete: <strong>"${title}"</strong>?</p><small class="text-danger">This action cannot be undone.</small>`,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: '🗑 Delete',
        cancelButtonColor: '#64748b'
    }).then(result => {
        if (result.isConfirmed) csrfPost('../action/manage_announcement', { id: id, action: 'delete' });
    });
}
window.deleteAnnouncement = deleteAnnouncement;

function editAnnouncement(id, title, body, targetRole, barangay, expiresAt, isActive, imageUrl) {
    let editCropper = null;
    const hasImage = imageUrl && imageUrl.length > 0;
    const isTextAnn = body && body !== '(Image Announcement)';
    const imgPreview = hasImage
        ? `<div id="editCurrentImg" class="mb-2 text-center"><img src="${imageUrl}" style="max-height:150px;border-radius:8px;border:1px solid #dee2e6;"><br><small class="text-muted">Current image</small></div>`
        : '';
    const bodyField = isTextAnn
        ? `<label class="form-label fw-semibold">Body <span class="text-danger">*</span></label>
           <textarea id="editBody" class="form-control mb-2" rows="3">${body}</textarea>`
        : '';

    Swal.fire({
        title: 'Edit Announcement',
        html: `
            <div class="text-start">
                <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                <input id="editTitle" class="form-control mb-2" value="${title}" maxlength="200">
                ${bodyField}
                ${imgPreview}
                <label class="form-label fw-semibold">Replace Image <small class="text-muted">(optional — leave blank to keep current)</small></label>
                <input type="file" id="editImageInput" class="form-control mb-2" accept="image/jpeg,image/png,image/gif,image/webp">
                <div id="editCropContainer" style="display:none;margin-bottom:10px;">
                    <div style="max-height:300px;background:#f1f5f9;border-radius:8px;overflow:hidden;border:2px solid #e2e8f0;">
                        <img id="editCropImage" src="" alt="Crop" style="max-width:100%;display:block;">
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Target Audience</label>
                        <select id="editRole" class="form-select">
                            <option value="dashboard" ${targetRole==='dashboard'?'selected':''}>Dashboard Only</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Visibility</label>
                        <select id="editActive" class="form-select">
                            <option value="1" ${isActive?'selected':''}>Visible</option>
                            <option value="0" ${!isActive?'selected':''}>Hidden</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Barangay</label>
                        <input id="editBarangay" class="form-control" value="${barangay}" placeholder="Leave blank for all">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Expires At</label>
                        <input id="editExpires" type="datetime-local" class="form-control" value="${expiresAt}">
                    </div>
                </div>
            </div>
        `,
        width: 650,
        showCancelButton: true,
        confirmButtonText: '💾 Save Changes',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#64748b',
        didOpen: () => {
            document.getElementById('editImageInput').addEventListener('change', function() {
                const container = document.getElementById('editCropContainer');
                const cropImg   = document.getElementById('editCropImage');
                if (editCropper) { editCropper.destroy(); editCropper = null; }
                const curImg = document.getElementById('editCurrentImg');
                if (curImg) curImg.style.display = 'none';

                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = e => {
                        cropImg.src = e.target.result;
                        container.style.display = 'block';
                        editCropper = new Cropper(cropImg, {
                            viewMode: 1,
                            dragMode: 'move',
                            aspectRatio: 1,
                            autoCropArea: 1,
                            responsive: true,
                            guides: true,
                            background: true,
                        });
                    };
                    reader.readAsDataURL(this.files[0]);
                } else {
                    container.style.display = 'none';
                }
            });
        },
        preConfirm: () => {
            const t = document.getElementById('editTitle').value.trim();
            if (!t) { Swal.showValidationMessage('Title is required.'); return false; }
            return { hasNewImage: !!editCropper, cropper: editCropper };
        }
    }).then(result => {
        if (!result.isConfirmed) return;

        const t = document.getElementById('editTitle').value.trim();
        const editBodyEl = document.getElementById('editBody');
        const b = editBodyEl ? editBodyEl.value.trim() || body : (body || '(Image Announcement)');

        function submitEdit(blob) {
            const fd = new FormData();
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) {
                fd.append('csrf_token', csrfMeta.content);
            }
            fd.append('id', id);
            fd.append('title', t);
            fd.append('body', b);
            fd.append('target_role', document.getElementById('editRole').value);
            fd.append('barangay', document.getElementById('editBarangay').value);
            fd.append('expires_at', document.getElementById('editExpires').value);
            fd.append('is_active', document.getElementById('editActive').value);
            if (blob) fd.append('image', blob, 'announcement.jpg');

            fetch('../action/update_announcement', { method: 'POST', body: fd })
                .then(() => { window.location.href = 'city_administrator?panel=announcements'; })
                .catch(() => Swal.fire('Error', 'Failed to update.', 'error'));
        }

        if (result.value.hasNewImage && result.value.cropper) {
            result.value.cropper.getCroppedCanvas({ maxWidth: 1200, maxHeight: 1200 }).toBlob(blob => {
                submitEdit(blob);
            }, 'image/jpeg', 0.9);
        } else {
            submitEdit(null);
        }
    });
}
window.editAnnouncement = editAnnouncement;

function switchAnnTab(mode) {
    const textForm = document.getElementById('annFormText');
    const picForm  = document.getElementById('annFormPicture');
    const tabText  = document.getElementById('tabTextOnly');
    const tabPic   = document.getElementById('tabWithPic');

    if (mode === 'picture') {
        if (textForm) textForm.style.display = 'none';
        if (picForm) picForm.style.display  = 'block';
        if (tabText) tabText.className = 'btn btn-sm btn-outline-dark';
        if (tabPic) tabPic.className  = 'btn btn-sm btn-dark active';
    } else {
        if (textForm) textForm.style.display = 'block';
        if (picForm) picForm.style.display  = 'none';
        if (tabText) tabText.className = 'btn btn-sm btn-dark active';
        if (tabPic) tabPic.className  = 'btn btn-sm btn-outline-dark';
    }
}
window.switchAnnTab = switchAnnTab;

let annCropper = null;

document.getElementById('annImageInput')?.addEventListener('change', function() {
    const container = document.getElementById('cropperContainer');
    const cropImg   = document.getElementById('cropperImage');

    if (annCropper) { annCropper.destroy(); annCropper = null; }

    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            cropImg.src = e.target.result;
            container.style.display = 'block';
            annCropper = new Cropper(cropImg, {
                viewMode: 1,
                dragMode: 'move',
                aspectRatio: 1,
                autoCropArea: 1,
                responsive: true,
                restore: false,
                guides: true,
                center: true,
                highlight: true,
                background: true,
            });
        };
        reader.readAsDataURL(this.files[0]);
    } else {
        container.style.display = 'none';
    }
});

function clearAnnImage() {
    const input = document.getElementById('annImageInput');
    if (input) input.value = '';
    const container = document.getElementById('cropperContainer');
    if (container) container.style.display = 'none';
    if (annCropper) { annCropper.destroy(); annCropper = null; }
}
window.clearAnnImage = clearAnnImage;

document.getElementById('annFormPicture')?.addEventListener('submit', function(e) {
    e.preventDefault();
    if (!annCropper) { Swal.fire('Error', 'Please select an image first.', 'warning'); return; }

    const btn = document.getElementById('btnPostPic');
    btn.disabled = true;
    btn.innerHTML = 'Processing...';

    annCropper.getCroppedCanvas({ maxWidth: 1200, maxHeight: 1200 }).toBlob(blob => {
        const fd = new FormData();
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            fd.append('csrf_token', csrfMeta.content);
        }
        fd.append('title', document.getElementById('picTitle').value);
        fd.append('body', '(Image Announcement)');
        fd.append('target_role', document.getElementById('picRole').value);
        fd.append('expires_at', document.getElementById('picExpires').value);
        fd.append('image', blob, 'announcement.jpg');

        fetch('../action/save_announcement', { method: 'POST', body: fd })
            .then(r => { window.location.href = 'city_administrator?panel=announcements'; })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = 'Post Announcement with Picture';
                Swal.fire('Error', 'Failed to post announcement.', 'error');
            });
    }, 'image/jpeg', 0.9);
});

// ── SYSTEM LOGS AJAX PAGINATION & FILTERING ──
let currentLogPage = 1;
function loadSystemLogs(page = 1) {
    const filterSelect = document.getElementById('logFilterSelect');
    const filter = filterSelect ? filterSelect.value : 'today';
    currentLogPage = page;

    const tbody = document.getElementById('logsTbody');
    if (!tbody) return;

    const pageInfo = document.getElementById('logsPageInfo');
    if (pageInfo) pageInfo.textContent = 'Loading...';

    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-3">⏳ Loading logs...</td></tr>';

    fetch(`../action/get_system_logs?log_filter=${filter}&log_page=${page}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">Error loading logs.</td></tr>';
                if (pageInfo) pageInfo.textContent = '';
                return;
            }

            tbody.innerHTML = '';
            if (!data.logs || !data.logs.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No logs found.</td></tr>';
                if (pageInfo) pageInfo.textContent = 'No matching records';
                renderLogsPagination(data.log_page, data.log_pages);
                return;
            }

            if (pageInfo) {
                const total = data.log_total || 0;
                const start = (data.log_page - 1) * 10 + 1;
                const end = start + data.logs.length - 1;
                pageInfo.textContent = `Showing ${start}–${end} of ${total}`;
            }

            data.logs.forEach(lg => {
                const tr = document.createElement('tr');

                // Log ID
                const tdId = document.createElement('td');
                tdId.textContent = 'LOG-' + (lg.log_id || '—');
                tr.appendChild(tdId);

                // User name
                const tdUser = document.createElement('td');
                tdUser.textContent = lg.user_name || '—';
                tr.appendChild(tdUser);

                // Role badge
                const tdRole = document.createElement('td');
                if (lg.user_type === 'city_administrator') {
                    tdRole.innerHTML = '<span class="badge bg-primary">City Administrator</span>';
                } else if (lg.user_type === 'employee') {
                    tdRole.innerHTML = '<span class="badge bg-success">Barangay Employee</span>';
                } else if (lg.user_type === 'resident') {
                    tdRole.innerHTML = '<span class="badge bg-info">Resident</span>';
                } else {
                    tdRole.textContent = '—';
                }
                tr.appendChild(tdRole);

                // Action
                const tdAction = document.createElement('td');
                tdAction.textContent = lg.action_type || '—';
                tr.appendChild(tdAction);

                // Target
                const tdTarget = document.createElement('td');
                tdTarget.innerHTML = `<small>${lg.target || ''}</small>`;
                tr.appendChild(tdTarget);

                // Details
                const tdDetails = document.createElement('td');
                tdDetails.style.maxWidth = '250px';
                tdDetails.style.overflow = 'hidden';
                tdDetails.style.textOverflow = 'ellipsis';
                tdDetails.style.whiteSpace = 'nowrap';
                tdDetails.innerHTML = `<small>${lg.details || ''}</small>`;
                tr.appendChild(tdDetails);

                // IP
                const tdIp = document.createElement('td');
                tdIp.innerHTML = `<small>${lg.ip_address || ''}</small>`;
                tr.appendChild(tdIp);

                // Time
                const tdTime = document.createElement('td');
                tdTime.innerHTML = `<small>${lg.created_at_formatted || '—'}</small>`;
                tr.appendChild(tdTime);

                tbody.appendChild(tr);
            });

            renderLogsPagination(data.log_page, data.log_pages);
        })
        .catch(err => {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">Failed to fetch logs.</td></tr>';
        });
}

function renderLogsPagination(page, totalPages) {
    const pager = document.getElementById('logsPagination');
    if (!pager) return;

    pager.innerHTML = '';
    if (totalPages <= 1) return;

    const prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.className = 'btn btn-sm btn-outline-secondary';
    prevBtn.textContent = '← Prev';
    prevBtn.disabled = page === 1;
    prevBtn.onclick = () => loadSystemLogs(page - 1);
    pager.appendChild(prevBtn);

    const infoSpan = document.createElement('span');
    infoSpan.className = 'text-muted small px-2 align-self-center';
    infoSpan.textContent = `Page ${page} of ${totalPages}`;
    pager.appendChild(infoSpan);

    const nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.className = 'btn btn-sm btn-outline-secondary';
    nextBtn.textContent = 'Next →';
    nextBtn.disabled = page === totalPages;
    nextBtn.onclick = () => loadSystemLogs(page + 1);
    pager.appendChild(nextBtn);
}

window.loadSystemLogs = loadSystemLogs;

document.addEventListener('DOMContentLoaded', () => {
    const logFilterSelect = document.getElementById('logFilterSelect');
    if (logFilterSelect) {
        logFilterSelect.addEventListener('change', () => {
            loadSystemLogs(1);
        });
    }

    // If the active panel on page load is logs, load the logs immediately
    const activePanel = document.querySelector('.panel.active');
    if (activePanel && activePanel.id === 'panel-logs') {
        loadSystemLogs(1);
    }
});

function applyDocFilter() {
    const year = document.getElementById('docFilterYear').value;
    const month = document.getElementById('docFilterMonth').value;
    const limit = document.getElementById('docFilterLimit').value;
    const monthSelect = document.getElementById('docFilterMonth');

    monthSelect.disabled = !year;
    if (!year) monthSelect.value = '';

    let url = '?panel=top-documents';
    url += '&doc_year=' + encodeURIComponent(year);
    url += '&doc_month=' + encodeURIComponent(year ? month : '');
    url += '&doc_limit=' + encodeURIComponent(limit);

    window.location.href = url;
}
window.applyDocFilter = applyDocFilter;

// ── Forgot Password (My Profile) ──
document.addEventListener('DOMContentLoaded', () => {
    const resetBtn = document.getElementById('adminResetBtn');
    if (!resetBtn) return;

    const adminEmail = cityAdminConfig.adminEmail || '';
    const adminUserId = cityAdminConfig.adminUserId || '';

    resetBtn.addEventListener('click', function () {
        if (!adminEmail) {
            Swal.fire({ icon: 'error', title: 'No Email Found', text: 'Registered email not available.' });
            return;
        }

        Swal.fire({
            title: 'Sending reset email...',
            text: 'Please wait',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch('../auth/forgot_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: adminEmail, user_id: adminUserId })
        })
        .then(res => res.json())
        .then(data => {
            Swal.fire({ icon: data.success ? 'success' : 'error', title: 'Password Reset', text: data.message });
        })
        .catch(() => {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to send reset email.' });
        });
    });
});