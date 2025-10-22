<?php
// File: quotation_view_modal.php
?>
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewModalLabel">Chi tiết báo giá</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
      </div>
      <div class="modal-body" id="viewModalBody">
        <div id="quotationDetail">
          <p><strong>Đối tác:</strong> <span id="q_partner"></span></p>
          <p><strong>Tiêu đề:</strong> <span id="q_title"></span></p>
          <p><strong>Ngày báo giá:</strong> <span id="q_date"></span></p>
          <p><strong>Ghi chú:</strong> <span id="q_notes"></span></p>
          <p><strong>Chi tiết:</strong></p>
          <ul id="q_details"></ul>
          <p><strong>Tệp đính kèm:</strong></p>
          <div id="q_files"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
      </div>
    </div>
  </div>
</div>
