<?php
/**
 * get_pending_approvals.php — FIXED
 * Bug #13: Now returns approved_today and total_certificates real stats
 */

require_once '../../config/db_config.php';

try {
    // Pending approvals list
    $stmt = $conn->prepare("
        SELECT
            dr.request_id, dr.donor_uid, dr.blood_group, dr.updated_at,
            u_donor.name           AS donor_name,
            u_donor.phone_number   AS donor_phone,
            u_donor.email          AS donor_email,
            u_donor.location       AS donor_area,
            u_donor.blood_group    AS donor_blood_group,
            br.hospital_name, br.hospital_address,
            br.patient_name, br.required_date,
            u_req.name             AS requester_name
        FROM donor_requests dr
        JOIN users u_donor ON dr.donor_uid = u_donor.firebase_uid
        LEFT JOIN blood_requests br ON dr.request_id = br.request_id
        LEFT JOIN users u_req ON br.firebase_uid = u_req.firebase_uid
        WHERE dr.status = 'PendingApproval'
        ORDER BY dr.updated_at DESC
    ");
    $stmt->execute();
    $result  = $stmt->get_result();
    $pending = [];
    while ($row = $result->fetch_assoc()) $pending[] = $row;
    $stmt->close();

    // Bug #13 fix: approved today
    $stmtToday = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM donor_requests
         WHERE status = 'Fulfilled' AND DATE(updated_at) = CURDATE()"
    );
    $stmtToday->execute();
    $approvedToday = (int)($stmtToday->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmtToday->close();

    // Bug #13 fix: total certificates ever issued
    $stmtTotal = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM donor_requests WHERE status = 'Fulfilled'"
    );
    $stmtTotal->execute();
    $totalCertificates = (int)($stmtTotal->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmtTotal->close();

    echo json_encode([
        "status"             => "success",
        "count"              => count($pending),
        "approved_today"     => $approvedToday,      // Bug #13 fix
        "total_certificates" => $totalCertificates,  // Bug #13 fix
        "data"               => $pending,
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>