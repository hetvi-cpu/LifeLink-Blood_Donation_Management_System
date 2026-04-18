<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$alert_id = isset($data['alert_id']) ? (int)$data['alert_id'] : 0;

if (!$alert_id) {
    echo json_encode(["status" => "error", "message" => "alert_id is required"]);
    exit;
}

$stmt = $conn->prepare("UPDATE emergency_alerts SET is_fulfilled = 1 WHERE alert_id = ?");
$stmt->bind_param("i", $alert_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(["status" => "success", "message" => "Alert marked as fulfilled"]);
} else {
    echo json_encode(["status" => "error", "message" => "Alert not found or already fulfilled"]);
}

$stmt->close();
$conn->close();
?>
