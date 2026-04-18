<?php
require_once '../../config/db_config.php';
require_once '../../notifications/notification_bridge.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$data       = json_decode(file_get_contents("php://input"), true);
$title      = isset($data['title'])      ? trim($data['title'])   : 'LifeLink Notification';
$message    = isset($data['message'])    ? trim($data['message']) : '';
// Support both 'target' and 'broadcast' field from frontend
$broadcast  = isset($data['broadcast']) ? (bool)$data['broadcast'] : true;
$target     = isset($data['target'])    ? $data['target'] : ($broadcast ? 'all' : 'all');
$blood_group = isset($data['blood_group']) ? trim($data['blood_group']) : '';

if (empty($message)) {
    echo json_encode(["status" => "error", "message" => "message is required"]);
    exit;
}

// Build query based on target
if ($target === 'all' || $broadcast) {
    if (!empty($blood_group)) {
        $result = $conn->query("SELECT user_id, firebase_uid, fcm_token FROM users WHERE role_id = 2 AND user_status = 'Active' AND blood_group = '" . $conn->real_escape_string($blood_group) . "'");
    } else {
        $result = $conn->query("SELECT user_id, firebase_uid, fcm_token FROM users WHERE role_id = 2 AND user_status = 'Active'");
    }

    $sent       = 0;
    $recipients = [];

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, status) VALUES (?, ?, ?, 'Unread')");
    while ($row = $result->fetch_assoc()) {
        $uid_int = $row['user_id'];
        $stmt->bind_param("iss", $uid_int, $title, $message);
        if ($stmt->execute()) $sent++;

        if (!empty($row['firebase_uid'])) {
            $recipients[] = [
                'uid'      => $row['firebase_uid'],
                'fcmToken' => $row['fcm_token'] ?: null,
            ];
        }
    }
    $stmt->close();

    // Fire socket + FCM for each recipient via Node bridge
    foreach ($recipients as $r) {
        try {
            LifeLinkNotify::send(
                $r['uid'],
                'broadcast',
                $title,
                $message,
                [],
                $r['fcmToken']
            );
        } catch (Exception $e) {
            error_log("[send_notification] Bridge error: " . $e->getMessage());
            error_log("[DEBUG] send result uid=" . $r['uid'] . " result=" . json_encode($result));
        }
    }

    echo json_encode(["status" => "success", "message" => "Notification sent to $sent users"]);

} else {
    // Send to specific user_id
    $user_id = (int)$target;
    if (!$user_id) {
        echo json_encode(["status" => "error", "message" => "Invalid target"]);
        exit;
    }

    $uStmt = $conn->prepare("SELECT firebase_uid, fcm_token FROM users WHERE user_id = ?");
    $uStmt->bind_param("i", $user_id);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, status) VALUES (?, ?, ?, 'Unread')");
    $stmt->bind_param("iss", $user_id, $title, $message);
    if ($stmt->execute()) {
        try {
            if (!empty($uRow['firebase_uid'])) {
                LifeLinkNotify::send(
                    $uRow['firebase_uid'],
                    'broadcast',
                    $title,
                    $message,
                    [],
                    $uRow['fcm_token'] ?: null
                );
            }
        } catch (Exception $e) {
            error_log("[send_notification] Bridge error: " . $e->getMessage());
        }
        echo json_encode(["status" => "success", "message" => "Notification sent"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
    $stmt->close();
}

$conn->close();
?>