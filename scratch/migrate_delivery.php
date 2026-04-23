<?php
require_once __DIR__ . '/../includes/init.php';
try {
    // Drop existing tables that might conflict
    $pdo->exec("DROP TABLE IF EXISTS delivery_trip_orders");
    $pdo->exec("DROP TABLE IF EXISTS delivery_items");
    $pdo->exec("DROP TABLE IF EXISTS delivery_trips");

    // 1. Create dispatcher_trips (using a fresh name to avoid any future conflicts with old schema)
    $pdo->exec("CREATE TABLE dispatcher_trips (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        trip_number VARCHAR(50) UNIQUE NOT NULL,
        trip_date DATE NOT NULL,
        driver_id INT UNSIGNED NOT NULL,
        vehicle_plate VARCHAR(20) DEFAULT NULL,
        base_freight_cost DECIMAL(15,2) DEFAULT 0,
        extra_costs DECIMAL(15,2) DEFAULT 0,
        notes TEXT,
        status ENUM('draft', 'scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (driver_id),
        INDEX (trip_date)
    ) ENGINE=InnoDB;");

    // 2. Create dispatcher_trip_orders to link many orders to one trip
    $pdo->exec("CREATE TABLE dispatcher_trip_orders (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        trip_id INT UNSIGNED NOT NULL,
        order_id INT UNSIGNED NOT NULL,
        delivery_status ENUM('pending', 'delivered', 'failed') DEFAULT 'pending',
        notes TEXT,
        FOREIGN KEY (trip_id) REFERENCES dispatcher_trips(id) ON DELETE CASCADE,
        FOREIGN KEY (order_id) REFERENCES sales_orders(id)
    ) ENGINE=InnoDB;");

    echo "Tables dispatcher_trips and dispatcher_trip_orders created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
