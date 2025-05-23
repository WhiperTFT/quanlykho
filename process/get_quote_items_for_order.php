<?php
// process/get_quote_items_for_order.php
require_once __DIR__ . '/../includes/init.php'; 
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => ($lang['failed_to_fetch_quote_details'] ?? 'Failed to fetch quote details.')];
$quote_number_for_response = null;
$items_for_response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quote_id'])) {
    $quoteId = filter_var($_POST['quote_id'], FILTER_VALIDATE_INT);

    if ($quoteId) {
        try {
            // 1. Lấy Số báo giá (quote_number)
            $stmt_quote_info = $pdo->prepare("SELECT quote_number FROM sales_quotes WHERE id = :quote_id");
            $stmt_quote_info->bindParam(':quote_id', $quoteId, PDO::PARAM_INT);
            $stmt_quote_info->execute();
            $quote_data = $stmt_quote_info->fetch(PDO::FETCH_ASSOC);

            if (!$quote_data || !isset($quote_data['quote_number'])) {
                throw new Exception($lang['quote_not_found_or_no_number'] ?? 'Quote not found or quote number is missing.');
            }
            $quote_number_for_response = $quote_data['quote_number'];

            
            $stmt_items = $pdo->prepare("SELECT 
                                            COALESCE(sqi.category_snapshot, '') AS category_snapshot, 
                                            COALESCE(sqi.product_name_snapshot, '') AS product_name_snapshot, 
                                            COALESCE(sqi.unit_snapshot, '') AS unit_snapshot, 
                                            sqi.quantity
                                            
                                        FROM sales_quote_details sqi 
                                        WHERE sqi.quote_id = :quote_id");
            $stmt_items->bindParam(':quote_id', $quoteId, PDO::PARAM_INT);
            $stmt_items->execute();
            $items_for_response = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            
            $response = [
                'status' => 'success',
                'quote_number' => $quote_number_for_response, // Chỉ trả về quote_number
                'items' => $items_for_response,
                'message' => ($lang['quote_data_fetched_successfully'] ?? 'Quote data fetched successfully.')
            ];

        } catch (Exception $e) {
            error_log("Error in get_quote_items_for_order.php: " . $e->getMessage());
            $response['message'] = $e->getMessage();
        }
    } else {
        $response['message'] = ($lang['invalid_quote_id'] ?? 'Invalid Quote ID provided.');
    }
} else {
    $response['message'] = ($lang['invalid_request_method'] ?? 'Invalid request method.');
}

echo json_encode($response);
exit;
?>