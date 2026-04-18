<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$status = isset($_GET['status']) ? $conn->real_escape_string(trim($_GET['status'])) : '';

$where_sql = '';
if (!empty($status) && in_array($status, ['Read', 'Unread'])) {
    $where_sql = "WHERE n.status = '$status'";
}

$sql = "
    SELECT 
        n.notification_id, n.user_id, n.message, n.status,
        n.created_at,
        u.name AS user_name, u.email AS user_email
    FROM notifications n
    LEFT JOIN users u ON u.user_id = n.user_id
    $where_sql
    ORDER BY n.created_at DESC
    LIMIT 200
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(["status" => "error", "message" => $conn->error]);
    exit;
}

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode(["status" => "success", "data" => $notifications]);
$conn->close();
?>
