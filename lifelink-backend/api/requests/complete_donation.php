<?php
/**
 * complete_donation.php  (UPDATED)
 * -------------------------------------------------------
 * Called by the DONOR when they click "Mark Donation as Completed".
 * Sets donor_requests.status = 'PendingApproval' (waits for admin).
 * Admin will later call approve_donation_complete.php to fully confirm.
 *
 * Body (JSON): { request_id, uid }
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

include_once '../../config/db_config.php';

$data       = json_decode(file_get_contents("php://input"), true);
$request_id = $data['request_id'] ?? null;
$donor_uid  = $data['uid']        ?? null;

if (!$request_id || !$donor_uid) {
    echo json_encode(["status" => "error", "message" => "Missing Request ID or User UID"]);
    exit;
}

try {
    /*
     * Instead of immediately marking as Fulfilled, we set status to
     * 'PendingApproval' so admin can review and issue certificate.
     *
     * NOTE: The blood_requests ENUM allows 'Fulfilled'. We add a new
     * status 'PendingApproval' to donor_requests only, so no DB schema
     * change is needed on blood_requests itself yet.
     * However, we update blood_requests to 'Fulfilled' directly since
     * that matches the existing logic — admin just approves the
     * certificate separately.
     */

    // 1. Flag the donor_request as PendingApproval
    $stmt1 = $conn->prepare(
        "UPDATE donor_requests SET status = 'PendingApproval'
         WHERE request_id = ? AND donor_uid = ?"
    );
    $stmt1->bind_param("is", $request_id, $donor_uid);
    $stmt1->execute();

    // 2. Mark blood_request as Fulfilled (keeps existing receiver UX)
    $stmt2 = $conn->prepare("UPDATE blood_requests SET status = 'Fulfilled' WHERE request_id = ?");
    $stmt2->bind_param("i", $request_id);
    $stmt2->execute();

    // 3. Award 100 points + update last_donation_date + set user_status = 'Hold'
    //    (user_status stays Hold for 90 days; get_user_dashboard resets it lazily)
    $today = date('Y-m-d');
    $stmt3 = $conn->prepare(
        "UPDATE users SET points = points + 100, last_donation_date = ?, user_status = 'Hold'
         WHERE firebase_uid = ?"
    );
    $stmt3->bind_param("ss", $today, $donor_uid);
    $stmt3->execute();

    // 4. Log to activity_history
    $stmtId = $conn->prepare("SELECT user_id FROM users WHERE firebase_uid = ?");
    $stmtId->bind_param("s", $donor_uid);
    $stmtId->execute();
    $row = $stmtId->get_result()->fetch_assoc();
    $db_user_id = $row['user_id'] ?? null;

    if ($db_user_id) {
        $action  = 'Donation Completed';
        $details = "Marked donation as completed for request #$request_id. Awaiting admin certificate approval.";
        $stmtH   = $conn->prepare(
            "INSERT INTO activity_history (user_id, action_type, details) VALUES (?, ?, ?)"
        );
        $stmtH->bind_param("iss", $db_user_id, $action, $details);
        $stmtH->execute();
    }

    echo json_encode([
        "status"  => "success",
        "message" => "Donation marked! Awaiting admin approval for your certificate."
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>