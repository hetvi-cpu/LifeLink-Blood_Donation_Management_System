<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$search      = isset($_GET['search'])      ? $conn->real_escape_string(trim($_GET['search']))      : '';
$action_type = isset($_GET['action_type']) ? $conn->real_escape_string(trim($_GET['action_type'])) : '';
$user_id     = isset($_GET['user_id'])     ? (int)$_GET['user_id']                                 : 0;
$limit       = isset($_GET['limit'])       ? min((int)$_GET['limit'], 500)                         : 100;

$where = [];
if (!empty($search)) {
    $where[] = "(ah.action_type LIKE '%$search%' OR ah.details LIKE '%$search%' OR u.name LIKE '%$search%')";
}
if (!empty($action_type)) {
    $where[] = "ah.action_type = '$action_type'";
}
if ($user_id > 0) {
    $where[] = "ah.user_id = $user_id";
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT 
        ah.id, ah.user_id, ah.action_type, ah.details, ah.created_at,
        u.name AS user_name, u.email AS user_email, u.blood_group
    FROM activity_history ah
    LEFT JOIN users u ON u.user_id = ah.user_id
    $where_sql
    ORDER BY ah.created_at DESC
    LIMIT $limit
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(["status" => "error", "message" => $conn->error]);
    exit;
}

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

// Also return distinct action types for filter dropdown
$types_result = $conn->query("SELECT DISTINCT action_type FROM activity_history ORDER BY action_type");
$action_types = [];
while ($row = $types_result->fetch_assoc()) {
    $action_types[] = $row['action_type'];
}

echo json_encode([
    "status" => "success",
    "data"   => $logs,
    "action_types" => $action_types
]);
$conn->close();
?>
