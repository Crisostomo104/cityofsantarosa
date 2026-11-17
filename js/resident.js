let openModalCount = 0;

function lockScroll() {
    document.body.style.overflow = "hidden";
}

function unlockScroll() {
    document.body.style.overflow = "";
}

function openModal(modal) {
    modal.style.display = "flex";
    openModalCount++;
    lockScroll();
}

function closeModal(modal) {
    modal.style.display = "none";
    openModalCount--;

    if (openModalCount <= 0) {
        openModalCount = 0;
        unlockScroll();
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

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

function buildAppUrl(path) {
    const normalized = String(path || '').trim();
    if (!normalized) {
        return '';
    }
    if (/^https?:\/\//i.test(normalized)) {
        return normalized;
    }

    const base = getAppBasePath();
    const baseUrl = `${window.location.origin}${base ? base : ''}/`;
    return new URL(normalized.replace(/^\/+/, ''), baseUrl).href;
}

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

let attachmentPreviewItems = [];
let attachmentPreviewKeydownHandler = null;

function closeAttachmentPreviewOverlay() {
    const overlay = document.getElementById('attachmentPreviewOverlay');
    if (overlay) {
        overlay.remove();
    }
    if (attachmentPreviewKeydownHandler) {
        document.removeEventListener('keydown', attachmentPreviewKeydownHandler);
        attachmentPreviewKeydownHandler = null;
    }
}

function closeAttachmentPreview() {
    closeAttachmentPreviewOverlay();
    // NOTE: blob URLs are intentionally NOT revoked here. Revoking on every
    // modal close used to wipe out blobs for files the resident never
    // touched, since it revoked the entire shared _registeredBlobUrls list —
    // not just the one being viewed. Actual cleanup now happens only when a
    // specific file is removed or replaced (handled in prerequisite_upload.js).
    document.body.style.overflow = '';
}

function openAttachmentPreview(index) {
    const rawItems = (Array.isArray(window.attachmentPreviewItems) && window.attachmentPreviewItems.length)
        ? window.attachmentPreviewItems
        : (Array.isArray(attachmentPreviewItems) ? attachmentPreviewItems : []);

    // Removed/replaced local files are nulled out in place (not spliced) so
    // other slots' stored indices stay valid. Filter those gaps out here so
    // the counter (X/Y) and navigation only count attachments that are
    // actually still present.
    const items = rawItems.filter(Boolean);
    if (!items.length) return;

    // Translate the incoming raw array index into a position within the
    // filtered list. If it points at a still-valid item, find that same
    // item's position in the filtered list; otherwise treat it as already
    // a filtered-list position (used internally by prev/next navigation).
    let startIndex;
    if (index >= 0 && index < rawItems.length && rawItems[index]) {
        startIndex = items.indexOf(rawItems[index]);
        if (startIndex === -1) startIndex = 0;
    } else {
        startIndex = ((index % items.length) + items.length) % items.length;
    }

    renderAttachmentPreview(items, startIndex);
}

function renderAttachmentPreview(items, index) {
    if (!items.length) return;

    closeAttachmentPreviewOverlay();

    const safeIndex = ((index % items.length) + items.length) % items.length;
    const item = items[safeIndex];

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
    const showNav = items.length > 1;
    overlay.innerHTML = `
        <div style="position:relative; z-index:100001; width:min(92vw, 900px); max-height:92vh; background:#fff; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,0.35); overflow:hidden;">
            <button type="button" onclick="closeAttachmentPreview()" style="position:absolute; top:10px; right:12px; border:none; background:#111827; color:#fff; border-radius:50%; width:38px; height:38px; font-size:20px; z-index:2;">×</button>
            <div style="position:absolute; top:14px; left:50%; transform:translateX(-50%); z-index:2; font-size:12px; font-weight:700; color:#111827; background:rgba(255,255,255,0.95); padding:6px 10px; border-radius:999px; border:1px solid #e5e7eb;">${safeIndex + 1}/${items.length}</div>
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
    prevBtn?.addEventListener('click', () => renderAttachmentPreview(items, safeIndex - 1));
    nextBtn?.addEventListener('click', () => renderAttachmentPreview(items, safeIndex + 1));

    attachmentPreviewKeydownHandler = (event) => {
        if (event.key === 'Escape') {
            closeAttachmentPreview();
        } else if (event.key === 'ArrowLeft') {
            renderAttachmentPreview(items, safeIndex - 1);
        } else if (event.key === 'ArrowRight') {
            renderAttachmentPreview(items, safeIndex + 1);
        }
    };
    document.addEventListener('keydown', attachmentPreviewKeydownHandler);

    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
}

window.openAttachmentPreview = openAttachmentPreview;

// Open any file (image/pdf/other) inside an inline modal.
function openFileModal(url, title, originalName) {
    if (!url) return;

    // close any existing preview
    closeAttachmentPreviewOverlay();

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

    const fileIsImage = String(url).match(/\.(jpg|jpeg|png|webp)$/i);

    overlay.innerHTML = `
        <div style="position:relative; z-index:100001; width:min(92vw, 900px); max-height:92vh; background:#fff; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,0.35); overflow:hidden;">
            <button type="button" onclick="closeAttachmentPreview()" style="position:absolute; top:10px; right:12px; border:none; background:#111827; color:#fff; border-radius:50%; width:36px; height:36px; font-size:18px; z-index:2;">×</button>
            <div style="padding:14px 18px; border-bottom:1px solid #e5e7eb; background:#f9fafb;">
                <div style="font-size:14px; font-weight:700; color:#111827;">${escapeHtml(title || 'Attachment Preview')}</div>
                <div style="font-size:12px; color:#6b7280;">${escapeHtml(originalName || '')}</div>
            </div>
            <div style="padding:18px; display:flex; align-items:center; justify-content:center; background:#f3f4f6;">
                ${fileIsImage ? `<img src="${url}" alt="Preview" style="max-width:100%; max-height:72vh; object-fit:contain; border-radius:8px; background:#fff;">` : `<iframe src="${url}" style="width:100%; height:72vh; border:0; background:#fff;"></iframe>`}
            </div>
        </div>
    `;

    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            closeAttachmentPreview();
        }
    });

    attachmentPreviewKeydownHandler = (event) => {
        if (event.key === 'Escape') {
            closeAttachmentPreview();
        }
    };
    document.addEventListener('keydown', attachmentPreviewKeydownHandler);

    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
}

window.openFileModal = openFileModal;

// Modal open/close
function initRequestModalToggle() {
    const openModalBtn = document.getElementById("openModalBtn");
    const modal = document.getElementById("requestModal");
    const closeModalBtn = document.getElementById("closeModalBtn");
    if (!openModalBtn || !modal || !closeModalBtn) {
        return;
    }

    openModalBtn.addEventListener("click", () => {
        if (Number(window.requestCooldownRemaining || 0) > 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Cooldown active',
                text: `Please wait ${window.requestCooldownRemaining} seconds before creating another request.`,
                confirmButtonColor: '#8E1B1B'
            });
            return;
        }

        openModal(modal);

        form.reset();
        document.getElementById('request_id').value = "";
        purposeSelect?.querySelectorAll('option[data-existing-purpose-option="true"]').forEach(option => option.remove());
        updatePurposeFields();
        clearRequestAgainDocumentLock();
        document.getElementById('document_type').disabled = false;
        document.querySelector('#document_type option[data-request-again-option="true"]')?.remove();
        if (duplicateConfirmInput) duplicateConfirmInput.value = '0';
        if (replaceDuplicateInput) replaceDuplicateInput.value = '0';
        document.getElementById('document_type').disabled = false;
        const customDocEl = document.getElementById('custom_document');
        if (customDocEl) customDocEl.disabled = false;
        // Clear any edit-mode attachments left in memory so create modal starts fresh
        window.editModeExistingAttachments = [];
        window.editModeAuthorizationLetter = null;
        window.editModeOriginalRequestName = '';
        window.authorizationLetterSelectedForName = '';
        window.authorizationLetterAttachmentsLoading = false;
        window.syncRequestRelationshipFields?.();
        // Remove prerequisite UI from any previous edit state
        const pc = document.getElementById('prereqContainer');
        if (pc) pc.innerHTML = '';

        // Reset payment method field from any previous "paid" edit state
        const payMethodSelect = document.getElementById('preferred_payment_method');
        if (payMethodSelect) {
            payMethodSelect.disabled = false;
            payMethodSelect.value = '';
        }
        const refInfoEl = document.getElementById('paidReferenceInfo');
        if (refInfoEl) refInfoEl.remove();
    });

    closeModalBtn.addEventListener("click", () => {
        closeModal(modal);
    });

    window.addEventListener("click", (e) => {
        if (e.target === modal) {
            closeModal(modal);
        }
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initRequestModalToggle);
} else {
    initRequestModalToggle();
}

const submissionKeyInput = document.getElementById('submission_key');
const duplicateRequestModal = document.getElementById('duplicateRequestModal');
const duplicateConfirmInput = document.getElementById('confirm_duplicate_request');
const replaceDuplicateInput = document.getElementById('replace_duplicate_request');
const requestIdInput = document.getElementById('request_id');
const cooldownHint = document.getElementById('cooldownHint');
let pendingDuplicateRequest = null;
let cooldownAlertHideTimer = null;

function getResidentRequestHistory() {
    return Array.isArray(window.residentRequestHistory) ? window.residentRequestHistory : [];
}

function getActiveResidentRequestHistory() {
    return Array.isArray(window.residentActiveRequestHistory)
        ? window.residentActiveRequestHistory
        : getResidentRequestHistory();
}

function normalizeDuplicateValue(value) {
    return String(value || '').trim().replace(/\s+/g, ' ').toLowerCase();
}

function ensureSubmissionKey() {
    if (!submissionKeyInput || submissionKeyInput.value) return submissionKeyInput?.value || '';
    const key = `req_${Date.now()}_${Math.random().toString(16).slice(2, 10)}`;
    if (submissionKeyInput) submissionKeyInput.value = key;
    return key;
}

function clearRequestAgainDocumentLock() {
    document.getElementById('requestAgainDocumentType')?.remove();
    document.getElementById('requestAgainCustomDocument')?.remove();
}

function lockRequestAgainDocumentType(record) {
    const documentTypeField = document.getElementById('document_type');
    const customDocumentField = document.getElementById('custom_document');
    if (!documentTypeField || !record?.document_type) return;

    clearRequestAgainDocumentLock();
    let matchingOption = Array.from(documentTypeField.options).find(
        (option) => normalizeDuplicateValue(option.value) === normalizeDuplicateValue(record.document_type)
    );
    const needsOption = !matchingOption;

    if (!matchingOption) {
        matchingOption = new Option(record.document_type, record.document_type, true, true);
        matchingOption.dataset.requestAgainOption = 'true';
        documentTypeField.add(matchingOption);
    }

    documentTypeField.value = matchingOption.value;
    documentTypeField.disabled = true;

    const documentTypeMirror = document.createElement('input');
    documentTypeMirror.type = 'hidden';
    documentTypeMirror.name = 'document_type';
    documentTypeMirror.id = 'requestAgainDocumentType';
    documentTypeMirror.value = matchingOption.value;
    form.appendChild(documentTypeMirror);

    if (customDocumentField && matchingOption.value === 'Other') {
        customDocumentField.disabled = true;
        const customDocumentMirror = document.createElement('input');
        customDocumentMirror.type = 'hidden';
        customDocumentMirror.name = 'custom_document';
        customDocumentMirror.id = 'requestAgainCustomDocument';
        customDocumentMirror.value = customDocumentField.value;
        form.appendChild(customDocumentMirror);
    } else if (customDocumentField) {
        customDocumentField.disabled = false;
    }

    if (needsOption) {
        documentTypeField.dispatchEvent(new Event('change'));
    }
}

function closeDuplicateModal() {
    if (!duplicateRequestModal) return;
    duplicateRequestModal.style.display = 'none';
    duplicateRequestModal.setAttribute('aria-hidden', 'true');
    duplicateRequestModal.classList.remove('show');
    closeModal(duplicateRequestModal);
}

function openDuplicateModal(duplicate) {
    if (!duplicateRequestModal) {
        return;
    }

    pendingDuplicateRequest = duplicate;
    const isReplaceable = String(duplicate?.status || '').toLowerCase() === 'pending';
    const replaceButton = document.getElementById('duplicateRequestReplaceBtn');
    const message = document.getElementById('duplicateRequestMessage');
    if (replaceButton) {
        replaceButton.disabled = !isReplaceable;
        replaceButton.textContent = isReplaceable ? 'Replace Existing' : 'Replace unavailable';
    }
    if (message) {
        message.textContent = isReplaceable
            ? 'You already have a pending request for this document. Replace it with these details, or create a separate request?'
            : 'This request is already approved. You can create a separate request, but it cannot be replaced.';
    }

    openModal(duplicateRequestModal);
    duplicateRequestModal.setAttribute('aria-hidden', 'false');
    duplicateRequestModal.classList.add('show');
}

function hasActiveDuplicateRequest() {
    const currentFullName = normalizeDuplicateValue(document.getElementById('full_name')?.value);
    const selectedDocumentValue = String(documentType?.value || '');
    const selectedDocType = normalizeDuplicateValue(
        selectedDocumentValue === 'Other'
            ? document.getElementById('custom_document')?.value
            : selectedDocumentValue
    );
    const currentRequestId = String(requestIdInput?.value || '');

    return getActiveResidentRequestHistory().find((item) => {
        const status = normalizeDuplicateValue(item?.status);
        const documentName = normalizeDuplicateValue(item?.document_type);
        const residentName = normalizeDuplicateValue(item?.full_name);

        return ['pending', 'approved'].includes(status)
            && documentName === selectedDocType
            && residentName === currentFullName
            && String(item?.id || '') !== currentRequestId
            && currentFullName.length > 0
            && selectedDocType.length > 0;
    }) || null;
}

function showCooldownHint(seconds) {
    if (!cooldownHint) return;
    if (!seconds || seconds <= 0) {
        cooldownHint.classList.add('d-none');
        cooldownHint.textContent = '';
        return;
    }

    cooldownHint.classList.remove('d-none');
    cooldownHint.textContent = `Please wait ${seconds} seconds before creating another request.`;
}

function updateRequestActionButtons(seconds) {
    const openButton = document.getElementById('openModalBtn');
    const openButtonLabel = document.getElementById('newRequestLabel');
    const cooldownStatus = document.getElementById('requestCooldownStatus');
    const cooldownAlert = document.getElementById('requestCooldownAlert');
    const cooldownCountdown = document.getElementById('requestCooldownCountdown');
    const isCoolingDown = seconds > 0;
    const formattedTime = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;

    if (openButton) {
        openButton.disabled = isCoolingDown;
        openButton.setAttribute('aria-disabled', String(isCoolingDown));
        if (isCoolingDown) {
            openButton.title = `Please wait ${seconds} seconds before creating another request.`;
        } else {
            openButton.title = 'Create a new request';
        }
    }

    if (openButtonLabel) {
        openButtonLabel.style.display = isCoolingDown ? 'none' : '';
    }

    if (cooldownStatus) {
        if (isCoolingDown) {
            cooldownStatus.textContent = `Wait ${formattedTime}`;
            cooldownStatus.style.display = 'inline-block';
        } else {
            cooldownStatus.textContent = '';
            cooldownStatus.style.display = 'none';
        }
    }

    if (cooldownAlert) {
        if (isCoolingDown) {
            if (cooldownAlertHideTimer) {
                clearTimeout(cooldownAlertHideTimer);
                cooldownAlertHideTimer = null;
            }
            if (cooldownAlert.style.display === 'none') {
                cooldownAlert.style.display = 'block';
                requestAnimationFrame(() => cooldownAlert.classList.add('is-visible'));
            } else {
                cooldownAlert.classList.add('is-visible');
            }
        } else if (cooldownAlert.style.display !== 'none' && !cooldownAlertHideTimer) {
            cooldownAlert.classList.remove('is-visible');
            cooldownAlertHideTimer = setTimeout(() => {
                cooldownAlert.style.display = 'none';
                cooldownAlertHideTimer = null;
            }, 300);
        }
    }
    if (cooldownCountdown) {
        cooldownCountdown.textContent = formattedTime;
    }

    document.querySelectorAll('.edit-btn, .request-again-btn').forEach((button) => {
        button.disabled = isCoolingDown;
        button.setAttribute('aria-disabled', String(isCoolingDown));
        if (isCoolingDown) {
            button.title = `Please wait ${seconds} seconds before creating another request.`;
            button.classList.add('disabled');
        } else {
            button.title = button.classList.contains('edit-btn') ? 'Edit Request' : 'Request Again';
            button.classList.remove('disabled');
        }
    });
}

function updateCooldownState(seconds) {
    const button = document.getElementById('submitBtn');
    if (button) {
        if (seconds > 0) {
            button.disabled = true;
            button.innerText = `Wait ${seconds}s`;
        } else {
            button.disabled = false;
            button.innerText = 'Submit Request';
        }
    }

    updateRequestActionButtons(seconds);
    showCooldownHint(seconds);
}

function populateRequestDetails(record, options = {}) {
    if (!record || !form) return;

    const shouldOverride = Boolean(options.override);
    const fillIfBlank = (fieldId, value) => {
        const field = document.getElementById(fieldId);
        if (!field) return;
        const nextValue = value ?? '';
        if (field.type === 'checkbox') {
            field.checked = Boolean(nextValue);
            return;
        }

        if (!shouldOverride && field.value && String(field.value).trim() !== '') {
            return;
        }

        field.value = nextValue;
    };

    if (record.document_name_parts && typeof record.document_name_parts === 'object') {
        setRequestNameFromParts(record.document_name_parts);
    } else {
        setRequestNameFromFullName(record.full_name || '');
    }
    getRequestNameParts().first?.dispatchEvent(new Event('input', { bubbles: true }));
    const relationshipSelect = document.getElementById('relationship');
    if (relationshipSelect && !relationshipSelect.disabled) {
        fillIfBlank('relationship', record.relationship || '');
    }
    fillIfBlank('gender', record.gender || '');
    fillIfBlank('dob', record.dob || '');
    fillIfBlank('age', record.age || '');
    fillIfBlank('civil_status', record.civil_status || '');
    fillIfBlank('address', record.address || '');
    fillIfBlank('contact_number', record.contact_number || '');
    fillIfBlank('years_residency', record.years_residency || '');
    fillIfBlank('document_type', record.document_type || '');
    setPurposeFromStoredValue(record.purpose || '', !shouldOverride);
    fillIfBlank('preferred_payment_method', record.preferred_payment_method || '');

    const documentTypeField = document.getElementById('document_type');
    const customDocumentField = document.getElementById('custom_document');
    const customDocumentDiv = document.getElementById('customDocumentDiv');
    if (documentTypeField && record.document_type === 'Other') {
        if (customDocumentDiv) customDocumentDiv.style.display = 'block';
        if (customDocumentField) customDocumentField.value = record.custom_document || '';
    }

    if (record.document_type) {
        documentTypeField.value = record.document_type;
        if (typeof documentTypeField.dispatchEvent === 'function') {
            documentTypeField.dispatchEvent(new Event('change'));
        }
    }

    const dobField = document.getElementById('dob');
    if (dobField && dobField.value) {
        dobField.dispatchEvent(new Event('change'));
    }

}

function hookRequestAgainButtons() {
    document.querySelectorAll('.request-again-btn').forEach((button) => {
        button.addEventListener('click', (event) => {
            if (Number(window.requestCooldownRemaining || 0) > 0) {
                event.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Cooldown active',
                    text: `Please wait ${window.requestCooldownRemaining} seconds before creating another request.`,
                    confirmButtonColor: '#8E1B1B'
                });
                return;
            }

            event.preventDefault();
            const requestId = button.dataset.id;
            const selected = getResidentRequestHistory().find(item => String(item.id) === String(requestId));
            if (!selected) return;

            const modal = document.getElementById('requestModal');
            if (!modal) return;
            openModal(modal);

            form.reset();
            document.getElementById('request_id').value = '';
            clearRequestAgainDocumentLock();
            document.querySelector('#document_type option[data-request-again-option="true"]')?.remove();
            document.getElementById('document_type').disabled = false;
            const customDocEl = document.getElementById('custom_document');
            if (customDocEl) customDocEl.disabled = false;
            window.editModeExistingAttachments = [];
            const pc = document.getElementById('prereqContainer');
            if (pc) pc.innerHTML = '';

            populateRequestDetails(selected, { override: true });
            lockRequestAgainDocumentType(selected);
        });
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
        hookRequestAgainButtons();
    });
} else {
    hookRequestAgainButtons();
}

// Show/hide custom document input
const documentType = document.getElementById("document_type");
const customDiv = document.getElementById("customDocumentDiv");
const customInput = document.getElementById("custom_document");
const purposeSelect = document.getElementById('purpose');
const purposeOtherGroup = document.getElementById('purposeOtherGroup');
const purposeOtherInput = document.getElementById('purposeOther');
const purposeRenewalGroup = document.getElementById('purposeRenewalGroup');
const purposeRenewalInput = document.getElementById('purposeRenewalReference');

function updatePurposeFields() {
    const isOther = purposeSelect?.value === 'Others (specify)';
    const isRenewal = purposeSelect?.value === 'Renew';

    if (purposeOtherGroup) {
        purposeOtherGroup.classList.toggle('animated-field--hidden', !isOther);
        purposeOtherGroup.setAttribute('aria-hidden', String(!isOther));
    }
    if (purposeOtherInput) {
        purposeOtherInput.required = isOther;
        if (!isOther) purposeOtherInput.value = '';
    }

    if (purposeRenewalGroup) {
        purposeRenewalGroup.classList.toggle('animated-field--hidden', !isRenewal);
        purposeRenewalGroup.setAttribute('aria-hidden', String(!isRenewal));
    }
    if (purposeRenewalInput) {
        purposeRenewalInput.required = isRenewal;
        if (!isRenewal) purposeRenewalInput.value = '';
    }

    markRequiredFields();
}

function setPurposeFromStoredValue(value, onlyIfBlank = false) {
    if (!purposeSelect) return;
    if (onlyIfBlank && purposeSelect.value) return;
    purposeSelect.querySelectorAll('option[data-existing-purpose-option="true"]').forEach(option => option.remove());

    const storedPurpose = String(value || '');
    let selectedPurpose = storedPurpose;
    let otherDetails = '';
    let renewalReference = '';

    if (storedPurpose.startsWith('Others: ')) {
        selectedPurpose = 'Others (specify)';
        otherDetails = storedPurpose.slice('Others: '.length);
    } else if (storedPurpose.startsWith('Renew: ')) {
        selectedPurpose = 'Renew';
        renewalReference = storedPurpose.slice('Renew: '.length);
    }

    const matchingOption = Array.from(purposeSelect.options).find(option => option.value === selectedPurpose);
    if (!matchingOption && selectedPurpose) {
        if (onlyIfBlank) {
            selectedPurpose = 'Others (specify)';
            otherDetails = storedPurpose;
        } else {
            const legacyOption = document.createElement('option');
            legacyOption.value = selectedPurpose;
            legacyOption.textContent = `Existing purpose: ${selectedPurpose}`;
            legacyOption.dataset.existingPurposeOption = 'true';
            purposeSelect.add(legacyOption);
        }
    }

    purposeSelect.value = selectedPurpose;
    updatePurposeFields();
    if (purposeOtherInput) purposeOtherInput.value = otherDetails;
    if (purposeRenewalInput) purposeRenewalInput.value = renewalReference;
}

purposeSelect?.addEventListener('change', updatePurposeFields);

function markRequiredFields() {
    form?.querySelectorAll('[required]').forEach((field) => {
        const label = field.labels?.[0]
            || field.closest('.form-group')?.querySelector(':scope > label')
            || field.closest('label');
        if (!label || label.querySelector('.required-field-indicator')) return;

        const indicator = document.createElement('span');
        indicator.className = 'required-field-indicator text-danger';
        indicator.setAttribute('aria-hidden', 'true');
        indicator.textContent = ' *';
        label.appendChild(indicator);
    });
}

documentType.addEventListener("change", function () {
    if (this.value === "Other") {
        customDiv.style.display = "block";
        customInput.required = true;
    } else {
        customDiv.style.display = "none";
        customInput.required = false;
        customInput.value = "";
    }
    markRequiredFields();
});

// Auto-calculate age from DOB
const dobInput = document.getElementById("dob");
const ageInput = document.getElementById("age");

const residencyInput = document.getElementById("years_residency");

// Set max date to today so future/absurd years can't be entered
(function setDobLimits() {
    const now = new Date();
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    dobInput.setAttribute('max', `${yyyy}-${mm}-${dd}`);
    dobInput.setAttribute('min', '1900-01-01');
})();

dobInput.addEventListener("change", () => {
    const dob = new Date(dobInput.value);
    const today = new Date();

    // Remove any existing age warning
    const existingWarning = document.getElementById('ageWarning');
    if (existingWarning) existingWarning.remove();

    // Guard against invalid / future dates
    if (isNaN(dob.getTime()) || dob > today || dob.getFullYear() < 1900) {
        ageInput.value = '';
        residencyInput.value = '';
        if (submitBtn) submitBtn.disabled = false;
        return;
    }

    let age = today.getFullYear() - dob.getFullYear();
    const monthDiff = today.getMonth() - dob.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age--;
    }
    ageInput.value = age;

    residencyInput.max = age;
    residencyInput.value = age;

    // ── Age restriction: must be 18 or older to submit a request ──
    if (age < 18) {
        const warning = document.createElement('div');
        warning.id = 'ageWarning';
        warning.style.cssText = `margin-top: 8px;padding: 12px;background: #fef2f2;border: 1px solid #fecaca;border-radius: 8px;font-size: 14px;color: #991b1b;display: flex;align-items: flex-start;gap: 10px;line-height: 1.4;word-break: break-word;`;
		warning.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span>You must be at least <strong>18 years old</strong> to submit a request.</span>`;
ageInput.closest('.form-group').appendChild(warning);
        if (submitBtn) submitBtn.disabled = true;
    } else {
        if (submitBtn) submitBtn.disabled = false;
    }
});

residencyInput.addEventListener("input", () => {
    const age = parseInt(ageInput.value) || 0;
    const residency = parseInt(residencyInput.value) || 0;

    if (residency > age) {
        residencyInput.value = age;
    }
});
// Form submission handler
const form = document.getElementById("requestForm");
const submitBtn = document.getElementById("submitBtn");
const overlay = document.getElementById("loadingOverlay");
updatePurposeFields();
markRequiredFields();

form.addEventListener("submit", function (e) {
    const currentAge = parseInt(ageInput.value) || 0;
    if (currentAge < 18) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Age Restriction',
            text: 'You must be at least 18 years old to submit a request.',
            confirmButtonColor: '#8E1B1B'
        });
        return;
    }

    const payMethodField = document.getElementById('preferred_payment_method');
    const onlinePaymentMethodInput = document.getElementById('online_payment_method');

    if (payMethodField && payMethodField.disabled) {
        payMethodField.disabled = false;
    }

    if (payMethodField && payMethodField.value === 'online' && onlinePaymentMethodInput && !onlinePaymentMethodInput.value) {
        return;
    }

    const remainingSeconds = Number(window.requestCooldownRemaining || 0);
    if (remainingSeconds > 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Cooldown active',
            text: `Please wait ${remainingSeconds} seconds before creating another request.`,
            confirmButtonColor: '#8E1B1B'
        });
        return;
    }

    const activeDuplicate = hasActiveDuplicateRequest();
    if (activeDuplicate) {
        e.preventDefault();
        if (duplicateConfirmInput) duplicateConfirmInput.value = '0';
        if (replaceDuplicateInput) replaceDuplicateInput.value = '0';
        openDuplicateModal(activeDuplicate);
        return;
    }

    ensureSubmissionKey();
    submitBtn.disabled = true;
    submitBtn.innerText = 'Submitting...';
    if (overlay) overlay.style.display = 'flex';
});

if (duplicateRequestModal) {
    duplicateRequestModal.addEventListener('click', (event) => {
        if (event.target === duplicateRequestModal) {
            if (duplicateConfirmInput) duplicateConfirmInput.value = '0';
            if (replaceDuplicateInput) replaceDuplicateInput.value = '0';
            pendingDuplicateRequest = null;
            closeDuplicateModal();
        }
    });
}

const duplicateRequestReplaceBtn = document.getElementById('duplicateRequestReplaceBtn');
if (duplicateRequestReplaceBtn) {
    duplicateRequestReplaceBtn.addEventListener('click', () => {
        if (!pendingDuplicateRequest || String(pendingDuplicateRequest.status || '').toLowerCase() !== 'pending') {
            return;
        }
        if (requestIdInput) requestIdInput.value = String(pendingDuplicateRequest.id);
        if (duplicateConfirmInput) duplicateConfirmInput.value = '0';
        if (replaceDuplicateInput) replaceDuplicateInput.value = '1';
        closeDuplicateModal();
        ensureSubmissionKey();
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerText = 'Submitting...';
        }
        if (overlay) overlay.style.display = 'flex';
        form.submit();
    });
}

const duplicateRequestSeparateBtn = document.getElementById('duplicateRequestSeparateBtn');
if (duplicateRequestSeparateBtn) {
    duplicateRequestSeparateBtn.addEventListener('click', () => {
        if (duplicateConfirmInput) duplicateConfirmInput.value = '1';
        if (replaceDuplicateInput) replaceDuplicateInput.value = '0';
        closeDuplicateModal();
        ensureSubmissionKey();
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerText = 'Submitting...';
        }
        if (overlay) overlay.style.display = 'flex';
        form.submit();
    });
}

const duplicateRequestCancelBtn = document.getElementById('duplicateRequestCancelBtn');
if (duplicateRequestCancelBtn) {
    duplicateRequestCancelBtn.addEventListener('click', () => {
        if (duplicateConfirmInput) duplicateConfirmInput.value = '0';
        if (replaceDuplicateInput) replaceDuplicateInput.value = '0';
        pendingDuplicateRequest = null;
        closeDuplicateModal();
    });
}

const duplicateRequestCloseBtn = document.querySelector('[data-close-duplicate-modal]');
if (duplicateRequestCloseBtn) {
    duplicateRequestCloseBtn.addEventListener('click', () => {
        if (duplicateConfirmInput) duplicateConfirmInput.value = '0';
        if (replaceDuplicateInput) replaceDuplicateInput.value = '0';
        pendingDuplicateRequest = null;
        closeDuplicateModal();
    });
}

if (window.requestCooldownRemaining > 0) {
    const cooldownEndsAt = Date.now() + (window.requestCooldownRemaining * 1000);
    const countdown = setInterval(() => {
        const remaining = Math.max(0, Math.ceil((cooldownEndsAt - Date.now()) / 1000));
        updateCooldownState(remaining);
        window.requestCooldownRemaining = remaining;
        if (remaining <= 0) {
            clearInterval(countdown);
        }
    }, 1000);
    updateCooldownState(window.requestCooldownRemaining);
}

// Modal Small
document.addEventListener('DOMContentLoaded', () => {

    const reportModalEl = document.getElementById('reportModal');
    const reportModalBody = document.getElementById('reportDetails');

    const fieldsToShow = [
        { key: 'request_id', label: 'Request ID' },
        { key: 'full_name', label: 'Full Name' },
        { key: 'gender', label: 'Gender' },
        { key: 'age', label: 'Age' },
        { key: 'address', label: 'Full Address' },
        { key: 'contact_number', label: 'Contact Number' },
        { key: 'dob', label: 'Date of Birth' },
        { key: 'civil_status', label: 'Civil Status' },
        { key: 'years_residency', label: 'Year of Residency' },
        { key: 'document_type', label: 'Document' },
        { key: 'purpose', label: 'Purpose' },
        { key: 'status', label: 'Status' },
        { key: 'rejection_reason', label: 'Rejection Reason' },
        { key: 'created_at', label: 'Date Requested' },
    ];

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        if (isNaN(d)) return dateStr;
        return d.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    // BLOCK ROW CLICK unless a button inside the row was clicked
    document.querySelectorAll('.request-row').forEach(row => {
        row.addEventListener('click', (e) => {
            const clickedActionElement = e.target instanceof Element
                ? e.target.closest('button, a, input, select, textarea, label')
                : (e.target && e.target.parentElement ? e.target.parentElement.closest('button, a, input, select, textarea, label') : null);
            if (clickedActionElement) {
                return;
            }
            e.stopPropagation();
        });
    });

    // ONLY BUTTON CAN OPEN MODAL
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {

            const requestId = btn.getAttribute('data-id');

            const modal = new bootstrap.Modal(reportModalEl);
            reportModalBody.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 mb-0">Loading request...</p>
                </div>
            `;
            modal.show();

            // Fetch request data via AJAX
            fetch(buildAppUrl('action/get_request_details.php?request_id=' + encodeURIComponent(requestId)))
                .then(r => {
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
                        reportModalBody.innerHTML = '<p class="text-danger">Error: ' + (data?.error || 'Unknown error') + '</p>';
                        modal.show();
                        return;
                        return;
                    }

                    const displayID = `REQ${data.created_at.split(' ')[0].replace(/-/g, '')}${data.user_id}-${String(data.request_id).padStart(3, '0')}`;
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
    				const verifyUrl = (trackingCode && qrToken) ? buildAppUrl('/../cityofsantarosa/auth/verify?code=' + encodeURIComponent(data.tracking_code) + '&token=' + encodeURIComponent(data.qr_token)) : '';
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
                    const badgeClass = statusClass === 'approved' ? 'bg-success' : statusClass === 'rejected' ? 'bg-danger' : statusClass === 'completed' ? 'bg-primary' : 'bg-warning text-dark';
                    const requestedOn = formatDate(data.created_at);

                    reportModalBody.innerHTML = `
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
                                    <dd class="col-7">${paymentMethod === 'walk-in' ? 'Cash': paymentMethod}</dd>

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
                                            <p class="mt-2 mb-2" style="font-size:.82rem; color:#6b7280;">Scan to verify this request</p>
                                            <button type="button" id="downloadQrBtn" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-download"></i> Download QR
                                            </button>
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

                    // already shown via Bootstrap modal
                    // modal.show();

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

                        const downloadBtn = document.getElementById('downloadQrBtn');
                        if (downloadBtn) {
                            downloadBtn.addEventListener('click', function () {
                                const canvas = qrDiv.querySelector('canvas');
                                const img = qrDiv.querySelector('img');
                                const dataUrl = canvas ? canvas.toDataURL('image/png') : (img ? img.src : null);
                                if (!dataUrl) return;
                                const link = document.createElement('a');
                                link.href = dataUrl;
                                link.download = 'eRequestMo_QR_' + trackingCode + '.png';
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                            });
                        }
                    }

                    const attachmentSection = document.getElementById('attachmentSection');
                    if (attachmentSection) {
                        attachmentSection.innerHTML = '';

                        return fetch(buildAppUrl('action/get_attachments.php?request_id=' + encodeURIComponent(requestId)))
                            .then(r => {
                                if (!r.ok) {
                                    return r.text().then(text => {
                                        const msg = text ? text.trim().slice(0, 180) : '';
                                        throw new Error(`HTTP ${r.status}: ${r.statusText}${msg ? ` - ${msg}` : ''}`);
                                    });
                                }
                                return parseJsonResponse(r);
                            })
                            .then(attachments => {
                                if (!attachments || attachments.length === 0) return;

                                attachmentPreviewItems = [];
                                // Also clear the global array used by the new/edit-request
                                // upload form — otherwise it lingers for the rest of the
                                // session and always "wins" in openAttachmentPreview(),
                                // making every old request's preview show whatever file
                                // was last uploaded in the request form instead of its
                                // own attachments.
                                window.attachmentPreviewItems = [];
                                const authorizationLetter = attachments.find(att => att.prereq_name === '__authorization_letter__');
                                const relationshipValue = document.getElementById('requestRelationshipValue');
                                if (authorizationLetter && relationshipValue) {
                                    const fileUrl = buildAppUrl(authorizationLetter.file_path);
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
                                    const fileUrl = buildAppUrl(att.file_path);
                                    const fallbackJpg = fileUrl.replace(/\.png$/i, '.jpg');

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
                                </div>
                                <div class="attachment-controls">
                                    ${isImage ? `<button type="button" class="btn btn-sm btn-outline-primary" onclick="openAttachmentPreview(${attachmentPreviewItems.length - 1})">Preview</button>` : `<a href="${fileUrl}" target="_blank" class="btn btn-sm btn-outline-primary" style="text-decoration:none">View</a>`}
                                </div>
                            </div>`;
                                });
                                html += '</div>';
                                attachmentSection.innerHTML = html;
                            })
                            .catch(err => console.error('Failed to load attachments:', err));
                    }
                })
                .catch(err => {
                    console.error('Failed to fetch request:', err);
                    reportModalBody.innerHTML = '<p class="text-danger">Error loading request: ' + err.message + '</p>';
                    modal.show();
                });
        });
    });

});

