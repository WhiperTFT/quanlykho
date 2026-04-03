<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/logging.php';

try {
    $quoteId = 37; // Quotation with 'accepted' status
    $newStatus = 'sent';
    $currentStatus = 'accepted';

    echo "Testing status update for ID: $quoteId, New Status: $newStatus\n";

    $pdo->beginTransaction();
    
    $sql = "UPDATE sales_quotes SET status = :new_status, updated_at = NOW() WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':new_status' => $newStatus, ':id' => $quoteId]);
    $affected = $stmt->rowCount();
    echo "Affected rows: $affected\n";

    if ($affected >= 0) {
        echo "Attempting to write log...\n";
        write_user_log('UPDATE', 'sales_quote', "Debug: Cập nhật trạng thái báo giá #$quoteId từ $currentStatus → $newStatus", ['quote_id' => $quoteId, 'new_status' => $newStatus], 'info');
        echo "Log written successfully!\n";
    }

    $pdo->rollBack();
    echo "Transaction rolled back (test complete).\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "TRACE: " . $e->getTraceAsString() . "\n";
}
