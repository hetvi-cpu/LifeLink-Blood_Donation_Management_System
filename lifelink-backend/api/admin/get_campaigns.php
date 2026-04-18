<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
$status = isset($_GET['status']) ? $conn->real_escape_string(trim($_GET['status'])) : '';
$type   = isset($_GET['type'])   ? $conn->real_escape_string(trim($_GET['type']))   : '';

$where = [];
if (!empty($search)) {
    $where[] = "(c.campaign_name LIKE '%$search%' OR c.organized_by LIKE '%$search%' OR c.venue_info LIKE '%$search%')";
}
if (!empty($status)) {
    $where[] = "c.status = '$status'";
}
if (!empty($type)) {
    $where[] = "c.campaign_type = '$type'";
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        c.*,
        COUNT(cr.registration_id) AS registered_count,
        SUM(CASE WHEN cr.status = 'Completed' THEN 1 ELSE 0 END) AS completed_count
    FROM campaigns c
    LEFT JOIN campaign_registrations cr ON cr.campaign_name = c.campaign_name
    $where_sql
    GROUP BY c.campaign_id
    ORDER BY c.campaign_id DESC
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(["status" => "error", "message" => $conn->error]);
    exit;
}

$campaigns = [];
while ($row = $result->fetch_assoc()) {
    $row['registered_count'] = (int)$row['registered_count'];
    $row['completed_count']  = (int)$row['completed_count'];
    $campaigns[] = $row;
}

echo json_encode(["status" => "success", "data" => $campaigns]);
$conn->close();
?>
