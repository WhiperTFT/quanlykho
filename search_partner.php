<?php
$mysqli = new mysqli("localhost", "root", "", "db_quanlykho");
$term = $_GET['term'] ?? '';
$sql = "SELECT name FROM partners WHERE name LIKE CONCAT('%', ?, '%') ORDER BY name LIMIT 10";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $term);
$stmt->execute();
$result = $stmt->get_result();
$suggestions = [];
while ($row = $result->fetch_assoc()) $suggestions[] = $row['name'];
header("Content-Type: application/json; charset=UTF-8");
echo json_encode($suggestions, JSON_UNESCAPED_UNICODE);
?>
