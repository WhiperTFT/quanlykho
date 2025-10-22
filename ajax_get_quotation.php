<?php
require_once __DIR__ . '/includes/init.php';
header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(["error" => "ID không hợp lệ"]);
    exit;
}

$id = intval($_GET['id']);
$conn = new mysqli("localhost", "root", "", "db_quanlykho");

$stmt = $conn->prepare("SELECT q.*, p.name as partner_name FROM quotations q JOIN partners p ON q.partner_id = p.id WHERE q.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        "id" => $row['id'],
        "partner_name" => $row['partner_name'],
        "title" => $row['title'],
        "quotation_date" => $row['quotation_date'],
        "notes" => $row['notes'],
        "details" => json_decode($row['details_json'], true),
        "files" => json_decode($row['files_json'], true),
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["error" => "Không tìm thấy báo giá"]);
}

$stmt->close();
$conn->close();
