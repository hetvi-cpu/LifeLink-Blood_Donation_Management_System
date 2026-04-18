<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;

if (!$user_id) {
    echo json_encode(["status" => "error", "message" => "user_id is required"]);
    exit;
}

// Fetch firebase_uid first so we can clean up related records
$result = $conn->query("SELECT firebase_uid FROM users WHERE user_id = $user_id AND role_id = 2");
if (!$result || $result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "User not found"]);
    exit;
}
$firebase_uid = $result->fetch_assoc()['firebase_uid'];
$safe_uid = $conn->real_escape_string($firebase_uid);

// Cascade delete related records
$conn->query("DELETE FROM donors              WHERE firebase_uid = '$safe_uid'");
$conn->query("DELETE FROM blood_requests      WHERE firebase_uid = '$safe_uid'");
$conn->query("DELETE FROM donor_requests      WHERE requester_uid = '$safe_uid' OR donor_uid = '$safe_uid'");
$conn->query("DELETE FROM campaign_registrations WHERE firebase_uid = '$safe_uid'");
$conn->query("DELETE FROM emergency_alerts    WHERE user_id = $user_id");
$conn->query("DELETE FROM notifications       WHERE user_id = $user_id");
$conn->query("DELETE FROM activity_history    WHERE user_id = $user_id");
$conn->query("DELETE FROM health_records      WHERE user_id = $user_id");
$conn->query("DELETE FROM feedback            WHERE user_id = $user_id");

$stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role_id = 2");
$stmt->bind_param("i", $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(["status" => "success", "message" => "User deleted successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to delete user"]);
}

$stmt->close();
$conn->close();
?>