// Delegated handler for deleting attachments (used in view and edit modals)
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
            return fetch(buildAppUrl('action/delete_attachment.php'), { method: 'POST', body: form })
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
                    title: 'Request Rejected',
                    text: 'The document was removed, so this request has been automatically rejected. You may submit a new request with the correct document.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    // Reload so the request list/details reflect the new status
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

const requestNameSuffixes = ['JR.', 'SR.', 'II', 'III', 'IV', 'V'];

function normalizeRequestNamePart(value) {
    return String(value || '').trim().replace(/\s+/g, ' ').toUpperCase();
}

function getRequestNameParts() {
    return {
        first: document.getElementById('requestFirstName'),
        middle: document.getElementById('requestMiddleName'),
        last: document.getElementById('requestLastName'),
        suffix: document.getElementById('requestSuffix'),
        fullName: document.getElementById('full_name'),
        preview: document.getElementById('fullNamePreview')
    };
}

function formatRequestName(firstName, middleName, lastName, suffix, addMiddleInitialPeriod = false) {
    const normalizedMiddleName = String(middleName || '').trim().replace(/\s+/g, ' ');
    const middle = /^(N\/A|NONE|-)$/i.test(normalizedMiddleName) ? '' : normalizedMiddleName;
    return [
        String(firstName || '').trim(),
        addMiddleInitialPeriod && /^\p{L}$/u.test(middle) ? `${middle}.` : middle,
        String(lastName || '').trim(),
        String(suffix || '').trim()
    ].filter(Boolean).join(' ').replace(/\s+/g, ' ').toUpperCase();
}

