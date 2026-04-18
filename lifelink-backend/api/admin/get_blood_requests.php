<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$search     = isset($_GET['search'])     ? $conn->real_escape_string(trim($_GET['search']))     : '';
$status     = isset($_GET['status'])     ? $conn->real_escape_string(trim($_GET['status']))     : '';
$blood_group = isset($_GET['blood_group']) ? $conn->real_escape_string(trim($_GET['blood_group'])) : '';
$urgency    = isset($_GET['urgency'])    ? $conn->real_escape_string(trim($_GET['urgency']))    : '';

$where = [];
if (!empty($search)) {
    $where[] = "(br.patient_name LIKE '%$search%' OR br.hospital_name LIKE '%$search%' OR u.name LIKE '%$search%')";
}
if (!empty($status)) {
    $where[] = "br.status = '$status'";
}
if (!empty($blood_group)) {
    $where[] = "br.blood_group = '$blood_group'";
}
if (!empty($urgency)) {
    $where[] = "br.urgency_level = '$urgency'";
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        br.*,
        u.name AS requester_name,
        u.email AS requester_email,
        u.phone_number AS requester_phone
    FROM blood_requests br
    LEFT JOIN users u ON u.firebase_uid = br.firebase_uid
    $where_sql
    ORDER BY 
        FIELD(br.urgency_level, 'Critical', 'High', 'Normal'),
        br.created_at DESC
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(["status" => "error", "message" => $conn->error]);
    exit;
}

$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

echo json_encode(["status" => "success", "data" => $requests]);
$conn->close();
?>
