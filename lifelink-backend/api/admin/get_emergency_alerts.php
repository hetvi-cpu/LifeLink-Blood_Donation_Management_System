<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$fulfilled = isset($_GET['fulfilled']) ? $_GET['fulfilled'] : 'all'; // 'all', '0', '1'

$where_sql = '';
if ($fulfilled === '0') {
    $where_sql = "WHERE ea.is_fulfilled = 0";
} elseif ($fulfilled === '1') {
    $where_sql = "WHERE ea.is_fulfilled = 1";
}

// ── Fetch all columns including donors_notified, title, description ───────────
$sql = "
    SELECT
        ea.alert_id,
        ea.admin_id,
        ea.title,
        ea.location,
        ea.blood_group_needed,
        ea.contact_number,
        ea.description,
        ea.donors_notified,
        ea.alert_timestamp,
        ea.is_fulfilled,
        ea.created_at,
        a.name  AS admin_name,
        a.email AS admin_email
    FROM emergency_alerts ea
    LEFT JOIN admin a ON a.admin_id = ea.admin_id
    $where_sql
    ORDER BY ea.alert_timestamp DESC
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(["status" => "error", "message" => $conn->error]);
    $conn->close();
    exit;
}

$alerts = [];
while ($row = $result->fetch_assoc()) {
    $row['is_fulfilled']    = (bool)$row['is_fulfilled'];
    $row['donors_notified'] = (int)$row['donors_notified'];
    $alerts[] = $row;
}

echo json_encode(["status" => "success", "data" => $alerts]);
$conn->close();
?>