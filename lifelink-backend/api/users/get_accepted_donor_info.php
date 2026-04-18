<?php
/**
 * get_accepted_donor_info.php
 * -------------------------------------------------------
 * Returns the list of donors who have ACCEPTED a receiver's
 * blood request, along with their public contact details.
 *
 * Usage: GET ?uid=<receiver_firebase_uid>
 */

require_once '../../config/db_config.php';

$uid = isset($_GET['uid']) ? trim($_GET['uid']) : '';

if (empty($uid)) {
    echo json_encode(["status" => "error", "message" => "UID is required"]);
    exit;
}

try {
    /*
     * Join flow:
     *  blood_requests  →  identifies the requests made BY this receiver
     *  donor_requests  →  links each blood_request to the donor who accepted it
     *  users           →  fetches donor public profile
     */
    $sql = "
        SELECT
            dr.request_id,
            dr.status           AS donor_request_status,
            br.blood_group,
            br.hospital_name,
            br.hospital_address,
            br.city,
            br.required_date,
            br.urgency_level,
            br.status           AS request_status,
            u.name              AS donor_name,
            u.phone_number      AS donor_phone,
            u.pincode           AS donor_pincode,
            u.blood_group       AS donor_blood_group,
            u.location          AS donor_area
        FROM blood_requests br
        JOIN donor_requests dr ON br.request_id = dr.request_id
        JOIN users u ON dr.donor_uid = u.firebase_uid
        WHERE br.firebase_uid = ?
          AND dr.status IN ('Accepted', 'Fulfilled')
        ORDER BY dr.updated_at DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $result = $stmt->get_result();

    $donors = [];
    while ($row = $result->fetch_assoc()) {
        $donors[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "accepted_donors" => $donors
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>
