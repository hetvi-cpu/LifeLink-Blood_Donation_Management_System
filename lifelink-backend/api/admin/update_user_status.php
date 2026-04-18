<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$data    = json_decode(file_get_contents("php://input"), true);
$user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;

// Accept both 'status' and 'user_status' keys for compatibility
$new_status = trim($data['user_status'] ?? $data['status'] ?? '');

// Now includes 'Hold' (original DB value) and new migration values
$allowed_statuses = ['Active', 'Hold', 'Inactive', 'Suspended'];

if (!$user_id || !in_array($new_status, $allowed_statuses)) {
    echo json_encode(["status" => "error", "message" => "Invalid user_id or status. Allowed: " . implode(', ', $allowed_statuses)]);
    exit;
}

$stmt = $conn->prepare("UPDATE users SET user_status = ?, updated_at = NOW() WHERE user_id = ? AND role_id = 2");
$stmt->bind_param("si", $new_status, $user_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "User status updated to $new_status"]);
    } else {
        echo json_encode(["status" => "error", "message" => "User not found or already has this status"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => $conn->error]);
}

$stmt->close();
$conn->close();
?>
