<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
$blood_group = isset($_GET['blood_group']) ? $conn->real_escape_string(trim($_GET['blood_group'])) : '';

$where = [];
if (!empty($search)) {
    $where[] = "(u.name LIKE '%$search%' OR u.email LIKE '%$search%')";
}
if (!empty($blood_group)) {
    $where[] = "u.blood_group = '$blood_group'";
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        d.donor_id,
        u.user_id, u.name, u.email, u.phone_number,
        u.blood_group, u.location, u.pincode,
        u.last_donation_date, u.points, u.user_status,
        d.weight, d.donated_recent, d.has_anemia,
        d.recent_infection, d.is_smoker, d.consumes_alcohol,
        d.recent_tattoo, d.recent_surgery,
        -- Eligibility: must be 90+ days since last donation (or never donated)
        CASE 
            WHEN u.last_donation_date IS NULL THEN 1
            WHEN DATEDIFF(CURDATE(), u.last_donation_date) >= 90 THEN 1
            ELSE 0
        END AS is_eligible,
        CASE 
            WHEN u.last_donation_date IS NOT NULL 
            THEN GREATEST(0, 90 - DATEDIFF(CURDATE(), u.last_donation_date))
            ELSE 0
        END AS days_until_eligible,
        -- Count total fulfilled donations
        (SELECT COUNT(*) FROM blood_requests br WHERE br.firebase_uid = u.firebase_uid AND br.status = 'Fulfilled') AS total_donations
    FROM donors d
    JOIN users u ON u.firebase_uid = d.firebase_uid
    $where_sql
    ORDER BY u.name ASC
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(["status" => "error", "message" => $conn->error]);
    exit;
}

$donors = [];
while ($row = $result->fetch_assoc()) {
    // Cast boolean fields
    foreach (['donated_recent','has_anemia','recent_infection','is_smoker','consumes_alcohol','recent_tattoo','recent_surgery','is_eligible'] as $f) {
        $row[$f] = (bool)$row[$f];
    }
    $row['total_donations'] = (int)$row['total_donations'];
    $donors[] = $row;
}

echo json_encode(["status" => "success", "data" => $donors]);
$conn->close();
?>
