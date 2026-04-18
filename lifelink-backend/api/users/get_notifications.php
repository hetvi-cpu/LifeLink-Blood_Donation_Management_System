<?php
require_once '../../config/db_config.php';

// Use firebase_uid (same pattern as all other user endpoints)
$uid   = isset($_GET['uid'])   ? trim($_GET['uid'])   : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit']  : 30;

if (empty($uid)) {
    echo json_encode(["status" => "error", "message" => "uid is required"]);
    exit;
}

// Look up user_id from firebase_uid
$uStmt = $conn->prepare("SELECT user_id FROM users WHERE firebase_uid = ?");
$uStmt->bind_param("s", $uid);
$uStmt->execute();
$uRow = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

if (!$uRow) {
    echo json_encode(["status" => "error", "message" => "User not found"]);
    exit;
}
$user_id = (int)$uRow['user_id'];

// Fetch notifications
$stmt = $conn->prepare("
    SELECT notification_id, message, status, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT ?
");
$stmt->bind_param("ii", $user_id, $limit);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Unread count
$cStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND status = 'Unread'");
$cStmt->bind_param("i", $user_id);
$cStmt->execute();
$unread_count = (int)$cStmt->get_result()->fetch_assoc()['cnt'];
$cStmt->close();

// ── Transform raw DB rows into structured notification objects ────────────────
// The message text contains clues about the type (🚨 = emergency, 📢 = campaign)
function parseNotification($row) {
    $msg  = $row['message'] ?? '';
    $id   = 'db_' . $row['notification_id'];
    $read = ($row['status'] === 'Read');
    $ts   = $row['created_at'];

    // Detect emergency alert
    if (strpos($msg, '🚨') !== false || stripos($msg, 'EMERGENCY') !== false) {
        // Extract blood group from message e.g. "O+ blood needed"
        preg_match('/([ABO]{1,2}[+-])/', $msg, $bgMatch);
        $bloodGroup = $bgMatch[1] ?? '';

        // Extract location — text after "at " before period/end
        preg_match('/at\s+([^\.]+)/i', $msg, $locMatch);
        $location = trim($locMatch[1] ?? '');

        return [
            'id'        => $id,
            'type'      => 'emergency_alert',
            'priority'  => 'emergency',
            'title'     => '🚨 Emergency Blood Request' . ($bloodGroup ? " — $bloodGroup" : ''),
            'body'      => $msg,
            'data'      => ['location' => $location, 'blood_group' => $bloodGroup],
            'timestamp' => $ts,
            'created_at'=> $ts,
            'read'      => $read,
        ];
    }

    // Detect new campaign
    if (strpos($msg, '📢') !== false || stripos($msg, 'campaign') !== false) {
        // Extract campaign name between "New campaign: " and " at "
        preg_match('/campaign[:\s]+([^—–]+)/i', $msg, $nameMatch);
        $campaignName = trim($nameMatch[1] ?? '');

        // Extract venue after " at "
        preg_match('/at\s+([^—–!]+)/i', $msg, $venueMatch);
        $venue = trim($venueMatch[1] ?? '');

        return [
            'id'        => $id,
            'type'      => 'new_campaign',
            'priority'  => 'high',
            'title'     => '📣 New Blood Donation Campaign',
            'body'      => $msg,
            'data'      => ['campaign_name' => $campaignName, 'venue' => $venue],
            'timestamp' => $ts,
            'created_at'=> $ts,
            'read'      => $read,
        ];
    }

    // Request accepted
    if (stripos($msg, 'accepted') !== false) {
        return [
            'id'        => $id,
            'type'      => 'request_accepted',
            'priority'  => 'high',
            'title'     => '🩸 Donation Request Accepted',
            'body'      => $msg,
            'data'      => [],
            'timestamp' => $ts,
            'created_at'=> $ts,
            'read'      => $read,
        ];
    }

    // Donation complete
    if (stripos($msg, 'donation') !== false && stripos($msg, 'complete') !== false) {
        return [
            'id'        => $id,
            'type'      => 'donation_complete',
            'priority'  => 'normal',
            'title'     => '🏅 Donation Completed',
            'body'      => $msg,
            'data'      => [],
            'timestamp' => $ts,
            'created_at'=> $ts,
            'read'      => $read,
        ];
    }

    // Generic fallback
    return [
        'id'        => $id,
        'type'      => 'general',
        'priority'  => 'normal',
        'title'     => 'LifeLink Notification',
        'body'      => $msg,
        'data'      => [],
        'timestamp' => $ts,
        'created_at'=> $ts,
        'read'      => $read,
    ];
}

$notifications = array_map('parseNotification', $rows);

echo json_encode([
    "status"       => "success",
    "notifications" => $notifications,   // ← hook reads json.notifications
    "unread_count"  => $unread_count,
]);

$conn->close();
?>