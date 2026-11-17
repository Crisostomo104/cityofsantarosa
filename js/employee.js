// ======================
// PANEL SYSTEM (CORE)
// ======================
const employeeDashboardConfig = window.employeeDashboardConfig || {};
const employeeInitialPanel = employeeDashboardConfig.initialPanel || '';
const employeeShowWelcome = employeeDashboardConfig.showWelcome === true;
const chartDocLabels = employeeDashboardConfig.chartDocLabels || [];
const chartDocData = employeeDashboardConfig.chartDocData || [];
const chartDailyLabels = employeeDashboardConfig.chartDailyLabels || [];
const chartDailyData = employeeDashboardConfig.chartDailyData || [];

function hideLoadingOverlay() {
    const overlay = document.getElementById('dashboardLoadingOverlay');
    if (!overlay) return;
    setTimeout(() => {
        overlay.classList.add('is-hidden');
        setTimeout(() => overlay.style.display = 'none', 250);
    }, 400);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hideLoadingOverlay);
} else {
    hideLoadingOverlay();
}

window.addEventListener('beforeunload', () => {
    const overlay = document.getElementById('dashboardLoadingOverlay');
    if (overlay) {
        overlay.style.display = 'flex';
        overlay.classList.remove('is-hidden');
    }
});

setTimeout(hideLoadingOverlay, 3000);

