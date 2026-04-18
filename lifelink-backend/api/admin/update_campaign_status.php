<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$campaign_id = isset($data['campaign_id']) ? (int)$data['campaign_id'] : 0;
$new_status  = isset($data['status']) ? trim($data['status']) : '';

$allowed = ['Upcoming', 'Active', 'Completed'];
if (!$campaign_id || !in_array($new_status, $allowed)) {
    echo json_encode(["status" => "error", "message" => "Invalid campaign_id or status"]);
    exit;
}

$stmt = $conn->prepare("UPDATE campaigns SET status = ? WHERE campaign_id = ?");
$stmt->bind_param("si", $new_status, $campaign_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(["status" => "success", "message" => "Campaign status updated to $new_status"]);
} else {
    echo json_encode(["status" => "error", "message" => "Campaign not found or no change"]);
}

$stmt->close();
$conn->close();
?>
