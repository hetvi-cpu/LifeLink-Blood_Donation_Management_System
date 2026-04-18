<?php
/**
 * get_incoming_requests.php
 * Only shows requests explicitly sent to this donor via the Send button.
 * send_notification.php creates a donor_requests row (status='Pending') when Send is clicked.
 * This query reads ONLY those rows — not all compatible requests.
 */

header("Access-Control-Allow-Origin: http://localhost:5175");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../config/db_config.php';

$uid = isset($_GET['uid']) ? trim($_GET['uid']) : '';

if (empty($uid)) {
    echo json_encode(["status" => "error", "message" => "User UID is required"]);
    exit;
}

try {
    // 1. Check donor's user_status
    $stmtUser = $conn->prepare("SELECT blood_group, user_status FROM users WHERE firebase_uid = ?");
    $stmtUser->bind_param("s", $uid);
    $stmtUser->execute();
    $userData = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();

    if (!$userData) {
        echo json_encode(["status" => "error", "message" => "User not found"]);
        exit;
    }

    // Hold / Inactive / Suspended → show nothing
    if ($userData['user_status'] !== 'Active') {
        echo json_encode(["status" => "success", "incoming_requests" => [], "reason" => "donor_not_active"]);
        exit;
    }

    // 2. Already has an active commitment → show nothing
    $stmtCommit = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM donor_requests WHERE donor_uid = ? AND status = 'Accepted'"
    );
    $stmtCommit->bind_param("s", $uid);
    $stmtCommit->execute();
    $commitRow = $stmtCommit->get_result()->fetch_assoc();
    $stmtCommit->close();

    if ((int)($commitRow['cnt'] ?? 0) > 0) {
        echo json_encode(["status" => "success", "incoming_requests" => [], "reason" => "active_commitment"]);
        exit;
    }

    // 3. Only show requests where requester explicitly clicked Send for this donor
    //    donor_requests.status = 'Pending' means Send was clicked, not yet accepted/declined
    //    blood_requests.status = 'Pending' means request is still open
    $query = "SELECT br.request_id, br.blood_group, br.hospital_name,
                     br.hospital_address AS location, br.urgency_level,
                     u.name AS requester_name
              FROM donor_requests dr
              JOIN blood_requests br ON dr.blood_request_id = br.request_id
              JOIN users u ON br.firebase_uid = u.firebase_uid
              WHERE dr.donor_uid = ?
                AND dr.status   = 'Pending'
                AND br.status   = 'Pending'
              ORDER BY
                CASE WHEN br.urgency_level = 'Immediate' THEN 1
                     WHEN br.urgency_level = 'Urgent'    THEN 2
                     ELSE 3 END ASC,
                br.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $result = $stmt->get_result();

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();

    echo json_encode([
        "status"            => "success",
        "donor_blood"       => $userData['blood_group'],
        "incoming_requests" => $requests,
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>