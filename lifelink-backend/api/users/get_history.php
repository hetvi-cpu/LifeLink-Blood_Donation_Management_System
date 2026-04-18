<?php
/**
 * get_history.php  (UPDATED)
 * -------------------------------------------------------
 * Returns:
 *  1. activity_history  – existing donation / acceptance logs
 *  2. campaign_registrations  – campaigns the user volunteered for
 *     (joined with campaigns table to get full campaign details)
 *
 * Usage: GET ?user_id=<int>
 */

require_once '../../config/db_config.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid User ID"]);
    exit;
}

/* ── 1. Activity history (existing donor/action logs) ────── */
$sql = "SELECT action_type, details, created_at
        FROM activity_history
        WHERE user_id = ?
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

/* ── 1b. Requester blood request history ─────────────────── */
// Get firebase_uid for the user to query blood_requests
$uidStmt2 = $conn->prepare("SELECT firebase_uid FROM users WHERE user_id = ?");
$uidStmt2->bind_param("i", $user_id);
$uidStmt2->execute();
$uidRow2 = $uidStmt2->get_result()->fetch_assoc();
$firebase_uid_for_requests = $uidRow2['firebase_uid'] ?? null;

if ($firebase_uid_for_requests) {
    // Fetch all blood requests made by this user
    $reqSql = "SELECT request_id, blood_group, hospital_name, city, status, created_at, urgency_level
               FROM blood_requests
               WHERE firebase_uid = ?
               ORDER BY created_at DESC";
    $reqStmt = $conn->prepare($reqSql);
    $reqStmt->bind_param("s", $firebase_uid_for_requests);
    $reqStmt->execute();
    $reqResult = $reqStmt->get_result();

    while ($reqRow = $reqResult->fetch_assoc()) {
        // Entry for when the request was created
        $history[] = [
            'action_type' => 'blood_request_created',
            'details'     => 'Requested ' . $reqRow['blood_group'] . ' blood at ' . ($reqRow['hospital_name'] ?? 'Hospital') . ', ' . ($reqRow['city'] ?? ''),
            'created_at'  => $reqRow['created_at'],
            'request_status' => $reqRow['status'],
        ];

        // If fulfilled, add a separate entry for fulfillment
        if ($reqRow['status'] === 'Fulfilled') {
            // Try to get fulfillment timestamp from donor_requests
            $fulfilStmt = $conn->prepare(
                "SELECT updated_at FROM donor_requests
                 WHERE request_id = ? AND status = 'Fulfilled'
                 ORDER BY updated_at DESC LIMIT 1"
            );
            $fulfilStmt->bind_param("i", $reqRow['request_id']);
            $fulfilStmt->execute();
            $fulfilRow = $fulfilStmt->get_result()->fetch_assoc();
            $fulfilledAt = $fulfilRow['updated_at'] ?? $reqRow['created_at'];

            $history[] = [
                'action_type' => 'blood_request_fulfilled',
                'details'     => 'Your request for ' . $reqRow['blood_group'] . ' blood at ' . ($reqRow['hospital_name'] ?? 'Hospital') . ' has been fulfilled ✔',
                'created_at'  => $fulfilledAt,
                'request_status' => 'Fulfilled',
            ];
        }
    }

    // Sort all history by created_at descending
    usort($history, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
}

/* ── 2. Campaign registrations ───────────────────────────── */
/*
 * We need the firebase_uid of the user to query campaign_registrations
 * (which stores firebase_uid, not the internal user_id).
 */
$uidStmt = $conn->prepare("SELECT firebase_uid FROM users WHERE user_id = ?");
$uidStmt->bind_param("i", $user_id);
$uidStmt->execute();
$uidRow = $uidStmt->get_result()->fetch_assoc();
$firebase_uid = $uidRow['firebase_uid'] ?? null;

$campaigns = [];

if ($firebase_uid) {
    $campSql = "
        SELECT
            cr.registration_id,
            cr.campaign_name,
            cr.full_name,
            cr.phone_number,
            cr.blood_group,
            cr.age,
            cr.registration_date,
            cr.status                   AS registration_status,
            c.campaign_type,
            c.organized_by,
            c.venue_info,
            c.contact_person_name,
            c.contact_phone             AS campaign_contact_phone,
            c.facilities,
            c.status                    AS campaign_status
        FROM campaign_registrations cr
        LEFT JOIN campaigns c ON cr.campaign_name = c.campaign_name
        WHERE cr.firebase_uid = ?
        ORDER BY cr.registration_date DESC
    ";

    $campStmt = $conn->prepare($campSql);
    $campStmt->bind_param("s", $firebase_uid);
    $campStmt->execute();
    $campResult = $campStmt->get_result();

    while ($row = $campResult->fetch_assoc()) {
        $campaigns[] = $row;
    }
}

echo json_encode([
    "status"    => "success",
    "data"      => $history,       // existing key — no breaking change
    "campaigns" => $campaigns      // NEW key
]);

$conn->close();
?>
