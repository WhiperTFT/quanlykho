<?php
require_once __DIR__ . '/../includes/init.php';
try {
    $pdo->exec("ALTER TABLE sales_quote_details ADD COLUMN supplier_id INT(10) UNSIGNED NULL AFTER product_id");
    echo "Column supplier_id added to sales_quote_details successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
