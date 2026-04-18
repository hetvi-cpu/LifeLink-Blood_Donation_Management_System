<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!empty($data['uid'])) {
    // Clear by uid (single token)
    $stmt = $conn->prepare("UPDATE users SET fcm_token = NULL WHERE firebase_uid = ?");
    $stmt->bind_param('s', $data['uid']);
} elseif (!empty($data['fcm_token'])) {
    // Clear by token value (multicast)
    $stmt = $conn->prepare("UPDATE users SET fcm_token = NULL WHERE fcm_token = ?");
    $stmt->bind_param('s', $data['fcm_token']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'uid or fcm_token required']);
    exit;
}

$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
$conn->close();

echo json_encode(['status' => 'success', 'cleared' => $affected]);
?>
