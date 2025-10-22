<?php
// File: process/update_delivery_date.php

declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';


header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $order_id = $_POST['order_id'] ?? null;
    $delivery_date = $_POST['delivery_date'] ?? null;

    if (!$order_id) {
        throw new Exception('Thiếu mã đơn hàng.');
    }

    // Nếu delivery_date rỗng, hiểu là "Chưa giao", cập nhật thành NULL
    if (is_null($delivery_date) || trim($delivery_date) === '') {
        $stmt = $pdo->prepare("UPDATE sales_orders SET expected_delivery_date = NULL WHERE id = :id");
        $stmt->execute([':id' => $order_id]);

        $response['success'] = true;
        $response['message'] = 'Đã cập nhật trạng thái "Chưa giao".';
    } else {
        // Ngày hợp lệ
        $dateObj = DateTime::createFromFormat('Y-m-d', $delivery_date);
        if (!$dateObj) {
            throw new Exception('Ngày giao hàng không hợp lệ.');
        }

        $stmt = $pdo->prepare("UPDATE sales_orders SET expected_delivery_date = :delivery_date WHERE id = :id");
        $stmt->execute([
            ':delivery_date' => $dateObj->format('Y-m-d'),
            ':id' => $order_id
        ]);

        $response['success'] = true;
        $response['message'] = 'Đã cập nhật ngày giao hàng.';
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(400);
}

echo json_encode($response);
