<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$request_id = isset($data['request_id']) ? (int)$data['request_id'] : 0;
$new_status = isset($data['status']) ? trim($data['status']) : '';

$allowed = ['Pending', 'Approved', 'Fulfilled', 'Cancelled'];
if (!$request_id || !in_array($new_status, $allowed)) {
    echo json_encode(["status" => "error", "message" => "Invalid request_id or status"]);
    exit;
}

$stmt = $conn->prepare("UPDATE blood_requests SET status = ? WHERE request_id = ?");
$stmt->bind_param("si", $new_status, $request_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(["status" => "success", "message" => "Request status updated to $new_status"]);
} else {
    echo json_encode(["status" => "error", "message" => "Request not found or no change"]);
}

$stmt->close();
$conn->close();
?>
