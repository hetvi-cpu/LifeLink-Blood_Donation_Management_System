<?php
// v9 — stores admin_id + contact_number
ob_start();

require_once '../../config/db_config.php';
require_once '../../notifications/notification_bridge.php';

function json_exit($payload) {
    ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit(["status" => "error", "message" => "Invalid request method", "version" => "v9"]);
}

$data               = json_decode(file_get_contents("php://input"), true);
$location           = isset($data['location'])           ? trim($data['location'])           : '';
$blood_group_needed = isset($data['blood_group_needed']) ? trim($data['blood_group_needed']) : '';
$description        = isset($data['description'])        ? trim($data['description'])        : '';
$admin_uid          = isset($data['admin_uid'])           ? trim($data['admin_uid'])           : '';
$contact_number     = isset($data['contact_number'])      ? trim($data['contact_number'])      : '';

if (empty($location))           json_exit(["status" => "error", "message" => "Location is required."]);
if (empty($blood_group_needed)) json_exit(["status" => "error", "message" => "Blood group is required."]);
if (empty($contact_number))                  json_exit(["status" => "error", "message" => "Contact number is required."]);
if (!preg_match('/^[0-9]{10}$/', $contact_number)) json_exit(["status" => "error", "message" => "Contact number must be exactly 10 digits."]);

// Look up admin_id from admin table using firebase_uid sent from frontend
$admin_id = null;
if (!empty($admin_uid)) {
    $a = $conn->prepare("SELECT admin_id FROM admin WHERE firebase_uid = ? LIMIT 1");
    $a->bind_param("s", $admin_uid);
    $a->execute();
    $a->bind_result($fetched_admin_id);
    if ($a->fetch()) $admin_id = (int)$fetched_admin_id;
    $a->close();
}

// Fallback: grab the first admin if uid not sent or not found
if (!$admin_id) {
    $r = $conn->query("SELECT admin_id FROM admin LIMIT 1");
    if ($r && $r->num_rows > 0) {
        $admin_id = (int)$r->fetch_assoc()['admin_id'];
    }
}

if (!$admin_id) {
    json_exit(["status" => "error", "message" => "No admin found in database."]);
}

$title = "Urgent: $blood_group_needed Blood Needed";
$msg   = "EMERGENCY: $blood_group_needed blood needed urgently at $location.";
if ($contact_number) $msg .= " Contact: $contact_number.";
if ($description)    $msg .= " " . $description;

// Emergency alerts go to ALL active users — no blood group filtering
$donor_user_ids  = [];
$donors_for_node = [];

$d_stmt = $conn->prepare("SELECT user_id, firebase_uid, fcm_token FROM users WHERE user_status = 'Active' AND role_id = 2");
$d_stmt->execute();
$d_stmt->bind_result($d_user_id, $d_firebase_uid, $d_fcm_token);
while ($d_stmt->fetch()) {
    $donor_user_ids[] = (int)$d_user_id;
    if (!empty($d_firebase_uid))
        $donors_for_node[] = ['uid' => $d_firebase_uid, 'fcmToken' => $d_fcm_token ?: null];
}
$d_stmt->close();

// Insert alert using admin_id column
$ins = $conn->prepare("INSERT INTO emergency_alerts (admin_id, title, location, blood_group_needed, contact_number, description, alert_timestamp, is_fulfilled, donors_notified) VALUES (?, ?, ?, ?, ?, ?, NOW(), 0, 0)");
if (!$ins) json_exit(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
$ins->bind_param("isssss", $admin_id, $title, $location, $blood_group_needed, $contact_number, $description);
if (!$ins->execute()) json_exit(["status" => "error", "message" => "Insert failed: " . $ins->error]);
$alert_id = $conn->insert_id;
$ins->close();

// Save in-app notifications for donors
$notif_count = 0;
if (!empty($donor_user_ids)) {
    $nstmt = $conn->prepare("INSERT INTO notifications (user_id, message, status) VALUES (?, ?, 'Unread')");
    foreach ($donor_user_ids as $uid_int) {
        $nstmt->bind_param("is", $uid_int, $msg);
        if ($nstmt->execute()) $notif_count++;
    }
    $nstmt->close();
}
$conn->query("UPDATE emergency_alerts SET donors_notified = $notif_count WHERE alert_id = $alert_id");

// FCM tokens — already have all active users in donors_for_node
$all_fcm_tokens = array_filter(array_column($donors_for_node, 'fcmToken'));

// Socket.io / FCM (non-fatal)
$socket_ok = false;
try {
    $socket_ok = LifeLinkNotify::emergencyAlert($alert_id, $blood_group_needed, $location, $description, $donors_for_node, $all_fcm_tokens);
} catch (Exception $e) {
    error_log("[create_emergency_alert] Socket trigger failed: " . $e->getMessage());
}

json_exit([
    "status"           => "success",
    "message"          => "Emergency alert created. $notif_count donor(s) notified.",
    "alert_id"         => $alert_id,
    "admin_id"         => $admin_id,
    "donors_notified"  => $notif_count,
    "socket_triggered" => $socket_ok,
]);