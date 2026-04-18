<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "POST required"]);
    exit;
}

$data      = json_decode(file_get_contents("php://input"), true);
$uid       = isset($data['uid'])       ? trim($data['uid'])       : '';
$fcm_token = isset($data['fcm_token']) ? trim($data['fcm_token']) : '';

if (empty($uid) || empty($fcm_token)) {
    echo json_encode(["status" => "error", "message" => "uid and fcm_token are required"]);
    exit;
}

$stmt = $conn->prepare("UPDATE users SET fcm_token = ? WHERE firebase_uid = ?");
$stmt->bind_param("ss", $fcm_token, $uid);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "FCM token saved"]);
} else {
    echo json_encode(["status" => "error", "message" => $conn->error]);
}

$stmt->close();
$conn->close();
?>