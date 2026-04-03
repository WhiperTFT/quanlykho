<?php
// includes/logging.php

if (!function_exists('write_user_log')) {
    /**
     * Standardized Enterprise Logging
     * Signature 1: write_user_log(PDO $pdo, int $user_id, string $action, string $description = '') (Legacy)
     * Signature 2: write_user_log(string $action, string $module, string $description = '', string|array $data = [], string $log_type = 'info') (Modern)
     */
    function write_user_log(...$args) {
        if (!isset($GLOBALS['pdo'])) return;
        $pdo = $GLOBALS['pdo'];

        // Determine if it's the old signature by checking if the first argument is PDO
        if (count($args) > 0 && $args[0] instanceof PDO) {
            $pdo = $args[0];
            // Nếu tham số thứ 2 không phải là số (int), có khả năng là ACTION luôn (Legacy v2)
            if (isset($args[1]) && !is_numeric($args[1])) {
                $user_id = (int)($_SESSION['user_id'] ?? 0);
                $action = (string)$args[1];
                $description = (string)($args[2] ?? '');
            } else {
                $user_id = (int)($args[1] ?? 0);
                $action = (string)($args[2] ?? 'UNKNOWN');
                $description = (string)($args[3] ?? '');
            }
            $module = 'system';
            $data = null;
            $log_type = 'info';
        } else {
            // New modern signature usage
            $user_id = (int)($_SESSION['user_id'] ?? 0);
            $action = (string)($args[0] ?? 'UNKNOWN');
            $module = (string)($args[1] ?? 'system');
            $description = (string)($args[2] ?? '');
            
            $raw_data = $args[3] ?? null;
            $data = (is_array($raw_data) || is_object($raw_data)) ? json_encode($raw_data, JSON_UNESCAPED_UNICODE) : $raw_data;
            if (empty($data)) $data = null;

            $log_type = (string)($args[4] ?? 'info');
        }

        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            // Get device_id (Ưu tiên Cookie > Session > POST/GET/JSON)
            $device_id = $_COOKIE['device_id'] ?? ($_SESSION['device_id'] ?? 'unknown');

            if ($device_id === 'unknown') {
                if (isset($_POST['device_id'])) {
                    $device_id = $_POST['device_id'];
                } elseif (isset($_GET['device_id'])) {
                    $device_id = $_GET['device_id'];
                } else {
                    $input = file_get_contents('php://input');
                    if ($input) {
                        $json = json_decode($input, true);
                        if (isset($json['device_id']) && !empty($json['device_id'])) {
                            $device_id = $json['device_id'];
                        }
                    }
                }
            }

            // Check in passed data array as last resort
            if ($device_id === 'unknown' && isset($raw_data) && is_array($raw_data) && isset($raw_data['device_id'])) {
                $device_id = (string)$raw_data['device_id'];
            }
            
            // Lưu vào Session nếu tìm thấy để reuse
            if ($device_id !== 'unknown' && empty($_SESSION['device_id'])) {
                $_SESSION['device_id'] = $device_id;
            }

            $stmt = $pdo->prepare("
                INSERT INTO user_logs (user_id, action, module, log_type, description, data, ip_address, user_agent, device_id)
                VALUES (:user_id, :action, :module, :log_type, :description, :data, :ip, :agent, :device_id)
            ");
            $stmt->execute([
                ':user_id' => $user_id,
                ':action' => $action,
                ':module' => $module,
                ':log_type' => $log_type,
                ':description' => $description,
                ':data' => $data,
                ':ip' => $ip,
                ':agent' => $agent,
                ':device_id' => $device_id
            ]);
        } catch (Exception $e) {
            // Fallback: Nếu bảng đã có column device_id thì vẫn cố ghi vào
            try {
                $check_sql = "
                    INSERT INTO user_logs (user_id, action, description, ip_address, user_agent, device_id)
                    VALUES (:user_id, :action, :description, :ip, :agent, :device_id)
                ";
                $stmt = $pdo->prepare($check_sql);
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':action' => $action,
                    ':description' => $description,
                    ':ip' => $ip,
                    ':agent' => $agent,
                    ':device_id' => $device_id ?? 'unknown'
                ]);
            } catch(Exception $ex) {
                // Final fallback if everything fails (no device_id column)
                try {
                    $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user_id, $action, $description, $ip]);
                } catch(Exception $ex2) {}
            }
        }
    }
}
?>