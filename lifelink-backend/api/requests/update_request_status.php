<?php
/**
 * update_request_status.php — FIXED
 * Bug #7: Decline no longer sets blood_requests.status = 'Declined'
 *         (which was permanently blocking all other donors).
 *         Now only the specific donor_requests row is marked Declined,
 *         so blood_requests stays Pending and other donors can still respond.
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

include_once '../../config/db_config.php';
require_once '../../notifications/notification_bridge.php';

$data       = json_decode(file_get_contents("php://input"), true);
$request_id = $data['request_id'] ?? null;
$status     = $data['status']     ?? null;
$donor_uid  = $data['uid']        ?? null;

if (!$request_id || !$status) {
    echo json_encode(["status" => "error", "message" => "Missing request_id or status"]);
    exit;
}

try {

    // 1. Fetch original blood_request (requester info)
    $stmtInfo = $conn->prepare(
        "SELECT br.firebase_uid AS requester_uid,
                br.blood_group,
                u.user_id       AS requester_db_id,
                u.fcm_token     AS requester_fcm,
                u.name          AS requester_name
         FROM blood_requests br
         JOIN users u ON u.firebase_uid = br.firebase_uid
         WHERE br.request_id = ?"
    );
    $stmtInfo->bind_param("i", $request_id);
    $stmtInfo->execute();
    $info = $stmtInfo->get_result()->fetch_assoc();
    $stmtInfo->close();

    if (!$info) {
        echo json_encode(["status" => "error", "message" => "Blood request not found"]);
        exit;
    }

    $requester_uid   = $info['requester_uid'];
    $blood_group     = $info['blood_group'];
    $requester_db_id = $info['requester_db_id'];
    $requester_fcm   = $info['requester_fcm'];
    $requester_name  = $info['requester_name'];

    // 2. Resolve donor's db user_id and name
    $donor_name  = 'A donor';
    $donor_db_id = null;

    if ($donor_uid) {
        $stmtD = $conn->prepare("SELECT user_id, name FROM users WHERE firebase_uid = ?");
        $stmtD->bind_param("s", $donor_uid);
        $stmtD->execute();
        $dRow = $stmtD->get_result()->fetch_assoc();
        $stmtD->close();
        if ($dRow) { $donor_db_id = $dRow['user_id']; $donor_name = $dRow['name']; }
    }

    if (!$donor_uid) {
        $stmtFb = $conn->prepare("SELECT firebase_uid, user_id, name FROM users WHERE blood_group = ? LIMIT 1");
        $stmtFb->bind_param("s", $blood_group);
        $stmtFb->execute();
        $fbRow     = $stmtFb->get_result()->fetch_assoc();
        $stmtFb->close();
        $donor_uid   = $fbRow['firebase_uid'] ?? 'SYSTEM_ASSIGNED';
        $donor_db_id = $fbRow['user_id']      ?? null;
        $donor_name  = $fbRow['name']         ?? 'A donor';
    }

    // ══════════════════════════════════════════════════════════════════
    if ($status === 'Accepted') {
    // ══════════════════════════════════════════════════════════════════

        // Update blood_requests status to Accepted
        $stmtBR = $conn->prepare("UPDATE blood_requests SET status = 'Accepted' WHERE request_id = ?");
        $stmtBR->bind_param("i", $request_id);
        $stmtBR->execute();
        $stmtBR->close();

        // Insert into donor_requests
        $stmtDR = $conn->prepare(
            "INSERT INTO donor_requests (request_id, requester_uid, donor_uid, blood_group, status)
             VALUES (?, ?, ?, ?, 'Accepted')"
        );
        $stmtDR->bind_param("isss", $request_id, $requester_uid, $donor_uid, $blood_group);
        $stmtDR->execute();
        $stmtDR->close();

        // Notify requester
        $notif_title = "🩸 Donor Found!";
        $notif_msg   = "$donor_name has accepted your $blood_group blood request. They are on their way!";
        $stmtN = $conn->prepare(
            "INSERT INTO notifications (user_id, firebase_uid, title, message, type, priority, status)
             VALUES (?, ?, ?, ?, 'request_accepted', 'high', 'Unread')"
        );
        $stmtN->bind_param("isss", $requester_db_id, $requester_uid, $notif_title, $notif_msg);
        $stmtN->execute();
        $stmtN->close();

        try {
            LifeLinkNotify::requestAccepted($requester_uid, $donor_name, $blood_group, $request_id, $requester_fcm);
        } catch (Exception $e) {
            error_log("[update_request_status] Notification bridge error: " . $e->getMessage());
        }

        if ($donor_db_id) {
            $action  = 'accepted';
            $details = "Accepted a $blood_group blood request (#$request_id)";
            $stmtH   = $conn->prepare("INSERT INTO activity_history (user_id, action_type, details) VALUES (?, ?, ?)");
            $stmtH->bind_param("iss", $donor_db_id, $action, $details);
            $stmtH->execute();
            $stmtH->close();
        }

        echo json_encode(["status" => "success", "message" => "Accepted successfully! Requester has been notified."]);

    // ══════════════════════════════════════════════════════════════════
    } else {
    // Decline — Bug #7 FIX
    // ══════════════════════════════════════════════════════════════════

        // Bug #7 fix: ONLY log this donor's decision in donor_requests.
        // Do NOT touch blood_requests — it stays 'Pending' so other donors can respond.
        // INSERT a new Declined row so get_incoming_requests can exclude it next time.
        // (donor_requests.request_id is auto-increment, so each decline is a new row —
        //  the exclusion subquery just checks for ANY declined row for this donor+request)
        $stmtDec = $conn->prepare(
            "INSERT INTO donor_requests (request_id, requester_uid, donor_uid, blood_group, status)
             VALUES (?, ?, ?, ?, 'Declined')"
        );
        $stmtDec->bind_param("isss", $request_id, $requester_uid, $donor_uid, $blood_group);
        $stmtDec->execute();
        $stmtDec->close();

        // Notify requester
        $notif_title = "Request Update";
        $notif_msg   = "$donor_name could not fulfill your $blood_group request. We are looking for another donor.";
        $stmtN2 = $conn->prepare(
            "INSERT INTO notifications (user_id, firebase_uid, title, message, type, priority, status)
             VALUES (?, ?, ?, ?, 'request_declined', 'normal', 'Unread')"
        );
        $stmtN2->bind_param("isss", $requester_db_id, $requester_uid, $notif_title, $notif_msg);
        $stmtN2->execute();
        $stmtN2->close();

        try {
            LifeLinkNotify::requestDeclined($requester_uid, $donor_name, $blood_group, $request_id, $requester_fcm);
        } catch (Exception $e) {
            error_log("[update_request_status] Decline notification error: " . $e->getMessage());
        }

        echo json_encode(["status" => "success", "message" => "Declined successfully."]);
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>