function initCharts() {
    if (window._employeeChartsInited) return;
    const docCtx = document.getElementById('chartDocType');
    const dayCtx = document.getElementById('chartDaily');
    if (docCtx) {
        new Chart(docCtx, {
            type: 'bar',
            data: {
                labels: chartDocLabels,
                datasets: [{
                    label: 'Requests',
                    data: chartDocData,
                    backgroundColor: ['#0d6efd', '#198754', '#dc3545', '#ffc107', '#0dcaf0', '#6f42c1'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }
    if (dayCtx) {
        new Chart(dayCtx, {
            type: 'line',
            data: {
                labels: chartDailyLabels,
                datasets: [{
                    label: 'Requests',
                    data: chartDailyData,
                    fill: true,
                    backgroundColor: 'rgba(13,110,253,0.1)',
                    borderColor: '#0d6efd',
                    tension: 0.4,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }
    window._employeeChartsInited = true;
}

function showPanel(panel, el) {
    document.querySelectorAll('.panel').forEach(el => {
        el.classList.remove('active');
        el.style.display = 'none';
    });

    document.querySelectorAll('.sidebar a').forEach(a => a.classList.remove('active'));

    const target = document.getElementById('panel-' + panel);
    if (target) {
        target.classList.add('active');
        target.style.display = 'block';
    }

    if (el) el.classList.add('active');

    const matchingTab = document.querySelector(`.ov-tab[data-tab="${panel}"]`);
    if (matchingTab) matchingTab.classList.add('active');

    localStorage.setItem('activePanel', panel);
}

function showGroupTab(panelName, tabEl, groupName) {
    document.querySelectorAll('.panel').forEach(el => {
        el.classList.remove('active');
        el.style.display = 'none';
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

    localStorage.setItem('activePanel', panelName);
}

function openRequestEditModal(id, position, currentName) {
    const reqOffId = document.getElementById('req_off_id');
    const reqOffPos = document.getElementById('req_off_pos');
    const reqOffName = document.getElementById('req_off_name');
    if (!reqOffId || !reqOffPos || !reqOffName) return;

    reqOffId.value = id;
    reqOffPos.textContent = position;
    reqOffName.value = currentName;
    new bootstrap.Modal(document.getElementById('requestEditModal')).show();
}

function toggleSidebar() {
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
}

function updateSidebarClock() {
    const el = document.getElementById('sidebarClock');
    if (!el) return;
    const now = new Date();
    const date = now.toLocaleDateString(undefined, { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
    const time = now.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    el.textContent = `${date} ${time}`;
}

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
        dropdown.style.position = '';
        dropdown.style.width = '';
        return;
    }

    dropdown.classList.add('show');
    requestAnimationFrame(() => positionUserDropdown(dropdown, trigger));
}

function initWelcomeModal() {
    if (!employeeShowWelcome || typeof bootstrap === 'undefined') return;
    const welcomeModal = document.getElementById('welcomeModal');
    const welcomeRingSvg = document.querySelector('.welcome-ring-svg');
    const modalInstance = welcomeModal ? new bootstrap.Modal(welcomeModal) : null;
    if (!modalInstance) return;
    modalInstance.show();
    if (welcomeRingSvg) {
        setTimeout(() => modalInstance.hide(), 15000);
    }
}

// ======================
// CSRF-SAFE POST HELPER
// ======================
function csrfPost(url, params = {}) {
    const form = document.createElement('form');
    form.method = 'POST';
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
    form.action = url;
    form.submit();
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function ensureDuplicateInfoModal() {
    const overlayId = 'duplicateInfoOverlay';
    if (document.getElementById(overlayId)) return;

    const modalHtml = `
        <div id="${overlayId}" style="display:none; position:fixed; inset:0; z-index:2147483647; background:rgba(15,23,42,0.55); backdrop-filter:blur(2px); align-items:center; justify-content:center; padding:22px; overflow:auto;">
            <div style="width:min(760px,100%); max-height:calc(100vh - 44px); overflow:hidden; background:#ffffff; border-radius:16px; box-shadow:0 24px 60px rgba(15,23,42,0.22); border:1px solid rgba(15,23,42,0.08);">
                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:18px 20px 14px; border-bottom:1px solid #e5e7eb;">
                    <div>
                        <h5 style="margin:0; font-size:1.05rem;">Duplicate file information</h5>
                        <p style="margin:.35rem 0 0; font-size:.92rem; color:#4b5563;">This file was previously uploaded in another request and may be a duplicate.</p>
                    </div>
                    <button type="button" id="duplicateInfoOverlayClose" style="border:none; background:transparent; font-size:1.25rem; line-height:1; color:#374151; cursor:pointer;">×</button>
                </div>
                <div style="padding:18px 20px 22px; overflow:auto; max-height:calc(100vh - 136px);">
                    <p id="duplicateInfoModalMessage" style="margin:0 0 14px; color:#b91c1c; font-weight:600;"></p>
                    <div style="overflow-x:auto;">
                        <table class="table table-sm table-bordered mb-0" style="min-width:100%;">
                            <thead class="table-light"><tr><th>Request ID</th><th>Document</th><th>Barangay</th><th>Uploaded</th></tr></thead>
                            <tbody id="duplicateInfoModalBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    const overlay = document.getElementById(overlayId);
    const closeButton = document.getElementById('duplicateInfoOverlayClose');

    if (overlay) {
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                overlay.style.display = 'none';
            }
        });
    }

    if (closeButton) {
        closeButton.addEventListener('click', () => {
            const overlayEl = document.getElementById(overlayId);
            if (overlayEl) overlayEl.style.display = 'none';
        });
    }
}

window.duplicateInfoStore = window.duplicateInfoStore || {};
window.showDuplicateInfoModal = function(key) {
    const info = window.duplicateInfoStore[key];
    if (!info) return;
    ensureDuplicateInfoModal();

    const overlay = document.getElementById('duplicateInfoOverlay');
    const message = document.getElementById('duplicateInfoModalMessage');
    const body = document.getElementById('duplicateInfoModalBody');
    if (!overlay || !message || !body) return;

    message.textContent = `The same file was previously uploaded in ${info.records.length} other request(s), including files from other barangay areas.`;
    body.innerHTML = info.records.map(record => `
        <tr>
            <td>#${escapeHtml(record.request_id)}</td>
            <td>${escapeHtml(record.document_type || 'Unknown')}</td>
            <td>${escapeHtml(record.barangay || 'N/A')}</td>
            <td>${escapeHtml(record.uploaded_at || '—')}</td>
        </tr>
    `).join('');

    overlay.style.display = 'flex';
};

function formatDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return escapeHtml(String(value));
    }
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

let isDetailFetchInProgress = false;
let attachmentPreviewItems = [];
let attachmentPreviewKeydownHandler = null;

function getAppBasePath() {
    const pathname = (window.location.pathname || '/').replace(/\\/g, '/');
    const segments = pathname.split('/').filter(Boolean);
    const ignored = new Set(['dashboards', 'auth', 'partials', 'action', 'payment', 'js', 'css', 'images', 'uploads']);
    const baseSegments = [];

    for (const segment of segments) {
        if (segment.includes('.')) {
            break;
        }
        if (ignored.has(segment)) {
            break;
        }
        baseSegments.push(segment);
    }

    return baseSegments.length > 0 ? '/' + baseSegments.join('/') : '';
}

function buildActionUrl(relativePath) {
    try {
        const normalized = String(relativePath || '').trim();
        if (!normalized) return '';
        if (/^https?:\/\//i.test(normalized)) {
            return normalized;
        }

        const base = getAppBasePath();
        const baseUrl = `${window.location.origin}${base ? base : ''}/`;
        const cleanPath = normalized.replace(/^(\.\.\/|\.\/|\/)+/, '');
        return new URL(cleanPath, baseUrl).href;
    } catch (err) {
        return String(relativePath);
    }
}

function wasRedirectedToLogin(response) {
    if (!response?.redirected || !response.url) return false;

    try {
        return new URL(response.url).pathname.toLowerCase().includes('/auth/login');
    } catch (err) {
        return false;
    }
}

function closeAttachmentPreview() {
    const overlay = document.getElementById('attachmentPreviewOverlay');
    if (overlay) {
        overlay.remove();
    }
    if (attachmentPreviewKeydownHandler) {
        document.removeEventListener('keydown', attachmentPreviewKeydownHandler);
        attachmentPreviewKeydownHandler = null;
    }
    document.body.style.overflow = '';
}

function openAttachmentPreview(index) {
    if (!attachmentPreviewItems.length) return;

    closeAttachmentPreview();

    const safeIndex = ((index % attachmentPreviewItems.length) + attachmentPreviewItems.length) % attachmentPreviewItems.length;
    const item = attachmentPreviewItems[safeIndex];

    const overlay = document.createElement('div');
    overlay.id = 'attachmentPreviewOverlay';
    overlay.style.position = 'fixed';
    overlay.style.inset = '0';
    overlay.style.background = 'rgba(15, 23, 42, 0.86)';
    overlay.style.zIndex = '100000';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.style.padding = '20px';

    const isImg = item.isImage || String(item.src || '').match(/\.(jpg|jpeg|png|webp)$/i);
    const showNav = attachmentPreviewItems.length > 1;

    overlay.innerHTML = `
        <div style="position:relative; z-index:100001; width:min(92vw, 900px); max-height:92vh; background:#fff; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,0.35); overflow:hidden;">
            <button type="button" onclick="closeAttachmentPreview()" style="position:absolute; top:10px; right:12px; border:none; background:#111827; color:#fff; border-radius:50%; width:38px; height:38px; font-size:20px; z-index:2;">×</button>
            <div style="position:absolute; top:14px; left:50%; transform:translateX(-50%); z-index:2; font-size:12px; font-weight:700; color:#111827; background:rgba(255,255,255,0.95); padding:6px 10px; border-radius:999px; border:1px solid #e5e7eb;">${safeIndex + 1}/${attachmentPreviewItems.length}</div>
            <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid #e5e7eb; background:#f9fafb;">
                <div>
                    <div style="font-size:14px; font-weight:700; color:#111827;">${escapeHtml(item.title || 'Attachment Preview')}</div>
                    <div style="font-size:12px; color:#6b7280;">${escapeHtml(item.originalName || '')}</div>
                </div>
            </div>
            <div style="position:relative; display:flex; align-items:center; justify-content:center; padding:18px; background:#f3f4f6;">
                ${showNav ? `<button type="button" data-action="prev" style="position:absolute; left:18px; top:50%; transform:translateY(-50%); border:1px solid #d1d5db; background:#fff; border-radius:50%; width:42px; height:42px; box-shadow:0 4px 10px rgba(0,0,0,0.12);">←</button>` : ''}
                ${isImg ? `<img src="${item.src}" alt="Attachment preview" onerror="if(!this.dataset.fb){this.dataset.fb=1;this.src='${item.src.replace(/\.png$/i, '.jpg')}';}" style="max-width:100%; max-height:72vh; object-fit:contain; border-radius:10px; background:#fff;">` : `<iframe src="${item.src}" style="max-width:100%; max-height:72vh; width:100%; height:72vh; border:0; background:#fff;"></iframe>`}
                ${showNav ? `<button type="button" data-action="next" style="position:absolute; right:18px; top:50%; transform:translateY(-50%); border:1px solid #d1d5db; background:#fff; border-radius:50%; width:42px; height:42px; box-shadow:0 4px 10px rgba(0,0,0,0.12);">→</button>` : ''}
            </div>
        </div>
    `;

    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            closeAttachmentPreview();
        }
    });

    const prevBtn = overlay.querySelector('[data-action="prev"]');
    const nextBtn = overlay.querySelector('[data-action="next"]');
    prevBtn?.addEventListener('click', () => openAttachmentPreview(safeIndex - 1));
    nextBtn?.addEventListener('click', () => openAttachmentPreview(safeIndex + 1));

    attachmentPreviewKeydownHandler = (event) => {
        if (event.key === 'Escape') {
            closeAttachmentPreview();
        } else if (event.key === 'ArrowLeft') {
            openAttachmentPreview(safeIndex - 1);
        } else if (event.key === 'ArrowRight') {
            openAttachmentPreview(safeIndex + 1);
        }
    };
    document.addEventListener('keydown', attachmentPreviewKeydownHandler);

    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
}

window.openAttachmentPreview = openAttachmentPreview;

async function parseJsonResponse(response) {
    const text = await response.text();
    const trimmed = text ? text.trim() : '';

    if (!trimmed) {
        throw new Error('The server returned an empty response.');
    }

    try {
        return JSON.parse(trimmed);
    } catch (err) {
        if (trimmed.startsWith('<')) {
            throw new Error('The server returned an HTML page instead of JSON. Please refresh and try again.');
        }
        throw new Error(`Invalid JSON response from the server: ${trimmed.slice(0, 180)}`);
    }
}

document.addEventListener('click', (e) => {

    // ======================
    // VIEW REQUEST
    // ======================
    const viewBtn = e.target.closest('.view-btn');
    if (viewBtn) {
        if (isDetailFetchInProgress) {
            return;
        }

        isDetailFetchInProgress = true;
        viewBtn.disabled = true;

        const requestId = viewBtn.dataset.id;
        const modalEl = document.getElementById('reportModal');
        const modalBody = document.getElementById('reportDetails');
        const modal = new bootstrap.Modal(modalEl);

        modalBody.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 mb-0">Loading request...</p>
            </div>
        `;

        modal.show();

        const detailUrl = buildActionUrl('../action/get_request_details?request_id=' + encodeURIComponent(requestId));

        // Fetch request data via AJAX
        fetch(detailUrl, { credentials: 'same-origin', redirect: 'follow' })
            .then(r => {
                if (wasRedirectedToLogin(r)) {
                    throw new Error('Your session has expired. Please sign in again.');
                }
                if (!r.ok) {
                    return r.text().then(text => {
                        const msg = text ? text.trim().slice(0, 180) : '';
                        throw new Error(`HTTP ${r.status}: ${r.statusText}${msg ? ` - ${msg}` : ''}`);
                    });
                }
                return parseJsonResponse(r);
            })
            .then(data => {
                if (!data || data.error) {
                    console.error('API error:', data?.error);
                    modalBody.innerHTML = '<p class="text-danger">Error: ' + (data?.error || 'Unknown error') + '</p>';
                    return;
                }

                const displayID =
                    `REQ${data.created_at.split(' ')[0].replace(/-/g, '')}${data.user_id}-${String(data.request_id).padStart(3, '0')}`;
                const rawPaymentMethod = String(data.preferred_payment_method || '');
                const isWalkInPayment = rawPaymentMethod.trim().toLowerCase() === 'walk-in';
                const paymentMethod = rawPaymentMethod ? escapeHtml(rawPaymentMethod) : '—';
                const paymentStatus = data.payment_status ? escapeHtml(data.payment_status) : '—';
                const paymentAmount = data.payment_amount ? `PHP ${Number(data.payment_amount).toFixed(2)}` : 'Free';
                const paymentReference = isWalkInPayment
                    ? (data.receipt_number ? escapeHtml(data.receipt_number) : '—')
                    : (data.payment_reference ? escapeHtml(data.payment_reference) : '—');
                const paymentReferenceLabel = isWalkInPayment ? 'Receipt No.' : 'Payment Reference';
                const rejectionReason = data.rejection_reason ? escapeHtml(data.rejection_reason) : '—';
                const trackingCode = data.tracking_code ? escapeHtml(data.tracking_code) : '';
                const qrToken = data.qr_token ? escapeHtml(data.qr_token) : '';
                const verifyUrl = (trackingCode && qrToken)? buildActionUrl('/../cityofsantarosa/auth/verify?code=' + encodeURIComponent(data.tracking_code) + '&token=' + encodeURIComponent(data.qr_token)) : '';
                const requesterName = data.full_name ? escapeHtml(data.full_name) : '—';
                const relationship = data.relationship ? escapeHtml(data.relationship) : '—';
                const accountName = data.account_name ? escapeHtml(data.account_name) : '—';
                const gender = data.gender ? escapeHtml(data.gender) : '—';
                const age = data.age ? escapeHtml(data.age) : '—';
                const fullAddress = escapeHtml(data.full_address || data.address || '—');
                const contactNumber = escapeHtml(data.contact_number || data.phone || '—');
                const dateOfBirth = formatDate(data.date_of_birth || data.dob || data.birth_date);
                const civilStatus = data.civil_status ? escapeHtml(data.civil_status) : '—';
                const residencyYears = data.years_residency ? `${escapeHtml(data.years_residency)} YRS` : '—';
                const documentType = escapeHtml(data.document_type || '—');
                const purpose = escapeHtml(data.purpose || '—');
                const statusValue = escapeHtml(data.status || 'Pending');
                const statusClass = String(data.status || 'Pending').toLowerCase();
                const canModifyAttachments = !['approved', 'completed'].includes(statusClass);
                const badgeClass = statusClass === 'approved' ? 'bg-success' : statusClass === 'rejected' ? 'bg-danger' : statusClass === 'completed' ? 'bg-primary' : 'bg-warning text-dark';
                const requestedOn = formatDate(data.created_at);

                modalBody.innerHTML = `
                    <div class="row gx-4 gy-4">
                        <div class="col-lg-6">
                            <div class="p-3 bg-light border rounded-3 h-100">
                                <h6 class="mb-3">Requester Details</h6>
                                <dl class="row mb-0">
                                    <dt class="col-5 text-muted">Request ID</dt>
                                    <dd class="col-7"><strong class="text-primary">${escapeHtml(displayID)}</strong></dd>
                                    
									<dt class="col-5 text-muted">Requested By</dt>
                                    <dd class="col-7"><strong>${accountName}</strong></dd>

                                    <dt class="col-5 text-muted">Full Name</dt>
                                    <dd class="col-7">${requesterName}</dd>

                                    <dt class="col-5 text-muted">Relationship</dt>
                                    <dd class="col-7" id="requestRelationshipValue">${relationship}</dd>
                                    
									<dt class="col-5 text-muted">Gender</dt>
                                    <dd class="col-7">${gender}</dd>

                                    <dt class="col-5 text-muted">Age</dt>
                                    <dd class="col-7">${age}</dd>

                                    <dt class="col-5 text-muted">Full Address</dt>
                                    <dd class="col-7">${fullAddress}</dd>

                                    <dt class="col-5 text-muted">Contact Number</dt>
                                    <dd class="col-7">${contactNumber}</dd>

                                    <dt class="col-5 text-muted">Date of Birth</dt>
                                    <dd class="col-7">${dateOfBirth}</dd>

                                    <dt class="col-5 text-muted">Civil Status</dt>
                                    <dd class="col-7">${civilStatus}</dd>

                                    <dt class="col-5 text-muted">Year of Residency</dt>
                                    <dd class="col-7">${residencyYears}</dd>

                                    <dt class="col-5 text-muted">Document</dt>
                                    <dd class="col-7">${documentType}</dd>

                                    <dt class="col-5 text-muted">Purpose</dt>
                                    <dd class="col-7">${purpose}</dd>

                                    <dt class="col-5 text-muted">Status</dt>
                                    <dd class="col-7"><span class="badge ${badgeClass}">${statusValue}</span></dd>

                                    <dt class="col-5 text-muted">Date Requested</dt>
                                    <dd class="col-7">${requestedOn}</dd>

                                    ${data.valid_until ? `
                                    <dt class="col-5 text-muted">Valid Until</dt>
                                    <dd class="col-7"><strong class="text-success">${formatDate(data.valid_until)}</strong></dd>
                                    ` : ''}
                                </dl>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="p-3 bg-light border rounded-3 h-100">
                                <h6 class="mb-3">Payment Details</h6>
                                <dl class="row mb-0">
                                    <dt class="col-5 text-muted">Payment Method</dt>
									<dd class="col-7">${paymentMethod === 'walk-in' ? 'Cash' : paymentMethod}</dd>

                                    <dt class="col-5 text-muted">Payment Status</dt>
                                    <dd class="col-7">${paymentStatus}</dd>

                                    <dt class="col-5 text-muted">Payment Amount</dt>
                                    <dd class="col-7">${paymentAmount}</dd>

                                    <dt class="col-5 text-muted">${paymentReferenceLabel}</dt>
									<dd class="col-7">${paymentReference}</dd>

                                    <dt class="col-5 text-muted">Processed By</dt>
                                    <dd class="col-7">${data.processed_by_name ? 'EMP-' + String(data.processed_by_id).padStart(3, '0') + ' — ' + escapeHtml(data.processed_by_name) : '—'}</dd>
                                    
                                    <dt class="col-5 text-muted">Rejection Reason</dt>
                                    <dd class="col-7">${rejectionReason}</dd>
                                </dl>
                                ${verifyUrl ? `
                                    <div id="qrCodeContainer" class="mt-4 text-center">
                                        <div style="display:inline-block; padding:16px; background:#fff; border:1px solid #e2e8f0; border-radius:12px;">
                                            <div id="paymentQrCode" style="width:130px; height:130px; margin:0 auto;"></div>
                                            <p class="mt-2 mb-0" style="font-size:.82rem; color:#6b7280;">Scan to verify this request</p>
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div id="attachmentSection"></div>
                    </div>
                `;

                            if (verifyUrl && typeof QRCode !== 'undefined') {
                                const qrDiv = document.getElementById('paymentQrCode');
                                if (qrDiv) {
                                    qrDiv.innerHTML = '';
                                    new QRCode(qrDiv, {
                                        text: verifyUrl,
                                        width: 130,
                                        height: 130,
                                        colorDark: '#0f172a',
                                        colorLight: '#ffffff',
                                    });
                                }
                            }

                // Fetch prerequisite attachments
                return fetch(buildActionUrl('../action/get_attachments?request_id=' + encodeURIComponent(requestId)), { credentials: 'same-origin', redirect: 'follow' })
                    .then(r => {
                        if (wasRedirectedToLogin(r)) {
                            throw new Error('Your session has expired. Please sign in again.');
                        }
                        if (!r.ok) {
                            return r.text().then(text => {
                                const msg = text ? text.trim().slice(0, 180) : '';
                                throw new Error(`HTTP ${r.status}: ${r.statusText}${msg ? ` - ${msg}` : ''}`);
                            });
                        }
                        return parseJsonResponse(r);
                    })
                    .then(attachments => {
                        const section = document.getElementById('attachmentSection');
                        if (!section || !Array.isArray(attachments) || attachments.length === 0) return;

                        attachmentPreviewItems = [];
                        const authorizationLetter = attachments.find(att => att.prereq_name === '__authorization_letter__');
                        const relationshipValue = document.getElementById('requestRelationshipValue');
                        if (authorizationLetter && relationshipValue) {
                            const fileUrl = buildActionUrl(authorizationLetter.file_path);
                            const previewIndex = attachmentPreviewItems.push({
                                title: 'Authorization Letter',
                                originalName: authorizationLetter.original_name,
                                src: fileUrl
                            }) - 1;
                            const previewButton = document.createElement('button');
                            previewButton.type = 'button';
                            previewButton.className = 'btn btn-sm btn-outline-primary ms-2';
                            previewButton.textContent = 'Auth. Letter';
                            previewButton.addEventListener('click', () => openAttachmentPreview(previewIndex));
                            relationshipValue.appendChild(previewButton);
                        }

                        const prerequisiteAttachments = attachments.filter(att => att.prereq_name !== '__authorization_letter__');
                        if (prerequisiteAttachments.length === 0) return;
                        let html = '<div class="attachment-section"><p><b>📎 Prerequisite Documents:</b></p>';
                        prerequisiteAttachments.forEach((att, index) => {
                            const isImage = att.file_path.match(/\.(jpg|jpeg|png|webp)$/i);
                            const sizeKB = att.file_size > 0 ? (att.file_size / 1024).toFixed(0) : null;
                            const fileUrl = buildActionUrl(att.file_path);
                            const fallbackJpg = fileUrl.replace(/\.png$/i, '.jpg');
                            const duplicateHistory = Array.isArray(att.duplicate_history) ? att.duplicate_history : [];
                            let duplicateHtml = '';

                            if (duplicateHistory.length > 0) {
                                const duplicateKey = 'duplicate-history-' + Math.random().toString(36).slice(2, 10) + '-' + index;
                                window.duplicateInfoStore[duplicateKey] = {
                                    name: att.prereq_name,
                                    records: duplicateHistory
                                };

                                duplicateHtml = '<div class="duplicate-history text-danger" style="margin-top:6px; border-left: 3px solid #dc2626; background: rgba(254, 226, 226, 0.95); padding: 10px 12px; border-radius: 8px;">';
                                duplicateHtml += `<button type="button" class="btn btn-sm btn-outline-danger w-100 text-start" style="padding:0; background:transparent; border:none; color:#991b1b;" onclick="window.showDuplicateInfoModal('${duplicateKey}')">`;
                                duplicateHtml += `<div style="font-weight:700; color:#b91c1c; margin-bottom:4px;">Duplicate file detected</div>`;
                                duplicateHtml += `<div style="font-size:12px; color:#991b1b; margin-bottom:4px;">Click for details from ${duplicateHistory.length} other request(s)</div>`;
                                duplicateHtml += `</button>`;
                                duplicateHtml += '</div>';
                            }

                            if (isImage) {
                                attachmentPreviewItems.push({
                                    title: att.prereq_name,
                                    originalName: att.original_name,
                                    src: fileUrl
                                });
                            }

                            html += `<div class="attachment-item" data-att-id="${att.id ?? att.attachment_id ?? index}">
                                ${isImage ? `<button type="button" class="p-0 border-0 bg-transparent" onclick="openAttachmentPreview(${attachmentPreviewItems.length - 1})"><img src="${fileUrl}" alt="Preview" onerror="if(!this.dataset.fb){this.dataset.fb=1;this.src='${fallbackJpg}';}" style="width:56px; height:56px; object-fit:cover; border-radius:8px;"></button>` : '<span style="font-size:24px">📄</span>'}
                                <div class="attachment-details">
                                    <strong>${escapeHtml(att.prereq_name)}</strong><br>
                                    <small class="text-muted">${escapeHtml(att.original_name)}${sizeKB ? ` (${sizeKB}KB)` : ''}</small>
                                    ${duplicateHtml}
                                </div>
                                <div class="attachment-controls">
                                    ${isImage ? `<button type="button" class="btn btn-sm btn-outline-primary" onclick="openAttachmentPreview(${attachmentPreviewItems.length - 1})">Preview</button>` : `<a href="${fileUrl}" target="_blank" class="btn btn-sm btn-outline-primary" style="text-decoration:none">View</a>`}
                                    ${canModifyAttachments ? `<button type="button" class="delete-attachment btn btn-sm btn-outline-danger" data-id="${att.id ?? att.attachment_id ?? ''}" title="Remove document">×</button>` : ''}
                                </div>
                            </div>`;
                        });
                        html += '</div>';
                        section.innerHTML = html;
                    });
            })
            .catch(err => {
                console.error('Failed to fetch request:', detailUrl, err);
                modalBody.innerHTML = '<p class="text-danger">Error loading request: ' + escapeHtml(err.message) + '</p>';
            })
            .finally(() => {
                isDetailFetchInProgress = false;
                viewBtn.disabled = false;
            });

        // Mark as viewed silently in the background
        fetch(buildActionUrl('../action/mark_viewed?id=' + encodeURIComponent(requestId)), { credentials: 'same-origin', redirect: 'follow' });

        return;
    }

    // ======================
    // ACTION BUTTONS (CSRF-safe POST)
    // ======================
    const btn = e.target.closest('.action-btn');
    if (!btn) return;

    const id = btn.dataset.id;
    const action = btn.dataset.action;

    let url = '';
    let confirmText = 'Confirm action?';
    let useFetchSubmit = false;

    switch (action) {
        case 'approve':
            url = '../action/approve_request';
            confirmText = 'Approve this request?';
            break;
        case 'reject':
            url = '../action/reject_request';
            confirmText = 'Reject this request?';
            break;
        case 'delete':
            url = '../action/delete_request';
            confirmText = 'Archive this request?';
            break;
        case 'process':
            url = '../action/process_request';
            confirmText = 'Mark this request ready for pickup and notify the resident?';
            break;
        case 'claim':
            url = '../action/mark_claimed.php';
            confirmText = 'Confirm that the resident has claimed this document?';
            break;
        case 'mark-paid':
            url = '../action/mark_paid.php';
            confirmText = btn.dataset.completeClaim === '1'
                ? 'Confirm that payment is received and the document has been claimed?'
                : 'Mark this cash request as paid?';
            useFetchSubmit = true;
            break;
    }

    if (!url) return;

    if (action === 'reject') {
        // Rejection needs a reason — prompt first
        Swal.fire({
            title: 'Rejection Reason',
            input: 'text',
            inputPlaceholder: 'Enter reason for rejection...',
            showCancelButton: true,
            inputValidator: (v) => !v && 'Reason is required!'
        }).then(res => {
            if (!res.isConfirmed) return;
            csrfPost(url, { id: id, reason: res.value });
        });
        return;
    }

    if (action === 'process' || action === 'claim') {
        const isClaim = action === 'claim';
        Swal.fire({
            title: isClaim ? 'Confirm Document Claim' : 'Ready for Pickup',
            text: confirmText,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: isClaim ? 'Mark Claimed' : 'Mark Ready',
        }).then(res => {
            if (!res.isConfirmed) return;
            csrfPost(url, { id: id });
        });
        return;
    }

    if (useFetchSubmit) {
        Swal.fire({
            text: confirmText,
            showCancelButton: true,
            confirmButtonText: btn.dataset.completeClaim === '1' ? 'Paid & Complete' : 'Yes',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                const formData = new FormData();
                formData.append('id', id);
                if (btn.dataset.completeClaim === '1') {
                    formData.append('complete_claim', '1');
                }
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                if (csrfMeta) {
                    formData.append('csrf_token', csrfMeta.content);
                }

                return fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData,
                    redirect: 'follow'
                }).then(response => {
                    if (response.redirected && response.url.toLowerCase().includes('/auth/login')) {
                        throw new Error('Your session has expired. Please sign in again.');
                    }

                    if (!response.ok) {
                        return response.text().then(text => {
                            const trimmed = text ? text.trim() : '';
                            throw new Error(`Server returned ${response.status}: ${trimmed.slice(0, 180)}`);
                        });
                    }

                    return response.text();
                });
            }
        }).then(result => {
            if (result.isConfirmed) {
                window.location.reload();
            }
        }).catch(err => {
            Swal.fire({
                icon: 'error',
                title: 'Unable to mark paid',
                text: err.message || 'Please refresh and try again.'
            });
        });

        return;
    }

    Swal.fire({
        text: confirmText,
        showCancelButton: true,
        confirmButtonText: 'Yes',
    }).then(res => {
        if (!res.isConfirmed) return;
        csrfPost(url, { id: id });
    });

});

// ======================
// DELETE PREREQUISITE ATTACHMENT (View Request modal)
// ======================
document.addEventListener('click', function (e) {
    const delBtn = e.target.closest('.delete-attachment');
    if (!delBtn) return;
    e.preventDefault();

    const id = delBtn.dataset.id;
    if (!id) return;

    Swal.fire({
        title: 'Delete attachment?',
        text: 'Removing a required document will automatically reject this request. This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const form = new FormData();
            form.append('id', id);
            return fetch(buildActionUrl('../action/delete_attachment.php'), { method: 'POST', body: form, credentials: 'same-origin' })
                .then(r => r.json())
                .then(res => {
                    if (!res || !res.success) throw new Error(res?.error || 'Failed to delete');
                    return res;
                })
                .catch(err => {
                    Swal.showValidationMessage(err.message || 'Request failed');
                });
        }
    }).then(result => {
        if (result.isConfirmed && result.value && result.value.success) {
            const node = delBtn.closest('[data-att-id]');
            if (node) node.remove();

            if (result.value.auto_rejected) {
                Swal.fire({
                    icon: 'error',
                    title: 'Request Auto-Rejected',
                    text: 'The prerequisite document was removed, so this request has been automatically rejected (Reason: Removed Document).',
                    confirmButtonText: 'OK'
                }).then(() => {
                    // Reload so the table row status/reason and dashboard counts refresh
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted',
                    toast: true,
                    position: 'top-end',
                    timer: 1600,
                    showConfirmButton: false
                });
            }
        }
    });
});

// ======================
// APPROVE / DENY REFUND REQUEST
// ======================
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.refund-action-btn');
    if (!btn) return;
    e.preventDefault();

    const id = btn.dataset.id;
    const action = btn.dataset.refundAction; // 'approve' | 'deny'
    if (!id || !action) return;

    const isApprove = action === 'approve';
    Swal.fire({
        title: isApprove ? 'Approve this refund?' : 'Deny this refund request?',
        text: isApprove
            ? 'This will process the refund with the payment provider. This cannot be undone.'
            : 'The resident will be notified that their refund request was not approved.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: isApprove ? 'Approve Refund' : 'Deny',
        confirmButtonColor: isApprove ? '#198754' : '#dc3545',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const form = new FormData();
            form.append('id', id);
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) form.append('csrf_token', csrfMeta.content);

            const url = isApprove ? '../action/approve_refund.php' : '../action/deny_refund.php';
            return fetch(buildActionUrl(url), { method: 'POST', body: form, credentials: 'same-origin' })
                .then(r => r.json())
                .then(res => {
                    if (!res || !res.success) throw new Error(res?.error || 'Request failed');
                    return res;
                })
                .catch(err => {
                    Swal.showValidationMessage(err.message || 'Request failed');
                });
        }
    }).then(result => {
        if (result.isConfirmed && result.value && result.value.success) {
            Swal.fire({
                icon: 'success',
                title: isApprove ? 'Refund Approved' : 'Refund Denied',
                timer: 1600,
                showConfirmButton: false
            }).then(() => window.location.reload());
        }
    });
});


// ======================
// RESIDENT SEARCH (INLINE FIXED)
// ======================
document.addEventListener('DOMContentLoaded', () => {

    const search = document.getElementById('residentSearch');
    const table = document.querySelector('#panel-residents table tbody');

    if (!search || !table) return;

    const rows = table.getElementsByTagName('tr');

    search.addEventListener('input', () => {

        const val = search.value.toUpperCase();

        for (let i = 0; i < rows.length; i++) {

            const cells = rows[i].getElementsByTagName('td');
            let match = false;

            for (let j = 0; j < cells.length; j++) {
                if ((cells[j].textContent || '').toUpperCase().includes(val)) {
                    match = true;
                    break;
                }
            }

            rows[i].style.display = match ? '' : 'none';
        }
    });
});


// ======================
// MANUAL REQUEST INIT (SAFE INLINE)
// ======================
document.addEventListener('DOMContentLoaded', () => {

    const openBtn = document.getElementById('manualRequestBtn');
    const modalEl = document.getElementById('manualRequestModal');

    if (!openBtn || !modalEl || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalEl);

    openBtn.addEventListener('click', () => {
        modal.show();
        initManualRequest();
    });
});

let manualInit = false;

function initManualRequest() {

    if (manualInit) return;
    manualInit = true;

    const dob = document.getElementById('m_dob');
    const age = document.getElementById('m_age');
    const residency = document.getElementById('m_residency');

    const docType = document.getElementById('m_doc_type');
    const customDiv = document.getElementById('m_custom_doc_div');
    const customInput = document.getElementById('m_custom_doc');

    const fullName = document.getElementById('m_full_name');
    const purpose = document.getElementById('m_purpose');

    if (!dob) return;

    // Set max date to today so future/absurd years can't be entered
    (function setDobLimits() {
        const now = new Date();
        const yyyy = now.getFullYear();
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        const dd = String(now.getDate()).padStart(2, '0');
        dob.setAttribute('max', `${yyyy}-${mm}-${dd}`);
        dob.setAttribute('min', '1900-01-01');
    })();

    [fullName, purpose].forEach(el => {
        if (!el) return;
        el.addEventListener('input', () => {
            el.value = el.value.toUpperCase();
        });
    });

    dob.addEventListener('change', () => {

        const d = new Date(dob.value);
        const now = new Date();

        // Guard against invalid / future dates
        if (isNaN(d.getTime()) || d > now || d.getFullYear() < 1900) {
            age.value = '';
            residency.value = '';
            return;
        }

        let a = now.getFullYear() - d.getFullYear();
        const m = now.getMonth() - d.getMonth();

        if (m < 0 || (m === 0 && now.getDate() < d.getDate())) {
            a--;
        }

        age.value = a;
        residency.max = a;

        if (!residency.value || parseInt(residency.value) > a) {
            residency.value = a;
        }
    });

    if (docType) {
        docType.addEventListener('change', () => {

            if (docType.value === 'Other') {
                customDiv.style.display = 'block';
                customInput.required = true;
            } else {
                customDiv.style.display = 'none';
                customInput.required = false;
                customInput.value = '';
            }
        });
    }
}


// ======================
// CREATE EMPLOYEE FORM
// ======================
document.addEventListener('DOMContentLoaded', () => {

    const form = document.getElementById('createEmployeeForm');
    if (!form) return;

    // Auto-uppercase name fields
    ['firstname', 'middlename', 'lastname'].forEach(name => {
        const el = form.querySelector(`[name="${name}"]`);
        if (el) el.addEventListener('input', () => { el.value = el.value.toUpperCase(); });
    });

    // Password match check on submit
    form.addEventListener('submit', (e) => {
        const pw = document.getElementById('emp_password');
        const cpw = document.getElementById('emp_confirm_password');

        if (pw.value.length < 6) {
            e.preventDefault();
            pw.classList.add('is-invalid');
            pw.setCustomValidity('Password must be at least 6 characters.');
            pw.reportValidity();
            return;
        }

        if (pw.value !== cpw.value) {
            e.preventDefault();
            cpw.classList.add('is-invalid');
            cpw.setCustomValidity('Passwords do not match.');
            cpw.reportValidity();
            return;
        }

        pw.classList.remove('is-invalid');
        cpw.classList.remove('is-invalid');
    });
});


// ======================
// EDIT REJECTED EMPLOYEE
// ======================
function editRejected(userId, firstname, middlename, lastname, email, phone) {

    const form = document.getElementById('createEmployeeForm');
    if (!form) return;

    // Set hidden user_id → backend will UPDATE instead of INSERT
    document.getElementById('emp_user_id').value = userId;

    // Pre-fill name + email + phone
    form.querySelector('[name="firstname"]').value = firstname.toUpperCase();
    form.querySelector('[name="middlename"]').value = middlename.toUpperCase();
    form.querySelector('[name="lastname"]').value = lastname.toUpperCase();
    form.querySelector('[name="email"]').value = email;
    const phoneField = form.querySelector('[name="phone"]');
    if (phoneField && phone) phoneField.value = phone;

    // Clear passwords — must be re-entered
    document.getElementById('emp_password').value = '';
    document.getElementById('emp_confirm_password').value = '';

    // Update submit button
    const submitBtn = form.querySelector('[type="submit"]');
    if (submitBtn) {
        submitBtn.innerHTML = 'Resubmit for Approval';
        submitBtn.classList.remove('btn-primary');
        submitBtn.classList.add('btn-warning');
    }

    // Show editing banner (only once)
    if (!document.getElementById('resubmit-note')) {
        const note = document.createElement('div');
        note.id = 'resubmit-note';
        note.className = 'alert alert-warning py-2 mb-3';
        note.style.fontSize = '13px';
        note.innerHTML = '✏️ <strong>Editing rejected account.</strong> Fix the details and click <b>Resubmit for Approval</b>.';
        form.prepend(note);
    }

    // Scroll to the form
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}


// ── SHARED TABLE FILTER HELPERS (from city admin) ──
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
            const text = Array.from(row.querySelectorAll('td'))
                .filter((_, index) => index !== statusColumnIndex && index !== paymentColumnIndex)
                .map(td => td.innerText)
                .join(' ')
                .toLowerCase();
            const matchesSearch = !query || text.includes(query);

            let matchesColumns = true;
            Object.entries(state.columnFilters).forEach(([colIdx, filterValue]) => {
                if (!filterValue) return;
                const cell = row.cells[colIdx];
                if (cell && !cell.innerText.toLowerCase().includes(filterValue)) {
                    matchesColumns = false;
                }
            });

            return matchesSearch && matchesColumns;
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

    window[tableId + '_setColumnFilter'] = (colIdx, query) => {
        state.columnFilters[colIdx] = (query || '').toLowerCase().trim();
        state.currentPage = 1;
        renderPage();
    };

    renderPage();
    return { renderPage };
}

// Search all text columns
function filterTable(tableId, query) {
    if (window[tableId + '_setSearch']) {
        window[tableId + '_setSearch'](query);
    }
}

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

// Filter a specific column (0-indexed)
function filterColTable(tableId, colIdx, query) {
    if (window[tableId + '_setColumnFilter']) {
        window[tableId + '_setColumnFilter'](colIdx, query);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    toggleSidebar();
    initWelcomeModal();
    initCharts();
    initDocRevenueChart();
    
    const urlPanel = employeeInitialPanel;
    const saved = urlPanel || localStorage.getItem('activePanel') || 'dashboard';
    const manage = ['requests', 'payments', 'top-documents'];
    const accounts = ['profile', 'add-employee', 'my-submissions', 'pending-requests', 'residents'];

    if (manage.includes(saved)) showGroupTab(saved, null, 'manage');
    else if (accounts.includes(saved)) showGroupTab(saved, null, 'accounts');
    else { const link = document.querySelector(`.sidebar a[onclick*="'${saved}'"]`); showPanel(saved, link); }

    if (window.history.replaceState) {
        const p = new URLSearchParams(location.search);
        p.delete('panel'); p.delete('tab');
        const q = p.toString();
        window.history.replaceState(null, '', location.pathname + (q ? '?' + q : ''));
    }

    updateSidebarClock();
    setInterval(updateSidebarClock, 1000);

    requestAnimationFrame(() => {
        const requestTable = document.getElementById('reqTable');
        if (!requestTable || requestTable.dataset.serverPaginated !== 'true') {
            initTablePagination('reqTable', 15, 'reqPageInfo', 'reqPagination');
            const requestSearch = document.getElementById('reqSearch');
            if (requestSearch && requestSearch.value) {
                filterTable('reqTable', requestSearch.value);
            }
        }
        initTablePagination('resTable', 15, 'resPageInfo', 'resPagination');

        const residentStatus = new URLSearchParams(window.location.search).get('resident_status') || 'all';
        if (['online', 'offline', 'deactivated'].includes(residentStatus) && window.resTable_setColumnFilter) {
            window.resTable_setColumnFilter(5, residentStatus);
        }
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

    const payoutForm = document.getElementById('payoutForm');
    if (payoutForm) {
        payoutForm.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                text: 'Submit payout request to City Administrator?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, submit',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    e.target.submit();
                }
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
            .catch(() => { });
    }, 15000);

    window.showPanel = showPanel;
    window.showGroupTab = showGroupTab;
    window.openRequestEditModal = openRequestEditModal;
    window.toggleUserDropdown = toggleUserDropdown;
    window.positionUserDropdown = positionUserDropdown;
    window.editRejected = editRejected;
    window.filterTable = filterTable;
    window.filterColTable = filterColTable;
});

function applyDocFilter() {
    const year = document.getElementById('docFilterYear').value;
    const month = document.getElementById('docFilterMonth').value;
    const monthSelect = document.getElementById('docFilterMonth');

    monthSelect.disabled = !year;
    if (!year) monthSelect.value = '';

    let url = '?panel=top-documents';
    url += '&doc_year=' + encodeURIComponent(year);
    url += '&doc_month=' + encodeURIComponent(year ? month : '');

    window.location.href = url;
}
window.applyDocFilter = applyDocFilter;

const docRevenueLabels = employeeDashboardConfig.docRevenueLabels || [];
const docRevenueValues = employeeDashboardConfig.docRevenueValues || [];

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
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y', // ← ito ang gumagawa ng horizontal bar
            responsive: true,
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