function updateRequestFullName() {
    const parts = getRequestNameParts();
    const fullName = formatRequestName(parts.first?.value, parts.middle?.value, parts.last?.value, parts.suffix?.value);
    if (parts.fullName) parts.fullName.value = fullName;
    if (parts.preview) {
        parts.preview.value = formatRequestName(
            parts.first?.value,
            parts.middle?.value,
            parts.last?.value,
            parts.suffix?.value,
            true
        );
    }
    return fullName;
}

function setRequestNameFromParts(nameParts) {
    const parts = getRequestNameParts();
    if (!parts.first || !parts.middle || !parts.last || !parts.suffix) return;
    parts.first.value = nameParts.firstname || '';
    parts.middle.value = /^(N\/A|NONE|-)$/i.test(String(nameParts.middlename || '').trim()) ? '' : (nameParts.middlename || '');
    parts.last.value = nameParts.lastname || '';
    parts.suffix.value = requestNameSuffixes.includes(String(nameParts.suffix || '').toUpperCase())
        ? (String(nameParts.suffix).toUpperCase() === 'JR.' ? 'Jr.' : String(nameParts.suffix).toUpperCase() === 'SR.' ? 'Sr.' : String(nameParts.suffix).toUpperCase())
        : '';
    updateRequestFullName();
}

