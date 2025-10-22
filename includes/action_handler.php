<?php
// File: includes/action_handler.php
declare(strict_types=1);

if (!function_exists('execute_action')) {
    /**
     * Hàm trung gian để xử lý các hành động (thêm, sửa, xóa) một cách tự động.
     *
     * @param PDO $pdo Đối tượng kết nối PDO.
     * @param array $config Mảng cấu hình cho hành động.
     * @param array $request_data Dữ liệu từ request (thường là $_POST hoặc $_GET).
     */
    function execute_action(PDO $pdo, array $config, array $request_data) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        try {
            // 1. Tự động kiểm tra quyền
            if (isset($config['permission']) && !has_permission($config['permission'])) {
                throw new RuntimeException('Bạn không có quyền thực hiện hành động này.', 403);
            }

            // 2. Tự động bắt đầu giao dịch an toàn
            $pdo->beginTransaction();

            // 3. Thực thi logic chính do bạn định nghĩa
            $result = $config['logic']($pdo, $request_data);
            $target_id = $result['id'] ?? null;
            $message = $result['message'] ?? 'Hành động đã được thực hiện thành công.';
            
            // 4. Tự động ghi log
            if (isset($config['log_action']) && isset($config['log_type'])) {
                log_activity($pdo, $config['log_action'], $config['log_type'], $target_id, $request_data);
            }

            // 5. Tự động commit giao dịch
            $pdo->commit();

            // 6. Tự động trả về JSON thành công
            echo json_encode([
                'success' => true, 
                'message' => $message,
                'data' => $result
            ]);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Database Error in execute_action: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu.']);
        } catch (RuntimeException | InvalidArgumentException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $code = $e->getCode();
            http_response_code(is_int($code) && $code >= 400 ? $code : 400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Unexpected Error in execute_action: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống không mong muốn.']);
        }
        exit;
    }
}
// -------- HÀM HỖ TRỢ SALES QUOTES --------
if (!function_exists('get_active_sales_quotes_for_linking')) {
    function get_active_sales_quotes_for_linking(PDO $pdo): array {
        if (!$pdo) return [];
        try {
            $sql = "SELECT sq.id, sq.quote_number, sq.quote_date, p.name as customer_name, sq.status
                    FROM sales_quotes sq
                    LEFT JOIN partners p ON sq.customer_id = p.id AND p.type = 'customer' 
                    WHERE sq.status IN ('sent', 'accepted') 
                    ORDER BY sq.id DESC";
            $stmt = $pdo->query($sql);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException $e) {
            return [];
        }
    }
}