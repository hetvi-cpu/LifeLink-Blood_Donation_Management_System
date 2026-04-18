<?php
/**
 * get_compatible_requests.php
 * Used ONLY by the "Become a Donor" success page.
 *
 * Returns ALL open blood_requests compatible with this donor's blood type.
 * This is a proactive discovery view — it does NOT require the requester to
 * have clicked Send for this donor. Compare with get_incoming_requests.php
 * which is the dashboard view (explicitly sent requests only).
 *
 * Filters:
 *  - Blood type compatibility (donor can donate to recipient blood group)
 *  - blood_requests.status = 'Pending'  (not yet accepted/fulfilled)
 *  - Excludes the donor's own requests  (can't donate to yourself)
 *  - Donor must be Active               (Hold/Inactive/Suspended → empty)
 *  - Donor must have no active commitment (already Accepted elsewhere)
 * Ordering:
 *  - Urgency: Immediate → Urgent → Standard
 *  - Then newest first
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
    // 1. Get donor's blood group and status
    $stmtUser = $conn->prepare("SELECT blood_group, user_status FROM users WHERE firebase_uid = ?");
    $stmtUser->bind_param("s", $uid);
    $stmtUser->execute();
    $userData = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();

    if (!$userData) {
        echo json_encode(["status" => "error", "message" => "User not found"]);
        exit;
    }

    // Hold / Inactive / Suspended → nothing shown
    if ($userData['user_status'] !== 'Active') {
        echo json_encode(["status" => "success", "compatible_requests" => [], "reason" => "donor_not_active"]);
        exit;
    }

    // 2. Already has an active commitment → nothing shown
    $stmtCommit = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM donor_requests WHERE donor_uid = ? AND status = 'Accepted'"
    );
    $stmtCommit->bind_param("s", $uid);
    $stmtCommit->execute();
    $commitRow = $stmtCommit->get_result()->fetch_assoc();
    $stmtCommit->close();

    if ((int)($commitRow['cnt'] ?? 0) > 0) {
        echo json_encode(["status" => "success", "compatible_requests" => [], "reason" => "active_commitment"]);
        exit;
    }

    // 3. Blood groups this donor CAN donate to (donor → recipient map)
    $can_donate_to = [
        'O-'  => ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
        'O+'  => ['O+', 'A+', 'B+', 'AB+'],
        'A-'  => ['A-', 'A+', 'AB-', 'AB+'],
        'A+'  => ['A+', 'AB+'],
        'B-'  => ['B-', 'B+', 'AB-', 'AB+'],
        'B+'  => ['B+', 'AB+'],
        'AB-' => ['AB-', 'AB+'],
        'AB+' => ['AB+'],
    ];

    $my_blood         = $userData['blood_group'];
    $recipient_groups = $can_donate_to[$my_blood] ?? [$my_blood];
    $placeholders     = implode(',', array_fill(0, count($recipient_groups), '?'));
    $group_types      = str_repeat('s', count($recipient_groups));

    // 4. Fetch all compatible Pending blood_requests
    //    Exclude: the donor's own requests
    //    Exclude: requests this donor already explicitly declined (donor_requests row with Declined)
    $query = "SELECT br.request_id, br.blood_group, br.hospital_name,
                     br.hospital_address AS location, br.urgency_level,
                     u.name AS requester_name
              FROM blood_requests br
              JOIN users u ON br.firebase_uid = u.firebase_uid
              WHERE br.blood_group IN ($placeholders)
                AND br.status      = 'Pending'
                AND br.firebase_uid != ?
                AND br.request_id NOT IN (
                    SELECT COALESCE(blood_request_id, request_id)
                    FROM donor_requests
                    WHERE donor_uid = ? AND status = 'Declined'
                )
              ORDER BY
                CASE WHEN br.urgency_level = 'Immediate' THEN 1
                     WHEN br.urgency_level = 'Urgent'    THEN 2
                     ELSE 3 END ASC,
                br.created_at DESC";

    $stmt = $conn->prepare($query);

    // bind: recipient_groups + uid(exclude own) + uid(declined exclusion)
    $bind_types  = $group_types . 'ss';
    $bind_values = array_merge($recipient_groups, [$uid, $uid]);
    $refs = [];
    foreach ($bind_values as $k => $v) { $refs[$k] = &$bind_values[$k]; }
    array_unshift($refs, $bind_types);
    call_user_func_array([$stmt, 'bind_param'], $refs);

    $stmt->execute();
    $result = $stmt->get_result();

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();

    echo json_encode([
        "status"              => "success",
        "donor_blood"         => $my_blood,
        "can_donate_to"       => $recipient_groups,
        "compatible_requests" => $requests,
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
