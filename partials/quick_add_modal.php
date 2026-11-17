<?php
/*
 * Quick Add (Multiple): modal markup only.
 * Included from the employee dashboard page. The behavior lives in js/quick_add.js.
 * Needs $available_document_types (already defined on the page).
 */
$qaReturnTo = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
?>
<!-- QUICK ADD (MULTIPLE) MODAL -->
<div class="modal fade" id="quickAddModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <form class="modal-content" method="POST" action="../action/manual_request_bulk" id="quickAddForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="return_to" value="<?= htmlspecialchars($qaReturnTo) ?>">

      <div class="modal-header">
        <h5 class="modal-title">Quick Add: Multiple Manual Requests</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <p class="text-muted" style="font-size:13px;">
          Use this to encode several walk-in requests at once, for example after a period without internet.
          Set <strong>Date Requested</strong> to the day the resident actually came in.
          Full resident details are not collected here.
        </p>

        <div class="table-responsive">
          <table class="table table-sm align-middle" id="quickAddTable">
            <thead class="table-dark">
              <tr>
                <th style="min-width:170px;">Full Name</th>
                <th style="min-width:170px;">Document Type</th>
                <th style="min-width:150px;">Purpose</th>
                <th style="min-width:110px;">Amount</th>
                <th style="min-width:70px;" class="text-center">PWD/Sr</th>
                <th style="min-width:150px;">Date Requested</th>
                <th style="width:40px;"></th>
              </tr>
            </thead>
            <tbody id="quickAddRows"></tbody>
          </table>
        </div>

        <button type="button" class="btn btn-sm btn-outline-dark" id="quickAddRowBtn">+ Add Row</button>

        <!-- Row template: never submitted directly -->
        <template id="quickAddRowTemplate">
          <tr class="quick-add-row">
            <td><input type="text" name="q_full_name[]" class="form-control form-control-sm" maxlength="150" style="text-transform:uppercase" required></td>
            <td>
              <select name="q_document_type[]" class="form-select form-select-sm q-doc-type" required>
                <option value="">-- Select --</option>
                <?php foreach ($available_document_types as $docType): ?>
                  <option value="<?= htmlspecialchars($docType) ?>"><?= htmlspecialchars($docType) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="text" name="q_purpose[]" class="form-control form-control-sm q-purpose" maxlength="255" style="text-transform:uppercase" required></td>
            <td><input type="number" name="q_payment_amount[]" class="form-control form-control-sm q-amount" min="0" step="0.01" required></td>
            <td class="text-center">
              <input type="checkbox" class="form-check-input q-pwd" title="Apply 20% PWD/Senior discount">
              <!-- Always submitted, so the arrays stay 1:1 per row -->
              <input type="hidden" name="q_pwd_flag[]" class="q-pwd-flag" value="0">
            </td>
            <td><input type="date" name="q_date_requested[]" class="form-control form-control-sm q-date" max="<?= date('Y-m-d') ?>" required></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger q-remove-row" title="Remove row">&times;</button></td>
          </tr>
        </template>
      </div>

      <div class="modal-footer">
        <span class="text-muted me-auto" id="quickAddCount" style="font-size:13px;">0 request(s) ready</span>
        <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-outline-primary" id="quickAddSubmitBtn">Save All Requests</button>
      </div>
    </form>
  </div>
</div>