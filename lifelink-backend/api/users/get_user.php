<?php

include '../../config/db_config.php';


$uid = isset($_GET['uid']) ? trim($_GET['uid']) : '';

if (empty($uid)) {
    echo json_encode(["status" => "error", "message" => "UID missing"]);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM users WHERE firebase_uid = ?");
$stmt->bind_param("s", $uid);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    $user['role_id'] = (int)$user['role_id'];
    echo json_encode(["status" => "success", "user" => $user]);
} else {
    echo json_encode(["status" => "error", "message" => "User not found"]);
}

$stmt->close();
$conn->close();
?>