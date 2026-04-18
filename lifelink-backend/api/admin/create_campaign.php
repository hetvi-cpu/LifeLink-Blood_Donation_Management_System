<?php
require_once '../../config/db_config.php';
require_once '../../notifications/notification_bridge.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$required = [
    'campaign_name', 'campaign_type', 'organized_by', 'blood_group_needed',
    'target_units', 'venue_info', 'contact_person_name', 'contact_phone', 'status'
];
foreach ($required as $field) {
    if (empty($data[$field])) {
        echo json_encode(["status" => "error", "message" => "Missing required field: $field"]);
        exit;
    }
}

$allowed_types    = ['NGO', 'Hospital', 'Corporate'];
$allowed_statuses = ['Upcoming', 'Active', 'Completed'];

if (!in_array($data['campaign_type'], $allowed_types) || !in_array($data['status'], $allowed_statuses)) {
    echo json_encode(["status" => "error", "message" => "Invalid campaign_type or status value"]);
    exit;
}

$facilities = isset($data['facilities']) ? trim($data['facilities']) : '';

// ── Insert campaign ────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO campaigns
        (campaign_name, campaign_type, organized_by, blood_group_needed, target_units,
         venue_info, contact_person_name, contact_phone, facilities, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    "ssssisssss", // campaign_name, campaign_type, organized_by, blood_group_needed, target_units(i), venue_info, contact_person_name, contact_phone, facilities, status
    $data['campaign_name'],
    $data['campaign_type'],
    $data['organized_by'],
    $data['blood_group_needed'],
    $data['target_units'],
    $data['venue_info'],
    $data['contact_person_name'],
    $data['contact_phone'],
    $facilities,
    $data['status']
);

if (!$stmt->execute()) {
    echo json_encode(["status" => "error", "message" => $conn->error]);
    $stmt->close(); $conn->close(); exit;
}
$campaign_id = $conn->insert_id;
$stmt->close();

// ── Save in-app notifications to ALL active users ─────────────────────────────
$notif_msg = "📢 New campaign: {$data['campaign_name']} at {$data['venue_info']} — {$data['blood_group_needed']} blood group needed!";
$all_users = $conn->query("SELECT user_id FROM users WHERE role_id = 2 AND user_status = 'Active'");
$notified_users = 0;
if ($all_users) {
    $nstmt = $conn->prepare("INSERT INTO notifications (user_id, message, status) VALUES (?, ?, 'Unread')");
    while ($row = $all_users->fetch_assoc()) {
        $nstmt->bind_param("is", $row['user_id'], $notif_msg);
        if ($nstmt->execute()) $notified_users++;
    }
    $nstmt->close();
}

// ── Fetch all active user FCM tokens ─────────────────────────────────────────
$fcm_tokens = [];
$token_res  = $conn->query(
    "SELECT fcm_token FROM users
     WHERE role_id = 2 AND user_status = 'Active' AND fcm_token IS NOT NULL AND fcm_token != ''"
);
if ($token_res) {
    while ($row = $token_res->fetch_assoc()) {
        $fcm_tokens[] = $row['fcm_token'];
    }
}

// ── Trigger real-time notification (PHP 7 compatible — positional args) ───────
$socket_ok = false;
try {
    $socket_ok = LifeLinkNotify::newCampaign(
        $campaign_id,
        $data['campaign_name'],
        $data['organized_by'],
        $data['blood_group_needed'],
        $data['venue_info'],
        $data['status'],
        $fcm_tokens
    );
} catch (Exception $e) {
    error_log("[create_campaign] Socket trigger failed: " . $e->getMessage());
}

echo json_encode([
    "status"           => "success",
    "message"          => "Campaign created and $notified_users users notified",
    "campaign_id"      => $campaign_id,
    "notified_users"   => $notified_users,
    "notified_devices" => count($fcm_tokens),
    "socket_triggered" => $socket_ok,
]);

$conn->close();
?>