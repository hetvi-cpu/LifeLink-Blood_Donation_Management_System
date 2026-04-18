<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';

$where_sql = '';
if (!empty($search)) {
    $where_sql = "WHERE hospital_name LIKE '%$search%' OR address LIKE '%$search%'";
}

$result = $conn->query("SELECT * FROM hospital_details $where_sql ORDER BY hospital_name ASC");

if (!$result) {
    echo json_encode(["status" => "error", "message" => $conn->error]);
    exit;
}

$hospitals = [];
while ($row = $result->fetch_assoc()) {
    $hospitals[] = $row;
}

echo json_encode(["status" => "success", "data" => $hospitals]);
$conn->close();
?>
