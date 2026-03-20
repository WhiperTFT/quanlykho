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
            $user_id = (int)($args[1] ?? 0);
            $action = (string)($args[2] ?? 'UNKNOWN');
            $description = (string)($args[3] ?? '');
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

            $stmt = $pdo->prepare("
                INSERT INTO user_logs (user_id, action, module, log_type, description, data, ip_address, user_agent)
                VALUES (:user_id, :action, :module, :log_type, :description, :data, :ip, :agent)
            ");
            $stmt->execute([
                ':user_id' => $user_id,
                ':action' => $action,
                ':module' => $module,
                ':log_type' => $log_type,
                ':description' => $description,
                ':data' => $data,
                ':ip' => $ip,
                ':agent' => $agent
            ]);
        } catch (Exception $e) {
            // Graceful Check: if failure is due to missing new columns ('42S22' Column not found), fallback safely
            if ($e->getCode() == '42S22') {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_logs (user_id, action, description, ip_address, user_agent)
                        VALUES (:user_id, :action, :description, :ip, :agent)
                    ");
                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':action' => $action,
                        ':description' => $description,
                        ':ip' => $ip,
                        ':agent' => $agent
                    ]);
                } catch(Exception $ex) {
                    error_log("Failed to fallback write user log: " . $ex->getMessage());
                }
            } else {
                error_log("Failed to write user log: " . $e->getMessage());
            }
        }
    }
}
?>