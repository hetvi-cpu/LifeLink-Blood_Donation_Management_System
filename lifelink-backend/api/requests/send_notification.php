<?php
/**
 * send_notification.php — FIXED
 * Bug #8: Success message now correctly references donor_name (not requester_name)
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

include '../../config/db_config.php';
require_once '../../notifications/notification_bridge.php';

$data = json_decode(file_get_contents("php://input"), true);

$requester_uid = isset($data['requester_uid']) ? trim($data['requester_uid']) : null;
$donor_uid     = isset($data['donor_uid'])     ? trim($data['donor_uid'])     : null;
$blood_group   = isset($data['blood_group'])   ? trim($data['blood_group'])   : null;
$pincode       = isset($data['pincode'])       ? trim($data['pincode'])       : null;

if (!$requester_uid || !$donor_uid || !$blood_group) {
    echo json_encode(["status" => "error", "message" => "Required data missing (UIDs or Blood Group)."]);
    exit;
}

// Look up requester name
$stmtR = $conn->prepare("SELECT name FROM users WHERE firebase_uid = ?");
$stmtR->bind_param("s", $requester_uid);
$stmtR->execute();
$requesterRow   = $stmtR->get_result()->fetch_assoc();
$stmtR->close();
$requester_name = $requesterRow['name'] ?? 'Someone';

// Look up donor's FCM token + user_id + name
$stmtD = $conn->prepare("SELECT user_id, fcm_token, name FROM users WHERE firebase_uid = ?");
$stmtD->bind_param("s", $donor_uid);
$stmtD->execute();
$donorRow        = $stmtD->get_result()->fetch_assoc();
$stmtD->close();
$donor_fcm_token = $donorRow['fcm_token'] ?? null;
$donor_db_id     = $donorRow['user_id']   ?? null;
$donor_name      = $donorRow['name']      ?? 'the donor'; // Bug #8 fix: capture donor name

// Find active blood_request_id
$stmtBR = $conn->prepare(
    "SELECT request_id FROM blood_requests
     WHERE firebase_uid = ? AND blood_group = ? AND status = 'Pending'
     ORDER BY created_at DESC LIMIT 1"
);
$stmtBR->bind_param("ss", $requester_uid, $blood_group);
$stmtBR->execute();
$brRow            = $stmtBR->get_result()->fetch_assoc();
$stmtBR->close();
$blood_request_id = $brRow['request_id'] ?? null;

// Insert donor_request row
$stmtIns = $conn->prepare(
    "INSERT INTO donor_requests (requester_uid, donor_uid, blood_group, pincode, status, blood_request_id)
     VALUES (?, ?, ?, ?, 'Pending', ?)"
);
$stmtIns->bind_param("ssssi", $requester_uid, $donor_uid, $blood_group, $pincode, $blood_request_id);

if (!$stmtIns->execute()) {
    echo json_encode(["status" => "error", "message" => "DB Error: " . $stmtIns->error]);
    $conn->close();
    exit;
}
$new_donor_request_id = $conn->insert_id;
$stmtIns->close();

// Save in-app notification for the DONOR
if ($donor_db_id) {
    $notif_title = "🆘 Blood Request Received";
    $notif_msg   = "$requester_name urgently needs $blood_group blood. Can you help?";
    $stmtN = $conn->prepare(
        "INSERT INTO notifications (user_id, firebase_uid, title, message, type, priority, status)
         VALUES (?, ?, ?, ?, 'new_request_nearby', 'high', 'Unread')"
    );
    $stmtN->bind_param("isss", $donor_db_id, $donor_uid, $notif_title, $notif_msg);
    $stmtN->execute();
    $stmtN->close();
}

// Fire Socket.io + FCM via Node bridge
try {
    LifeLinkNotify::newRequestNearby(
        [['uid' => $donor_uid, 'fcmToken' => $donor_fcm_token]],
        $blood_group,
        $pincode ?? 'your area',
        $requester_name,
        $blood_request_id ?? $new_donor_request_id
    );
} catch (Exception $e) {
    error_log("[send_notification] Bridge error: " . $e->getMessage());
}

// Bug #8 fix: message now correctly says donor_name received the request
echo json_encode([
    "status"  => "success",
    "message" => "Donation request sent to $donor_name! They have been notified.",
]);

$conn->close();
?>