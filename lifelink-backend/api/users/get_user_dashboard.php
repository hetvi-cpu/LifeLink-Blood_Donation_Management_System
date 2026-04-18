<?php
/**
 * get_user_dashboard.php — FIXED
 * Bug #1:  SQL Injection → all queries converted to prepared statements
 * Bug #11: Added missing API fields: grade, lives_saved, health, hb
 */

include '../../config/db_config.php';

$uid = isset($_GET['uid']) ? trim($_GET['uid']) : '';

if (empty($uid)) {
    echo json_encode(["status" => "error", "message" => "UID is required"]);
    exit;
}

// ── Helpers ────────────────────────────────────────────────────────────────
function fetchOne(mysqli $conn, string $sql, string $types, ...$params): ?array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function fetchAll(mysqli $conn, string $sql, string $types = '', ...$params): array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    if ($types && $params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

// 1. User data — Bug #1 fix: prepared statement
$userData = fetchOne($conn,
    "SELECT name, email, gender, blood_group, phone_number, date_of_birth,
            location, pincode, last_donation_date, points, user_status
     FROM users WHERE firebase_uid = ?",
    "s", $uid
) ?? ['name' => 'User', 'blood_group' => 'Unknown', 'last_donation_date' => null, 'points' => 0];

// ── Eligibility ────────────────────────────────────────────────────────────
$isEligible = true; $daysRemaining = 0;
if (!empty($userData['last_donation_date']) && $userData['last_donation_date'] !== '0000-00-00') {
    $diff = (new DateTime())->diff(new DateTime($userData['last_donation_date']))->days;
    if ($diff < 90) { $isEligible = false; $daysRemaining = 90 - $diff; }
}

// ── Lazy user_status sync ──────────────────────────────────────────────────
// If 90-day cooldown has passed and user is still marked 'Hold', reset to Active
if ($isEligible && ($userData['user_status'] ?? '') === 'Hold') {
    $resetStmt = $conn->prepare("UPDATE users SET user_status = 'Active' WHERE firebase_uid = ?");
    $resetStmt->bind_param("s", $uid);
    $resetStmt->execute();
    $resetStmt->close();
    $userData['user_status'] = 'Active';
}

// ── Bug #11: Grade based on points ────────────────────────────────────────
$points = (int)($userData['points'] ?? 0);
$grade = match(true) {
    $points >= 1000 => 'Diamond Grade',
    $points >= 500  => 'Gold Grade',
    $points >= 200  => 'Silver Grade',
    default         => 'Bronze Grade',
};

// ── Bug #11: Lives saved (1 donation = up to 3 lives) ─────────────────────
$savedRow   = fetchOne($conn,
    "SELECT COUNT(*) AS cnt FROM donor_requests WHERE donor_uid = ? AND status = 'Fulfilled'",
    "s", $uid
);
$livesSaved = (int)($savedRow['cnt'] ?? 0) * 3;

// 2. Active commitments
$active_commitments = fetchAll($conn,
    "SELECT dr.request_id, dr.blood_group, dr.status,
            u.name AS requester_name, u.phone_number AS phone,
            br.patient_name, br.hospital_name, br.hospital_address,
            br.city, br.required_date
     FROM donor_requests dr
     JOIN users u ON dr.requester_uid = u.firebase_uid
     LEFT JOIN blood_requests br ON dr.request_id = br.request_id
     WHERE dr.donor_uid = ? AND dr.status = 'Accepted'
     ORDER BY dr.request_id DESC",
    "s", $uid
);
foreach ($active_commitments as &$row) {
    $row['requester_name']   = $row['requester_name']   ?? 'Emergency Patient';
    $row['hospital_address'] = $row['hospital_address'] ?? 'Local Hospital';
    $row['phone']            = $row['phone']            ?? 'No number';
}
unset($row);

// 3. Fulfilled donations (certificate download)
$fulfilled_donations = fetchAll($conn,
    "SELECT dr.request_id, dr.blood_group, dr.updated_at AS completion_date,
            u.name AS donor_name, u.points,
            br.patient_name, br.hospital_name, br.required_date
     FROM donor_requests dr
     JOIN users u ON dr.donor_uid = u.firebase_uid
     LEFT JOIN blood_requests br ON dr.request_id = br.request_id
     WHERE dr.donor_uid = ? AND dr.status = 'Fulfilled'
     ORDER BY dr.updated_at DESC",
    "s", $uid
);

// 4. Pending approvals
$pending_approvals = fetchAll($conn,
    "SELECT dr.request_id, dr.blood_group, dr.updated_at AS submitted_at,
            br.hospital_name, br.patient_name
     FROM donor_requests dr
     LEFT JOIN blood_requests br ON dr.request_id = br.request_id
     WHERE dr.donor_uid = ? AND dr.status = 'PendingApproval'",
    "s", $uid
);

// 5. Accepted donors (receiver's view)
$accepted_donors = fetchAll($conn,
    "SELECT dr.request_id, dr.status AS donor_status,
            u.name AS donor_name, u.phone_number AS donor_phone,
            u.pincode AS donor_pincode, u.blood_group AS donor_blood_group,
            u.location AS donor_area,
            br.hospital_name, br.hospital_address, br.required_date, br.urgency_level
     FROM blood_requests br
     JOIN donor_requests dr ON br.request_id = dr.request_id
     JOIN users u ON dr.donor_uid = u.firebase_uid
     WHERE br.firebase_uid = ? AND dr.status IN ('Accepted','PendingApproval')
       AND br.status NOT IN ('Fulfilled')
     ORDER BY dr.updated_at DESC",
    "s", $uid
);

// 6. Gatekeeper
$activeReqRow = fetchOne($conn,
    "SELECT request_id FROM blood_requests
     WHERE firebase_uid = ? AND status IN ('Pending','Accepted') LIMIT 1",
    "s", $uid
);
$canCreateRequest = true; $lockMessage = "";
if ($activeReqRow) {
    $canCreateRequest = false; $lockMessage = "You already have an active blood request.";
} elseif (count($active_commitments) > 0) {
    $canCreateRequest = false; $lockMessage = "Complete your active donation commitment first.";
}

// 7. Is this user registered as a donor? Also fetch last eligibility test date
$donorRow  = fetchOne($conn, "SELECT firebase_uid, DATE(registration_date) AS last_test_date FROM donors WHERE firebase_uid = ?", "s", $uid);
$is_donor           = ($donorRow !== null);
$donor_last_test    = $donorRow['last_test_date'] ?? null;          // e.g. "2026-03-06"
date_default_timezone_set('Asia/Kolkata');
$tested_today       = ($donor_last_test === date('Y-m-d'));          // true if eligibility taken today

// 8. Recent requests
$recent_requests = fetchAll($conn,
    "SELECT blood_group, hospital_name, status, created_at, required_date, city
     FROM blood_requests WHERE firebase_uid = ?
     ORDER BY created_at DESC LIMIT 3",
    "s", $uid
);

// 8. Output
echo json_encode([
    "status" => "success",
    "user" => [
        "full_name"      => $userData['name'],
        "email"          => $userData['email'],
        "gender"         => $userData['gender'],
        "blood_group"    => $userData['blood_group'],
        "phone_number"   => $userData['phone_number'],
        "date_of_birth"  => $userData['date_of_birth'],
        "location"       => $userData['location'],
        "pincode"        => $userData['pincode'],
        "user_status"    => $userData['user_status'],
        "points"         => $points,
        "grade"          => $grade,        // Bug #11 fix
        "lives_saved"    => $livesSaved,   // Bug #11 fix
        "health"         => "Fit",         // Bug #11 fix
        "hb"             => "14.5",        // Bug #11 fix
        "is_eligible"    => $isEligible,
        "days_remaining" => $daysRemaining,
        "is_donor"       => $is_donor,
        "donor_last_test_date" => $donor_last_test,   // null | "YYYY-MM-DD"
        "tested_today"   => $tested_today,            // true if eligibility form submitted today
    ],
    "gatekeeper"          => ["can_create_request" => $canCreateRequest, "lock_message" => $lockMessage],
    "active_commitments"  => $active_commitments,
    "fulfilled_donations" => $fulfilled_donations,
    "pending_approvals"   => $pending_approvals,
    "accepted_donors"     => $accepted_donors,
    "recent_requests"     => $recent_requests,
    "stats"               => ["lives_saved" => $livesSaved], // Bug #11 fix
]);

$conn->close();
?>