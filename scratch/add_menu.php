<?php
require_once __DIR__ . '/../includes/init.php';
try {
    $pdo->exec("UPDATE menus SET sort_order = sort_order + 1 WHERE parent_id = 2 AND sort_order >= 8");
    $stmt = $pdo->prepare("INSERT INTO menus (name, name_en, url, icon, parent_id, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Điều phối giao hàng', 'Delivery Dispatcher', 'delivery_dispatcher.php', 'bi-truck', 2, 8]);
    echo "Menu added successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
