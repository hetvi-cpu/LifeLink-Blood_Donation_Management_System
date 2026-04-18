<?php
/**
 * approve_donation_complete.php
 * -------------------------------------------------------
 * Called by ADMIN to approve a donor's completed donation.
 * 1. Sets donor_requests.status = 'Fulfilled' (unlocks certificate)
 * 2. Inserts a record into donations table (powers Donation Report)
 *
 * Body (JSON): { request_id }
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

require_once '../../config/db_config.php';

$data       = json_decode(file_get_contents("php://input"), true);
$request_id = $data['request_id'] ?? null;

if (!$request_id) {
    echo json_encode(["status" => "error", "message" => "request_id is required"]);
    exit;
}

try {
    // Step 1: Mark donor_request as Fulfilled → unlocks certificate for donor
    $stmt = $conn->prepare(
        "UPDATE donor_requests SET status = 'Fulfilled'
         WHERE request_id = ? AND status = 'PendingApproval'"
    );
    $stmt->bind_param("i", $request_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        echo json_encode([
            "status"  => "error",
            "message" => "No matching PendingApproval record found for this request_id."
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();

    // Step 2: Insert into donations table so it appears in Donation Activity Report
    $insertStmt = $conn->prepare("
        INSERT INTO donations (user_id, hospital_id, blood_group, donation_date, volume_ml, status)
        SELECT
            u.user_id,
            COALESCE(
                (SELECT h.hospital_id FROM hospital_details h
                 WHERE h.hospital_name = br.hospital_name LIMIT 1),
                1
            ),
            u.blood_group,
            CURDATE(),
            450,
            'Completed'
        FROM donor_requests dr
        JOIN users u ON u.firebase_uid = dr.donor_uid
        LEFT JOIN blood_requests br ON br.request_id = dr.request_id
        WHERE dr.request_id = ?
    ");
    $insertStmt->bind_param("i", $request_id);
    $insertStmt->execute();
    $insertStmt->close();

    // Step 3: Update user's last_donation_date and award points
    $pointsStmt = $conn->prepare("
        UPDATE users u
        JOIN donor_requests dr ON dr.donor_uid = u.firebase_uid
        SET u.last_donation_date = CURDATE(),
            u.points = COALESCE(u.points, 0) + 100
        WHERE dr.request_id = ?
    ");
    $pointsStmt->bind_param("i", $request_id);
    $pointsStmt->execute();
    $pointsStmt->close();

    echo json_encode([
        "status"  => "success",
        "message" => "Donation approved! Donor can now download their certificate."
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>
