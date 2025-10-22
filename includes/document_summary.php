<?php
// File: includes/document_summary.php (Sửa Layout - Dùng row/col)
if (!isset($lang)) { $lang = []; }
?>
<div class="row mt-4 document-summary-top">
    <div class="col-md-7 order-md-1 mb-3">
         <label for="notes" class="form-label fw-bold"><?= $lang['notes'] ?? 'Notes' ?></label>
         <textarea class="form-control resizable-textarea" id="notes" name="notes" rows="5" placeholder="<?= $lang['notes_placeholder'] ?? 'Enter any notes here...' ?>"></textarea>
    </div>
    <div class="col-md-5 order-md-2 mb-3">
        <div class="card border-light shadow-sm">
            <div class="card-body p-3">

                <div class="d-flex justify-content-between mb-2">
                    <span><?= $lang['sub_total'] ?? 'Sub Total' ?>:</span>
                    <span id="summary-subtotal" class="fw-bold">0.00</span>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                <label for="summary-vat-rate" class="form-label mb-0 me-2"><?= $lang['vat_rate'] ?? 'VAT Rate (%)' ?>:</label>
                <div class="input-group input-group-sm" style="max-width: 110px;">
                <input type="number" class="form-control text-end" id="summary-vat-rate" name="vat_rate" value="10" min="0" max="100" step="any" required>
                <span class="input-group-text">%</span>
                </div>
                </div>

                <div class="d-flex justify-content-between mb-2">
                    <span><?= $lang['vat_total'] ?? 'VAT Total' ?>:</span>
                    <span id="summary-vattotal" class="fw-bold">0.00</span>
                </div>

                <hr class="my-2">

                <div class="d-flex justify-content-between fw-bold fs-5 text-danger">
                    <span><?= $lang['grand_total'] ?? 'Grand Total'?>:</span>
                    <span id="summary-grandtotal">0.00</span>
                </div>

                <input type="hidden" name="sub_total" id="input-subtotal" value="0">
                <input type="hidden" name="vat_rate_hidden" id="input-vatrate" value="10"> <input type="hidden" name="vat_total" id="input-vattotal" value="0">
                <input type="hidden" name="grand_total" id="input-grandtotal" value="0">

            </div> </div> </div> </div>
    
    <div class="row mt-4">
        <div class="col-md-6 text-center">
            <strong><?= $lang['seller'] ?? 'Seller' ?></strong>
        </div>
        <div class="col-md-6 text-center">
            <strong><?= $lang['buyer'] ?? 'Buyer' ?></strong>
        </div>
    </div>
    <div class="row mt-2" style="margin-bottom: 20px;"> <div class="col-md-6"></div>
        <div class="col-md-6 text-center">
            <img id="buyer-signature" src="" alt="" style="display: none;">
        </div>
    </div>
    <style>
    .resizable-textarea { resize: vertical; min-height: 100px; }
    .signatures { min-height: 120px; } /* Giảm chiều cao khu chữ ký */
    .form-control-plaintext.border-bottom { border-bottom: 1px solid #dee2e6 !important; padding-bottom: 0.1rem; min-height: calc(1.5em + 0.5rem + 2px); /* Giống chiều cao input */}
    fieldset { border-color: #dee2e6; }
    legend { font-size: 0.9em; color: #6c757d; }
</style>