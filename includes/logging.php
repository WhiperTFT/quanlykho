<?php
// includes/logging.php
if (!function_exists('write_user_log')) {
    function write_user_log(PDO $pdo, int $user_id, string $action, string $description = ''): void {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

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
        } catch (Exception $e) {
            error_log("Failed to write user log: " . $e->getMessage());
        }
    }
}
 