function setRequestNameFromFullName(fullName) {
    const parts = getRequestNameParts();
    if (!parts.first || !parts.middle || !parts.last || !parts.suffix) return;
    const nameParts = String(fullName || '').trim().split(/\s+/).filter(Boolean);
    let suffix = '';
    if (nameParts.length && requestNameSuffixes.includes(nameParts[nameParts.length - 1].toUpperCase())) {
        suffix = nameParts.pop();
    }

    const firstName = nameParts.shift() || '';
    let middleName = '';
    if (nameParts.length > 1 && /^[\p{L}]\.?$/u.test(nameParts[0])) {
        middleName = nameParts.shift().replace(/\.$/, '');
    }
    setRequestNameFromParts({
        firstname: firstName,
        middlename: middleName,
        lastname: nameParts.join(' '),
        suffix
    });
}

// ✅ EDIT FUNCTION
document.addEventListener('DOMContentLoaded', () => {
    const editButtons = document.querySelectorAll('.edit-btn'); // Add this class to your edit buttons
    const requestModal = document.getElementById('requestModal');
    const form = document.getElementById('requestForm');
    const nameParts = getRequestNameParts();
    const customDocumentInput = document.getElementById('custom_document');
    window.editModeExistingAttachments = [];

    // Function to uppercase input value safely on mobile
    function toUpperCaseInput(input) {
        if (!input) return;

        let isComposing = false;

        input.addEventListener('compositionstart', () => {
            isComposing = true;
        });

        input.addEventListener('compositionend', () => {
            isComposing = false;
            input.value = input.value.toUpperCase();
        });

        input.addEventListener('input', () => {
            if (isComposing) {
                return;
            }

            const current = input.value;
            const upper = current.toUpperCase();
            if (current === upper) {
                return;
            }

            const selectionStart = input.selectionStart;
            const selectionEnd = input.selectionEnd;
            input.value = upper;

            if (selectionStart !== null && selectionEnd !== null) {
                input.setSelectionRange(selectionStart, selectionEnd);
            }
        });
    }

    function escapeRegExp(value) {
        return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function normalizeAddressForEdit(savedAddress, barangayValue) {
        let address = String(savedAddress || '').trim();
        if (!address) return '';

        const normalizedBarangay = String(barangayValue || '')
            .replace(/^Brgy\.?\s*/i, '')
            .trim();

        if (!normalizedBarangay) return address;

        address = address.replace(new RegExp(`\\s*,\\s*(barangay|brgy\\.?)\\s+${escapeRegExp(normalizedBarangay)}$`, 'i'), '');
        address = address.replace(new RegExp(`\\s+(barangay|brgy\\.?)\\s+${escapeRegExp(normalizedBarangay)}$`, 'i'), '');
        return address.replace(/^,\s*|,\s*$/g, '').trim();
    }

    // Apply to all required inputs
    [nameParts.first, nameParts.middle, nameParts.last].forEach(toUpperCaseInput);
    toUpperCaseInput(customDocumentInput);

    editButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation(); // prevent row click

            const remainingSeconds = Number(window.requestCooldownRemaining || 0);
            if (remainingSeconds > 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Cooldown active',
                    text: `Please wait ${remainingSeconds} seconds before changing or creating a request.`,
                    confirmButtonColor: '#8E1B1B'
                });
                return;
            }

            const requestId = btn.getAttribute('data-id');
            window.editModeAuthorizationLetter = null;
            window.editModeExistingAttachments = [];
            window.editModeOriginalRequestName = '';
            window.authorizationLetterSelectedForName = '';
            window.authorizationLetterAttachmentsLoading = true;
            window.syncRequestRelationshipFields?.();

            // Fetch request data via AJAX
            fetch(buildAppUrl('action/get_request_details.php?request_id=' + encodeURIComponent(requestId)))
                .then(r => {
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
                        Swal.fire({ icon: 'error', title: 'Error loading request', text: (data?.error || 'Unknown error') });
                        return;
                    }

                    // Open modal (use openModal so background scrolling is locked)
                    openModal(requestModal);
                    clearRequestAgainDocumentLock();
                    document.querySelector('#document_type option[data-request-again-option="true"]')?.remove();
                    form.document_type.disabled = false;
                    if (customDocumentInput) customDocumentInput.disabled = false;

                    // Fill form fields
                    const barangayPrefixInput = document.getElementById('addressBarangayPrefix');
                    const editedAddress = normalizeAddressForEdit(data.address, barangayPrefixInput?.value || '');

                    if (data.document_name_parts && typeof data.document_name_parts === 'object') {
                        setRequestNameFromParts(data.document_name_parts);
                    } else {
                        setRequestNameFromFullName(data.full_name || '');
                    }
                    window.editModeOriginalRequestName = data.full_name || '';
                    form.relationship.value = data.relationship || '';
                    form.dob.value = data.dob || '';
                    form.age.value = data.age || '';
                    form.address.value = editedAddress;
                    form.contact_number.value = data.contact_number || '';
                    form.civil_status.value = data.civil_status || '';
                    form.years_residency.value = data.years_residency || '';
                    form.document_type.value = data.document_type || '';
                    form.custom_document.value = data.custom_document || '';
                    setPurposeFromStoredValue(data.purpose || '');

                    // Sync "Use my registered" checkboxes based on loaded values
                    const phoneCheckbox = document.getElementById('useRegisteredNumber');
                    nameParts.first.dispatchEvent(new Event('input'));
                    if (phoneCheckbox) {
                        const loadedPhone = (data.contact_number || '').replace(/^\+?63/, '0');
                        const regPhoneNorm = (typeof registeredPhone !== 'undefined' ? registeredPhone : '').replace(/^\+?63/, '0').replace(/^63/, '0');
                        phoneCheckbox.checked = loadedPhone === regPhoneNorm && loadedPhone !== '';
                    }

                    // Show/hide custom document field
                    form.document_type.dispatchEvent(new Event('change'));

                    // Lock document type once a request exists — resident can no longer
                    // switch documents after submitting.
                    form.document_type.disabled = true;
                    if (customDocumentInput) customDocumentInput.disabled = true;

                    // ✅ Set the hidden request_id
                    form.request_id.value = data.request_id;

                    // ── Lock payment method only for paid online requests. Walk-in paid requests may still be converted.
                    const payMethodSelect = document.getElementById('preferred_payment_method');
                    const onlinePaymentMethodInput = document.getElementById('online_payment_method');
                    const oldRefInfo = document.getElementById('paidReferenceInfo');
                    if (oldRefInfo) oldRefInfo.remove();

                    if (payMethodSelect) {
                        const isPaid = (data.payment_status || '').toLowerCase() === 'paid';
                        const preferredPaymentMethod = data.preferred_payment_method || '';
                        payMethodSelect.value = preferredPaymentMethod || '';
                        payMethodSelect.disabled = (isPaid && preferredPaymentMethod === 'online');

                        if (onlinePaymentMethodInput) {
                            if (preferredPaymentMethod === 'online') {
                                const knownProviders = ['gcash', 'paymaya', 'paypal', 'card'];
                                onlinePaymentMethodInput.value = knownProviders.includes(data.payment_reference)
                                    ? data.payment_reference
                                    : '';
                            } else {
                                onlinePaymentMethodInput.value = '';
                            }
                        }

                        if (isPaid && preferredPaymentMethod === 'online') {
                            const refCode = data.payment_reference || '—';
                            const refDiv = document.createElement('div');
                            refDiv.id = 'paidReferenceInfo';
                            refDiv.style.cssText = 'margin-top:8px;padding:10px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:13px;color:#166534;';
                            refDiv.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`
                                + `<strong>Already Paid</strong> — Reference: <code style="background:#dcfce7;padding:2px 6px;border-radius:4px;font-weight:600;">${escapeHtml(refCode)}</code>`;
                            payMethodSelect.closest('.form-group').appendChild(refDiv);
                        }
                    }

                    fetch(buildAppUrl('action/get_attachments.php?request_id=' + encodeURIComponent(requestId)))
                        .then(r => {
                            if (!r.ok) {
                                return r.text().then(text => {
                                    const msg = text ? text.trim().slice(0, 180) : '';
                                    throw new Error(`HTTP ${r.status}: ${r.statusText}${msg ? ` - ${msg}` : ''}`);
                                });
                            }
                            return parseJsonResponse(r);
                        })
                        .then(attachments => {
                            const allAttachments = attachments || [];
                            window.editModeAuthorizationLetter = allAttachments.find(att => att.prereq_name === '__authorization_letter__') || null;
                            window.editModeExistingAttachments = allAttachments.filter(att => att.prereq_name !== '__authorization_letter__');
                            window.authorizationLetterAttachmentsLoading = false;
                            window.syncRequestRelationshipFields?.();
                            if (typeof window.fillPrereqUploadPreviews === 'function') {
                                window.fillPrereqUploadPreviews();
                            }
                        })
                        .catch(err => {
                            window.authorizationLetterAttachmentsLoading = false;
                            window.syncRequestRelationshipFields?.();
                            console.error('Failed to load existing attachments:', err);
                        });
                })
                .catch(err => {
                    console.error('Failed to fetch request:', err);
                    Swal.fire({ icon: 'error', title: 'Error loading request', text: err.message });
                });
        });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const documentSelect = document.getElementById('documentSelect');
    const statusSelect = document.getElementById('statusSelect');
    const dateSelect = document.getElementById('dateSelect');
    const loadMoreBtn = document.getElementById('loadMoreRequestsBtn');

    // Support both the new table id AND the old class name
    const tableBody = document.querySelector('#requestsTable tbody')
        || document.querySelector('.requests-table tbody');

    let mobileVisibleLimit = 5;
    const MOBILE_INCREMENT = 5;

    function isMobileViewport() {
        return window.innerWidth <= 768;
    }

    function showMessage(text) {
        const oldMsg = tableBody.querySelector('.no-results');
        if (oldMsg) oldMsg.remove();
        const noRow = document.createElement('tr');
        noRow.classList.add('no-results');
        const td = document.createElement('td');
        td.colSpan = 16;
        td.style.cssText = 'text-align:center;font-style:italic;padding:16px;';
        td.textContent = text;
        noRow.appendChild(td);
        tableBody.appendChild(noRow);
    }

    function applyMobileLimit() {
        if (!tableBody) return;

        const rows = Array.from(tableBody.querySelectorAll('tr'));
        const visibleRows = rows.filter(row => row.dataset.filtered === '1');

        if (!isMobileViewport()) {
            visibleRows.forEach(row => row.style.display = '');
            if (loadMoreBtn) loadMoreBtn.classList.add('d-none');
            return;
        }

        visibleRows.forEach((row, index) => {
            row.style.display = index < mobileVisibleLimit ? '' : 'none';
        });

        if (!loadMoreBtn) return;

        const remaining = Math.max(0, visibleRows.length - mobileVisibleLimit);
        if (remaining > 0) {
            loadMoreBtn.textContent = `Show ${Math.min(remaining, MOBILE_INCREMENT)} more requests`;
            loadMoreBtn.classList.remove('d-none');
        } else {
            loadMoreBtn.classList.add('d-none');
        }
    }

    function resetMobileLimit() {
        mobileVisibleLimit = MOBILE_INCREMENT;
    }

    function isSameCalendarDay(a, b) {
        return a.getFullYear() === b.getFullYear() &&
            a.getMonth() === b.getMonth() &&
            a.getDate() === b.getDate();
    }

    function isDateInRange(dateStr, range) {
        if (!dateStr) return true;
        const target = new Date(dateStr);
        if (isNaN(target)) return true;
        const today = new Date();

        switch (range) {
            case 'Today':
                return isSameCalendarDay(target, today);
            case 'Yesterday': {
                const yest = new Date(today);
                yest.setDate(today.getDate() - 1);
                return isSameCalendarDay(target, yest);
            }
            case 'Last 7 Days': {
                const d7 = new Date(today);
                d7.setDate(today.getDate() - 6);
                d7.setHours(0, 0, 0, 0);
                return target >= d7;
            }
            case 'This Month':
                return target.getMonth() === today.getMonth() &&
                    target.getFullYear() === today.getFullYear();
            default:
                return true;
        }
    }

    function applyFilters() {
        if (!tableBody) return;

        const docFilter = (documentSelect ? documentSelect.value : '').toLowerCase().trim();
        const statusFilter = (statusSelect ? statusSelect.value : '').toLowerCase().trim();
        const dateFilter = (dateSelect ? dateSelect.value : '').trim();

        const oldMsg = tableBody.querySelector('.no-results');
        if (oldMsg) oldMsg.remove();

        const rows = tableBody.querySelectorAll('tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const isDeleted = row.dataset.isDeleted === '1';
            const rowStatus = (row.dataset.status || '').toLowerCase();

            // ── Use data-doc attribute (new table) OR fall back to column index (old table)
            const rowDoc = (row.dataset.doc
                ? row.dataset.doc
                : (row.cells[10] ? row.cells[10].textContent : '')
            ).toLowerCase();

            // ── Use data-date attribute (new table) OR fall back to column index (old table)
            const rowDate = row.dataset.date
                || (row.cells[14] ? row.cells[14].textContent.trim() : '');

            let show = false;

            // Status filter
            if (statusFilter === 'deleted') {
                show = isDeleted;
            } else {
                show = !isDeleted && (statusFilter === '' || rowStatus.includes(statusFilter));
            }

            // Document filter
            if (show && docFilter && !rowDoc.includes(docFilter)) {
                show = false;
            }

            // Date filter
            if (show && dateFilter) {
                show = isDateInRange(rowDate, dateFilter);
            }

            row.dataset.filtered = show ? '1' : '0';
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        resetMobileLimit();
        applyMobileLimit();

        if (rows.length === 0) {
            showMessage('No requests yet.');
        } else if (visibleCount === 0) {
            showMessage('No matching requests found.');
        }
    }

    if (documentSelect) documentSelect.addEventListener('change', applyFilters);
    if (statusSelect) statusSelect.addEventListener('change', applyFilters);
    if (dateSelect) dateSelect.addEventListener('change', applyFilters);
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', () => {
            mobileVisibleLimit += MOBILE_INCREMENT;
            applyMobileLimit();
        });
    }
    window.addEventListener('resize', applyMobileLimit);

    applyFilters(); // Run on page load
});


const contactInput = document.getElementById('contact_number');

contactInput.addEventListener('input', () => {
    contactInput.value = contactInput.value.replace(/[^0-9+]/g, ''); // only digits and '+'
});

document.addEventListener("DOMContentLoaded", () => {
    const tabButtons = document.querySelectorAll("[data-setting-tab]");
    const profilePanel = document.getElementById("settings-profile");
    const logsPanel = document.getElementById("settings-logs");
    const logsContent = document.getElementById("logsContent");

    function showSettingTab(tab) {
        if (profilePanel) profilePanel.style.display = tab === "profile" ? "block" : "none";
        if (logsPanel) logsPanel.style.display = tab === "logs" ? "block" : "none";

        tabButtons.forEach((btn) => {
            const active = btn.dataset.settingTab === tab;
            btn.classList.toggle("active", active);
        });

        if (tab === "logs" && logsContent) {
            loadResidentLogs();
        }
    }

    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function loadResidentLogs() {
        if (!logsContent) return;

        logsContent.innerHTML = "<p class='mb-0'>Loading logs...</p>";

        fetch("../action/get_resident_logs")
            .then((res) => res.json())
            .then((data) => {
                if (!Array.isArray(data) || !data.length) {
                    logsContent.innerHTML = "<p class='mb-0 text-muted'>No login logs found.</p>";
                    return;
                }

                let html = '<div class="list-group list-group-flush">';
                data.forEach((log) => {
                    const statusClass = (log.status === "success") ? "bg-success-subtle text-success" : "bg-danger-subtle text-danger";
                    const statusLabel = (log.status === "success") ? "Success" : "Failed";
                    html += `
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-semibold">${escapeHtml(log.device || "Unknown Device")}</div>
                                    <div class="text-muted small">${escapeHtml(log.browser || "Unknown Browser")} • ${escapeHtml(log.ip_address || "Unknown")}</div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small">${escapeHtml(log.created_at_formatted || log.created_at || "Unknown")}</div>
                                    <span class="badge ${statusClass}">${escapeHtml(statusLabel)}</span>
                                </div>
                            </div>
                        </div>`;
                });
                html += "</div>";
                logsContent.innerHTML = html;
            })
            .catch(() => {
                logsContent.innerHTML = "<p class='mb-0 text-muted'>Unable to load login logs right now.</p>";
            });
    }

    tabButtons.forEach((btn) => {
        btn.addEventListener("click", () => showSettingTab(btn.dataset.settingTab));
    });

    showSettingTab("profile");
});

const resetBtn = document.getElementById("resetBtn");

if (resetBtn) {

    resetBtn.addEventListener("click", function () {

        if (!userEmail) {
            Swal.fire({
                icon: "error",
                title: "No Email Found",
                text: "Registered email not available."
            });
            return;
        }

        Swal.fire({
            title: "Sending reset email...",
            text: "Please wait",
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch("../auth/forgot_password", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                email: userEmail,
                user_id: userId
            })
        })
            .then(res => res.json())
            .then(data => {

                Swal.fire({
                    icon: data.success ? "success" : "error",
                    title: "Password Reset",
                    text: data.message
                });

            })
            .catch(err => {

                console.error(err);

                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Failed to send reset email."
                });

            });

    });

} else {
    console.error("resetBtn not found");
}

document.addEventListener("DOMContentLoaded", () => {

    const password = document.getElementById("new_password");
    if (!password) return;

    password.addEventListener("input", () => {
        const value = password.value;

        const strong =
            /[a-z]/.test(value) &&
            /[A-Z]/.test(value) &&
            /\d/.test(value) &&
            /[\W_]/.test(value) &&
            value.length >= 8;

        if (!strong) {
            password.style.border = "2px solid red";
        } else {
            password.style.border = "2px solid green";
        }
    });

});

document.addEventListener("DOMContentLoaded", () => {

    const logsModal = document.getElementById("logsModal");
    const logsContent = document.getElementById("logsContent");
    const openLogsBtn = document.getElementById("openLogsBtn");
    const closeLogsModal = document.getElementById("closeLogsModal");

    if (!logsModal || !logsContent || !openLogsBtn || !closeLogsModal) return;

    openLogsBtn.addEventListener("click", () => {
        openModal(logsModal);

        logsContent.innerHTML = "<p>Loading...</p>";

        fetch("../action/get_resident_logs")
            .then(res => res.json())
            .then(data => {
                if (!data.length) {
                    logsContent.innerHTML = "<p>No login logs found.</p>";
                    return;
                }

                let html = "<ul style='padding-left:18px;'>";

                data.forEach(log => {
                    html += `
                        <li style="margin-bottom:10px;">
                            <strong>${log.device}</strong><br>
                            Browser: ${log.browser || 'Unknown'}<br>
                            IP: ${log.ip_address || 'Unknown'}<br>
                            Time: ${log.created_at_formatted || log.created_at}
                        </li>
                    `;
                });

                html += "</ul>";

                logsContent.innerHTML = html;
            })
            .catch(() => {
                logsContent.innerHTML = "<p>Unable to load login logs right now.</p>";
            });
    });

    closeLogsModal.addEventListener("click", () => {
        closeModal(logsModal);
    });

    window.addEventListener("click", (e) => {
        if (e.target === logsModal) {
            closeModal(logsModal);
        }
    });
});

// ── Panel switcher ──
function showPanel(name, el) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.sidebar a').forEach(a => a.classList.remove('active'));
    const panel = document.getElementById('panel-' + name);
    if (panel) panel.classList.add('active');
    
    const targetLink = el || document.querySelector(`.sidebar a[onclick*="'${name}'"]`);
    if (targetLink) targetLink.classList.add('active');
    
    localStorage.setItem('resPanel', name);
}
window.showPanel = showPanel;

// ── Notice modal ──
document.addEventListener("DOMContentLoaded", () => {
    const noticeModal   = document.getElementById("noticeModal");
    const closeNoticeBtn = document.getElementById("closeNoticeBtn");
    const openNoticeBtns = document.querySelectorAll("#openNoticeBtn");

    if (noticeModal && closeNoticeBtn) {
        openNoticeBtns.forEach(btn => btn.addEventListener("click", () => {
            noticeModal.style.display = "flex";
        }));
        closeNoticeBtn.addEventListener("click", () => noticeModal.style.display = "none");
        window.addEventListener("click", e => { if (e.target === noticeModal) noticeModal.style.display = "none"; });
    }
});

// ── Request form payment and validation ──
document.addEventListener("DOMContentLoaded", () => {
    const requestForm = document.getElementById('requestForm');
    const requestIdField = document.getElementById('request_id');
    const paymentMethodSelect = document.getElementById('preferred_payment_method');
    const onlinePaymentMethodInput = document.getElementById('online_payment_method');
    const documentTypeSelect = document.getElementById('document_type');
    const customDocumentInput = document.getElementById('custom_document');
    const fees = typeof documentFees !== 'undefined' ? documentFees : {};
    const paidIds = typeof paidRequestIds !== 'undefined' ? paidRequestIds : [];

    if (requestForm && paymentMethodSelect && onlinePaymentMethodInput && documentTypeSelect) {
        requestForm.addEventListener('submit', function (event) {
            if (event.defaultPrevented) {
                return;
            }

            const selectedDocType = documentTypeSelect.value === 'Other'
                ? (customDocumentInput ? customDocumentInput.value.trim() || 'Other' : 'Other')
                : documentTypeSelect.value;
            const selectedFeeSettings = fees[selectedDocType] || {};
            const selectedPrice = Number(selectedFeeSettings.price ?? selectedFeeSettings.fee_amount ?? 0);
            const isFreeDocument = Boolean(selectedFeeSettings.is_free) || selectedPrice <= 0;

            if (isFreeDocument) {
                event.preventDefault();
                Swal.fire({
                    icon: 'info',
                    title: 'Free Document',
                    text: 'Wait for the message that your request is approved and completed. It is free, just walk-in.',
                    confirmButtonText: 'Continue'
                }).then((result) => {
                    if (result.isConfirmed) {
                        onlinePaymentMethodInput.value = '';
                        requestForm.submit();
                    }
                });
                return;
            }

            const currentRequestId = requestIdField ? Number(requestIdField.value) : 0;
            const isPaidRequest = paidIds.includes(currentRequestId);

            if (paymentMethodSelect.value !== 'online') {
                onlinePaymentMethodInput.value = '';
                return;
            }

            if (onlinePaymentMethodInput.value) {
                // Already selected an online payment provider, allow the form to submit.
                return;
            }

            // If the request is already paid, don't show payment modal
            if (isPaidRequest) {
                return;
            }

            event.preventDefault();
            Swal.fire({
                title: 'Proceed with online payment',
                input: 'select',
                inputOptions: {
                    gcash: 'GCash',
                    paymaya: 'Maya (PayMaya)',
                    paypal: 'PayPal',
                    card: 'Card'
                },
                inputPlaceholder: 'Select a payment method',
                showCancelButton: true,
                confirmButtonText: 'Continue',
                cancelButtonText: 'Cancel',
                allowOutsideClick: false,
                inputValidator: (value) => {
                    if (!value) {
                        return 'Please select a payment method.';
                    }
                    return null;
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    onlinePaymentMethodInput.value = result.value;
                    requestForm.submit();
                    return;
                }

                onlinePaymentMethodInput.value = '';
            });
        });
    }
});

// ── Autopopulating checkboxes ──
document.addEventListener("DOMContentLoaded", () => {
    const nameCheckbox = document.getElementById('useRegisteredName');
    const relationshipGroup = document.getElementById('relationshipGroup');
    const relationshipSelect = document.getElementById('relationship');
    const authorizationLetterGroup = document.getElementById('authorizationLetterGroup');
    const authorizationLetterInput = document.getElementById('authorizationLetter');
    const authorizationLetterExisting = document.getElementById('authorizationLetterExisting');
    const authorizationLetterSelectedFile = document.getElementById('authorizationLetterSelectedFile');
    const nameParts = getRequestNameParts();
    const nameInputs = [nameParts.first, nameParts.middle, nameParts.last, nameParts.suffix].filter(Boolean);
    const registeredParts = typeof registeredNameParts !== 'undefined' ? registeredNameParts : {};
    const registeredFormattedName = formatRequestName(
        registeredParts.firstname,
        registeredParts.middlename,
        registeredParts.lastname,
        registeredParts.suffix
    );

    function syncAuthorizationLetterField(isRegisteredName) {
        if (!authorizationLetterGroup || !authorizationLetterInput || !relationshipSelect) return;

        const shouldShow = !isRegisteredName && Boolean(relationshipSelect.value);
        const existingLetter = window.editModeAuthorizationLetter || null;
        const currentName = normalizeRequestNamePart(nameParts.fullName?.value);
        const existingLetterMatchesName = Boolean(existingLetter)
            && currentName === normalizeRequestNamePart(window.editModeOriginalRequestName || '');
        authorizationLetterGroup.classList.toggle('animated-field--hidden', !shouldShow);
        authorizationLetterGroup.setAttribute('aria-hidden', String(!shouldShow));
        authorizationLetterInput.disabled = !shouldShow;
        authorizationLetterInput.required = shouldShow && !existingLetterMatchesName && !window.authorizationLetterAttachmentsLoading;

        if (authorizationLetterExisting) {
            authorizationLetterExisting.textContent = shouldShow && existingLetter
                ? `${existingLetterMatchesName ? 'Current file' : 'Existing file for previous name'}: ${existingLetter.original_name || 'Authorization Letter'}`
                : '';
        }

        if (!shouldShow) {
            authorizationLetterInput.value = '';
            window.authorizationLetterSelectedForName = '';
            if (authorizationLetterSelectedFile) authorizationLetterSelectedFile.textContent = '';
        } else if (
            authorizationLetterInput.value
            && window.authorizationLetterSelectedForName
            && currentName !== window.authorizationLetterSelectedForName
        ) {
            authorizationLetterInput.value = '';
            window.authorizationLetterSelectedForName = '';
            if (authorizationLetterSelectedFile) authorizationLetterSelectedFile.textContent = '';
        }
    }

    function syncRelationshipField() {
        if (!nameParts.fullName || !relationshipGroup || !relationshipSelect) return;

        const isRegisteredName = normalizeRequestNamePart(nameParts.fullName.value) === normalizeRequestNamePart(registeredFormattedName)
            && Boolean(nameParts.fullName.value);
        relationshipGroup.classList.toggle('relationship-field--hidden', isRegisteredName);
        relationshipGroup.setAttribute('aria-hidden', String(isRegisteredName));
        relationshipSelect.disabled = isRegisteredName;
        relationshipSelect.required = !isRegisteredName;
        markRequiredFields();

        if (isRegisteredName) {
            relationshipSelect.value = '';
        }
        syncAuthorizationLetterField(isRegisteredName);
    }
    window.syncRequestRelationshipFields = syncRelationshipField;

    function syncNameFields() {
        const fullName = updateRequestFullName();
        if (nameCheckbox) {
            nameCheckbox.checked = Boolean(fullName) && normalizeRequestNamePart(fullName) === normalizeRequestNamePart(registeredFormattedName);
        }
        syncRelationshipField();
    }

    nameInputs.forEach(input => {
        input.addEventListener('input', syncNameFields);
        input.addEventListener('change', syncNameFields);
    });
    relationshipSelect?.addEventListener('change', () => {
        const isRegisteredName = Boolean(nameParts.fullName?.value)
            && normalizeRequestNamePart(nameParts.fullName.value) === normalizeRequestNamePart(registeredFormattedName);
        syncAuthorizationLetterField(isRegisteredName);
    });
    authorizationLetterInput?.addEventListener('change', () => {
        if (!authorizationLetterSelectedFile) return;
        const selectedFile = authorizationLetterInput.files?.[0];
        window.authorizationLetterSelectedForName = selectedFile
            ? normalizeRequestNamePart(nameParts.fullName?.value)
            : '';
        authorizationLetterSelectedFile.textContent = selectedFile
            ? `Selected file: ${selectedFile.name}`
            : '';
    });

    // Helper: normalise a phone number to 09XXXXXXXXX for comparison
    function normalisePhone(num) {
        num = (num || '').trim();
        if (num.startsWith('+63')) num = '0' + num.slice(3);
        else if (num.startsWith('63'))  num = '0' + num.slice(2);
        return num;
    }

    if (nameCheckbox) {
        nameCheckbox.addEventListener('change', function() {
            if (this.checked) {
                setRequestNameFromParts(registeredParts);
            } else {
                setRequestNameFromParts({ firstname: '', middlename: '', lastname: '', suffix: '' });
            }
            syncNameFields();
        });
    }
    syncNameFields();
    syncRelationshipField();

    const phoneCheckbox = document.getElementById('useRegisteredNumber');
    const phoneInput = document.getElementById('contact_number');
    const regPhone = typeof registeredPhone !== 'undefined' ? registeredPhone : '';
    const regPhoneNorm = normalisePhone(regPhone);

    if (phoneCheckbox && phoneInput) {
        phoneCheckbox.addEventListener('change', function() {
            if (this.checked) {
                let num = regPhone;
                if (num.startsWith('639')) {
                    num = '0' + num.slice(2);
                }
                phoneInput.value = num;
            } else {
                phoneInput.value = '';
            }
        });

        // Auto-check/uncheck as the user types
        phoneInput.addEventListener('input', function() {
            const matches = normalisePhone(this.value) === regPhoneNorm && this.value.trim() !== '';
            phoneCheckbox.checked = matches;
        });
    }
});

// ── Mobile hamburger sidebar toggle ──
document.addEventListener("DOMContentLoaded", () => {
    const btn     = document.getElementById('hamburgerBtn');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!btn || !sidebar || !overlay) return;

    function openSidebar()  { sidebar.classList.add('open');    overlay.classList.add('open');    document.body.style.overflow = 'hidden'; }
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('open'); document.body.style.overflow = ''; }

    btn.addEventListener('click', openSidebar);
    overlay.addEventListener('click', closeSidebar);

    // Close sidebar when a nav link is clicked on mobile
    sidebar.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
        if (window.innerWidth <= 768) closeSidebar();
    }));
});

// ── User Dropdown positioning ──
function positionUserDropdown(dropdown, trigger) {
    if (!dropdown || !trigger) return;
    dropdown.classList.add('show');

    const rect = trigger.getBoundingClientRect();
    const dropdownWidth = dropdown.offsetWidth || 260;
    const dropdownHeight = dropdown.offsetHeight || 120;

    // Mobile: position inside the sidebar to avoid fixed overlay and scrolling
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

    // Desktop: fixed overlay centered below trigger
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
window.toggleUserDropdown = toggleUserDropdown;

// ── Dropdown and Clock Setup ──
document.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('resPanel') || 'requests';
    const link = document.querySelector(`.sidebar a[onclick*="'${saved}'"]`);
    if (document.getElementById('panel-' + saved)) {
        showPanel(saved, link);
    }
    updateSidebarClock();

    // Handle user dropdown close on outside click
    document.addEventListener('click', (e) => {
        const dropdown = document.getElementById('userDropdown');
        const container = document.querySelector('.user-dropdown-container');
        if (dropdown && container && !container.contains(e.target)) {
            dropdown.classList.remove('show');
            dropdown.style.left = '';
            dropdown.style.top = '';
        }
    });

    window.addEventListener('resize', () => {
        const dropdown = document.getElementById('userDropdown');
        const trigger = document.querySelector('.sidebar-user');
        if (dropdown && dropdown.classList.contains('show') && trigger) {
            positionUserDropdown(dropdown, trigger);
        }
    });

    // ── Session Keep-Alive Heartbeat ──
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

function updateSidebarClock() {
    const el = document.getElementById('sidebarClock');
    if (!el) return;
    const now = new Date();
    const date = now.toLocaleDateString(undefined, { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
    const time = now.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    el.textContent = `${date} ${time}`;
}
setInterval(updateSidebarClock, 1000);

// ======================
// REQUEST REFUND (rejected + paid requests)
// ======================
document.addEventListener('click', function (e) {
    const target = e.target;
    const btn = (target instanceof Element ? target.closest('.request-refund-btn') : null)
        || (target && target.parentElement ? target.parentElement.closest('.request-refund-btn') : null);
    if (!btn) return;
    e.preventDefault();

    const id = btn.dataset.id;
    if (!id) return;

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.content : '';

    const submitRefundRequest = () => {
        const form = new FormData();
        form.append('id', id);
        if (csrfToken) {
            form.append('csrf_token', csrfToken);
        }
        return fetch(buildAppUrl('action/request_refund.php'), { method: 'POST', body: form })
            .then(parseJsonResponse)
            .then(res => {
                if (!res || !res.success) {
                    throw new Error(res?.error || 'Failed to request refund');
                }
                return res;
            });
    };

    if (typeof Swal === 'undefined') {
        if (!window.confirm('Request a refund?\nThe barangay will review your request before the refund is processed.')) {
            return;
        }

        submitRefundRequest()
            .then(() => {
                window.alert('Refund requested. The barangay will review your request soon.');
                window.location.reload();
            })
            .catch(err => {
                window.alert(err.message || 'Request failed.');
            });

        return;
    }

    Swal.fire({
        title: 'Request a refund?',
        text: 'The barangay will review your request before the refund is processed.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Request Refund',
        showLoaderOnConfirm: true,
        preConfirm: () => submitRefundRequest().catch(err => {
            Swal.showValidationMessage(err.message || 'Request failed');
        })
    }).then(result => {
        if (result.isConfirmed && result.value && result.value.success) {
            Swal.fire({
                icon: 'success',
                title: 'Refund Requested',
                text: 'The barangay will review your request soon.',
                confirmButtonText: 'OK'
            }).then(() => window.location.reload());
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const docTypeSelect = document.getElementById('document_type');
    const docPriceDisplay = document.getElementById('docPriceDisplay');

    function updateDocPriceDisplay() {
        if (!docTypeSelect || !docPriceDisplay) return;
        const selected = docTypeSelect.value;
        const setting = documentFees[selected];

        if (!selected || !setting) {
            docPriceDisplay.style.display = 'none';
            return;
        }

        const price = parseFloat(setting.price ?? setting.fee_amount ?? 0);
        const isFree = setting.is_free || price <= 0;

        docPriceDisplay.style.display = 'block';
        if (isFree) {
            docPriceDisplay.style.color = '#166534';
            docPriceDisplay.textContent = 'This document is FREE.';
        } else {
            docPriceDisplay.style.color = '#92400e';
            docPriceDisplay.textContent = 'Fee: PHP ' + price.toFixed(2);
        }
    }

    if (docTypeSelect) {
        docTypeSelect.addEventListener('change', updateDocPriceDisplay);
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const docTypeSelect = document.getElementById('document_type');
    const pwdGroup = document.getElementById('pwdSeniorGroup');
    const pwdCheckbox = document.getElementById('pwd_senior_declared');
    const pwdUploadWrap = document.getElementById('pwdSeniorUploadWrap');
    const pwdFileInput = document.getElementById('pwd_senior_id_file');

    function updatePwdSeniorVisibility() {
        if (!docTypeSelect || !pwdGroup) return;
        const setting = documentFees[docTypeSelect.value];
        const enabled = !!(setting && setting.pwd_senior_enabled);

        pwdGroup.style.display = enabled ? 'block' : 'none';
        if (!enabled) {
            pwdCheckbox.checked = false;
            pwdUploadWrap.style.display = 'none';
            if (pwdFileInput) pwdFileInput.required = false;
        }
    }

    if (pwdCheckbox) {
        pwdCheckbox.addEventListener('change', function () {
            pwdUploadWrap.style.display = this.checked ? 'block' : 'none';
            if (pwdFileInput) pwdFileInput.required = this.checked;
        });
    }

    if (docTypeSelect) {
        docTypeSelect.addEventListener('change', updatePwdSeniorVisibility);
    }
});