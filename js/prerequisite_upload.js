/*
 * ─────────────────────────────────────────────────────────────
 *  PREREQUISITE UPLOAD — Dynamic upload fields for resident request form
 *  When a document type is selected, fetches prerequisites via AJAX
 *  and shows required upload fields.
 * ─────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    const docSelect  = document.getElementById('document_type');
    const submitBtn  = document.getElementById('submitBtn');
    const form       = document.getElementById('requestForm');

    if (!docSelect || !form) return;

    // Create the container for prerequisites
    let container = document.getElementById('prereqContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'prereqContainer';
        const docGroup = docSelect.closest('.form-group');
        const customDiv = document.getElementById('customDocumentDiv');

        if (customDiv && customDiv.parentNode) {
            customDiv.parentNode.insertBefore(container, customDiv);
        } else if (docGroup && docGroup.parentNode) {
            docGroup.parentNode.insertBefore(container, docGroup.nextSibling);
        }
    }

    let currentPrereqs = [];
    const removedAttachmentIds = new Set();

    function normalizePrereqName(name) {
        return String(name || '').trim().toLowerCase();
    }

    function getExistingAttachmentMap() {
        const attachments = Array.isArray(window.editModeExistingAttachments) ? window.editModeExistingAttachments : [];
        return attachments.reduce((map, att) => {
            if (removedAttachmentIds.has(Number(att.id))) return map;
            const key = normalizePrereqName(att.prereq_name);
            if (!key) return map;
            if (!map[key]) map[key] = [];
            map[key].push(att);
            return map;
        }, {});
    }

    function getExistingAttachmentForPrereq(prereqName) {
        const map = getExistingAttachmentMap();
        return map[normalizePrereqName(prereqName)]?.[0] || null;
    }

    function clearPreview(previewEl, input) {
        if (previewEl) {
            const idx = previewEl.dataset.previewIdx !== undefined ? Number(previewEl.dataset.previewIdx) : NaN;
            if (!Number.isNaN(idx) && Array.isArray(window.attachmentPreviewItems) && window.attachmentPreviewItems[idx]) {
                const item = window.attachmentPreviewItems[idx];
                if (item && typeof item.src === 'string' && item.src.startsWith('blob:')) {
                    try { URL.revokeObjectURL(item.src); } catch (e) { }
                    if (Array.isArray(window._registeredBlobUrls)) {
                        const urlPos = window._registeredBlobUrls.indexOf(item.src);
                        if (urlPos !== -1) window._registeredBlobUrls.splice(urlPos, 1);
                    }
                }
                // Null out the slot (not splice — splicing would shift the
                // indices other prereq slots have already stored) so it no
                // longer counts as an active item in the preview modal.
                window.attachmentPreviewItems[idx] = null;
            }
            delete previewEl.dataset.previewIdx;
            previewEl.innerHTML = '';
        }
        if (input) input.value = '';
    }

    function removeExistingAttachment(attachmentId, previewEl, input) {
        if (!attachmentId) return;
        removedAttachmentIds.add(Number(attachmentId));
        syncRemovedAttachmentIdsField();
        clearPreview(previewEl, input);
        validateUploads();
    }

    // Keep a hidden form field in sync with removedAttachmentIds so the
    // backend actually finds out which existing attachments were removed —
    // without this, removals only ever lived in browser memory and never
    // reached the server, so the "old" file kept being saved.
    function syncRemovedAttachmentIdsField() {
        let field = document.getElementById('removed_attachment_ids');
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.id = 'removed_attachment_ids';
            field.name = 'removed_attachment_ids';
            form.appendChild(field);
        }
        field.value = Array.from(removedAttachmentIds).join(',');
    }

    function attachPreviewRemoveButton(previewEl, attachmentId, input) {
        const button = previewEl.querySelector('.preview-remove-btn');
        if (!button) return;
        button.addEventListener('click', () => {
            if (attachmentId) {
                removeExistingAttachment(attachmentId, previewEl, input);
            } else {
                clearPreview(previewEl, input);
                validateUploads();
            }
        });
    }

    function attachPreviewActionButton(previewEl, action) {
        const button = previewEl.querySelector('.preview-action-btn');
        if (!button) return;
        button.addEventListener('click', action);
    }

    function registerLocalFilePreview(file, existingIdx) {
        const isImage = file.type && file.type.startsWith('image/');
        const url = URL.createObjectURL(file);

        window.attachmentPreviewItems = Array.isArray(window.attachmentPreviewItems) ? window.attachmentPreviewItems : [];
        window._registeredBlobUrls = Array.isArray(window._registeredBlobUrls) ? window._registeredBlobUrls : [];

        // If this slot already had a local file registered (i.e. resident is
        // replacing an already-selected file), revoke its old blob URL and
        // overwrite the same array slot instead of appending a new one —
        // otherwise the old file lingers in attachmentPreviewItems and can
        // still show up when navigating the preview modal.
        if (Number.isInteger(existingIdx) && window.attachmentPreviewItems[existingIdx]) {
            const oldItem = window.attachmentPreviewItems[existingIdx];
            if (oldItem && typeof oldItem.src === 'string' && oldItem.src.startsWith('blob:')) {
                try { URL.revokeObjectURL(oldItem.src); } catch (e) { }
                const urlPos = window._registeredBlobUrls.indexOf(oldItem.src);
                if (urlPos !== -1) window._registeredBlobUrls.splice(urlPos, 1);
            }
            window.attachmentPreviewItems[existingIdx] = { title: '', originalName: file.name, src: url, isImage: !!isImage };
            window._registeredBlobUrls.push(url);
            return existingIdx;
        }

        window._registeredBlobUrls.push(url);
        window.attachmentPreviewItems.push({ title: '', originalName: file.name, src: url, isImage: !!isImage });
        return window.attachmentPreviewItems.length - 1;
    }

    function openExistingAttachmentPreview(attachment) {
        if (!attachment) return;
        const fileUrl = (typeof window.buildAppUrl === 'function') ? window.buildAppUrl(attachment.file_path || '') : '../' + (attachment.file_path || '');
        const index = registerExistingAttachmentPreview(attachment);
        if (index !== null && typeof window.openAttachmentPreview === 'function') {
            window.openAttachmentPreview(index);
            return;
        }

        if (typeof window.openFileModal === 'function') {
            window.openFileModal(fileUrl, attachment.prereq_name || '', attachment.original_name || '');
            return;
        }

        window.open(fileUrl, '_blank');
    }

    function registerExistingAttachmentPreview(attachment) {
        if (!attachment) return null;

        const fileUrl = (typeof window.buildAppUrl === 'function') ? window.buildAppUrl(attachment.file_path || '') : '../' + (attachment.file_path || '');
        const isImage = String(attachment.file_path || '').match(/\.(jpg|jpeg|png|webp)$/i) ? true : false;
        window.attachmentPreviewItems = Array.isArray(window.attachmentPreviewItems) ? window.attachmentPreviewItems : [];
        const existingIndex = window.attachmentPreviewItems.findIndex(item => item.src === fileUrl && item.originalName === attachment.original_name);
        if (existingIndex !== -1) return existingIndex;

        window.attachmentPreviewItems.push({
            title: attachment.prereq_name,
            originalName: attachment.original_name,
            src: fileUrl,
            isImage: isImage
        });
        return window.attachmentPreviewItems.length - 1;
    }

    function renderExistingAttachmentPreview(previewEl, attachment, input) {
        if (!previewEl || !attachment) return;
        const isImage = String(attachment.file_path || '').match(/\.(jpg|jpeg|png|webp)$/i);
        const sizeKB = attachment.file_size ? Math.max(1, Math.round(attachment.file_size / 1024)) : null;
        const fileUrl = (typeof window.buildAppUrl === 'function') ? window.buildAppUrl(attachment.file_path || '') : '../' + (attachment.file_path || '');

        const label = `✅ ${escapeHtml(attachment.original_name || attachment.file_path)}${sizeKB ? ` (${sizeKB}KB)` : ''} • already uploaded`;
        previewEl.innerHTML = `
            <div class="prereq-thumb">
                ${isImage ? `<img src="${fileUrl}" alt="Preview">` : `<span class="prereq-pdf-icon">📄</span>`}
                <span>${label}</span>
                <button type="button" class="preview-action-btn">Preview</button>
                <button type="button" class="preview-remove-btn" data-attachment-id="${attachment.id}">×</button>
            </div>
        `;

        attachPreviewActionButton(previewEl, () => openExistingAttachmentPreview(attachment));
        attachPreviewRemoveButton(previewEl, attachment.id, input);
    }

    function fillPrereqUploadPreviews() {
        const attachments = Array.isArray(window.editModeExistingAttachments) ? window.editModeExistingAttachments : [];
        if (!attachments.length || !currentPrereqs.length) {
            validateUploads();
            return;
        }

        currentPrereqs.forEach((p, idx) => {
            const previewEl = document.getElementById('preview_' + idx);
            const fileInput = document.getElementById('prereq_file_' + idx);
            const existing = getExistingAttachmentForPrereq(p.prereq_name);
            if (!previewEl || !existing) return;
            if (fileInput && fileInput.files && fileInput.files.length > 0) return;
            renderExistingAttachmentPreview(previewEl, existing, fileInput);
        });

        validateUploads();
    }

    window.fillPrereqUploadPreviews = fillPrereqUploadPreviews;

    // Listen for document type changes
    docSelect.addEventListener('change', () => {
        const doc = docSelect.value;
        container.innerHTML = '';
        currentPrereqs = [];

        if (!doc || doc === '') {
            validateUploads();
            return;
        }

        const prereqUrl = typeof window.buildAppUrl === 'function' 
            ? window.buildAppUrl('action/get_prerequisites?doc=' + encodeURIComponent(doc))
            : '../action/get_prerequisites?doc=' + encodeURIComponent(doc);

        fetch(prereqUrl)
            .then(r => r.json())
            .then(prereqs => {
                currentPrereqs = prereqs;

                if (prereqs.length === 0) {
                    validateUploads();
                    return;
                }

                // Build upload UI
                const wrapper = document.createElement('div');
                wrapper.className = 'prereq-section';
                wrapper.innerHTML = `
                    <div class="prereq-header">
                        <span class="prereq-icon">⚠️</span>
                        <strong>This document requires the following:</strong>
                    </div>
                    <div class="prereq-note" style="margin-bottom:10px;">
                        <small class="text-muted">Please upload all marked files before submitting your request.</small>
                    </div>
                `;

                prereqs.forEach((p, idx) => {
                    const reqLabel = p.is_required == 1 ? ' <span class="text-danger">*</span>' : ' <span class="text-muted">(optional)</span>';
                    const fieldName = 'prereq_file_' + idx;

                    const row = document.createElement('div');
                    row.className = 'prereq-row';
                    row.innerHTML = `
                        <label class="prereq-label">
                            📎 ${escapeHtml(p.prereq_name)}${reqLabel}
                        </label>
                        <input type="hidden" name="prereq_name_${idx}" value="${escapeHtml(p.prereq_name)}">
                        <input type="hidden" name="prereq_required_${idx}" value="${p.is_required}">
                        <div class="prereq-file-wrap">
                            <input type="file"
                                   name="${fieldName}"
                                   id="${fieldName}"
                                   accept="image/jpeg,image/png,image/webp,application/pdf"
                                   class="prereq-file-input"
                                   data-required="${p.is_required}"
                                   data-idx="${idx}">
                            <div class="prereq-preview" id="preview_${idx}"></div>
                        </div>
                        <small class="text-muted">JPG, PNG, or PDF • Max 5MB</small>
                    `;
                    wrapper.appendChild(row);

                    // Listen for file selection
                    setTimeout(() => {
                        const input = document.getElementById(fieldName);
                        if (input) {
                            input.addEventListener('change', () => {
                                handlePreview(input, idx);
                                validateUploads();
                            });
                        }
                    }, 50);
                });

                // Hidden field to tell PHP how many prerequisites exist
                const countInput = document.createElement('input');
                countInput.type = 'hidden';
                countInput.name = 'prereq_count';
                countInput.value = prereqs.length;
                wrapper.appendChild(countInput);

                container.appendChild(wrapper);
                validateUploads();
                fillPrereqUploadPreviews();
            })
            .catch(() => {
                validateUploads();
            });
    });

    // Validate that all required uploads have files, including previously uploaded attachments
    function validateUploads() {
        if (!submitBtn) return;

        const requiredInputs = container.querySelectorAll('input[type="file"][data-required="1"]');
        let allFilled = true;

        requiredInputs.forEach(inp => {
            if (inp.files && inp.files.length > 0) {
                return;
            }

            const idx = inp.dataset.idx;
            const nameInput = container.querySelector(`input[name="prereq_name_${idx}"]`);
            const prereqName = nameInput ? nameInput.value : '';
            const existing = getExistingAttachmentForPrereq(prereqName);

            if (!existing) {
                allFilled = false;
            }
        });

        // Don't disable if there are no prerequisites at all
        if (requiredInputs.length === 0) {
            submitBtn.disabled = false;
            submitBtn.title = '';
            return;
        }

        submitBtn.disabled = !allFilled;
        submitBtn.title = allFilled ? '' : 'Please upload all required prerequisite documents';
    }

    // Show a preview of the selected file
    function handlePreview(input, idx) {
        const preview = document.getElementById('preview_' + idx);
        if (!preview) return;

        if (!input.files || input.files.length === 0) {
            preview.innerHTML = '';
            return;
        }

        const file = input.files[0];

        // Remember the previously-registered index for this slot (if any) so
        // that a replaced file overwrites the old entry in place instead of
        // leaving it lingering in the shared preview array.
        const priorIdx = preview.dataset.previewIdx !== undefined ? Number(preview.dataset.previewIdx) : undefined;

        // Validate size
        if (file.size > 5 * 1024 * 1024) {
            preview.innerHTML = '<span class="text-danger">⚠️ File too large (max 5MB)</span>';
            input.value = '';
            return;
        }

        const sizeKB = (file.size / 1024).toFixed(0);

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const registeredIdx = registerLocalFilePreview(file, priorIdx);
                preview.dataset.previewIdx = String(registeredIdx);
                preview.innerHTML = `
                    <div class="prereq-thumb">
                        <img src="${e.target.result}" alt="Preview">
                        <span>✅ ${escapeHtml(file.name)} (${sizeKB}KB)</span>
                        <button type="button" class="preview-action-btn">Preview</button>
                        <button type="button" class="preview-remove-btn">×</button>
                    </div>
                `;
                attachPreviewActionButton(preview, () => window.openAttachmentPreview(registeredIdx));
                attachPreviewRemoveButton(preview, null, input);
            };
            reader.readAsDataURL(file);
        } else {
            const registeredIdx = registerLocalFilePreview(file, priorIdx);
            preview.dataset.previewIdx = String(registeredIdx);
            preview.innerHTML = `
                <div class="prereq-thumb">
                    <span class="prereq-pdf-icon">📄</span>
                    <span>✅ ${escapeHtml(file.name)} (${sizeKB}KB)</span>
                    <button type="button" class="preview-action-btn">Preview</button>
                    <button type="button" class="preview-remove-btn">×</button>
                </div>
            `;
            attachPreviewActionButton(preview, () => window.openAttachmentPreview(registeredIdx));
            attachPreviewRemoveButton(preview, null, input);
        }
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // Make form use multipart encoding when prerequisites exist
    form.addEventListener('submit', () => {
        if (currentPrereqs.length > 0) {
            form.enctype = 'multipart/form-data';
        }
    });

    // Set multipart by default (safe for forms with or without files)
    form.setAttribute('enctype', 'multipart/form-data');

})();