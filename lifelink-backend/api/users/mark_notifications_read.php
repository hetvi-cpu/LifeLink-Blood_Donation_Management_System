<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "POST required"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$uid  = isset($data['uid']) ? trim($data['uid']) : '';

if (empty($uid)) {
    echo json_encode(["status" => "error", "message" => "uid is required"]);
    exit;
}

// Get user_id from firebase_uid
$uStmt = $conn->prepare("SELECT user_id FROM users WHERE firebase_uid = ?");
$uStmt->bind_param("s", $uid);
$uStmt->execute();
$uRow = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

if (!$uRow) {
    echo json_encode(["status" => "error", "message" => "User not found"]);
    exit;
}
$user_id = (int)$uRow['user_id'];

// Mark all read
if (!empty($data['all'])) {
    $conn->query("UPDATE notifications SET status = 'Read' WHERE user_id = $user_id");
    echo json_encode(["status" => "success", "message" => "All marked read"]);
    $conn->close();
    exit;
}

// Mark specific IDs read
if (!empty($data['ids']) && is_array($data['ids'])) {
    // Strip 'db_' prefix from frontend IDs to get actual notification_id
    $ids = array_map(function($id) {
        return (int)str_replace('db_', '', $id);
    }, $data['ids']);
    $ids = array_filter($ids); // remove zeros

    if (!empty($ids)) {
        $placeholders = implode(',', $ids);
        $conn->query("UPDATE notifications SET status = 'Read' WHERE user_id = $user_id AND notification_id IN ($placeholders)");
    }
    echo json_encode(["status" => "success", "message" => "Marked read"]);
    $conn->close();
    exit;
}

echo json_encode(["status" => "error", "message" => "Provide 'all' or 'ids'"]);
$conn->close();
?>
