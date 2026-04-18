<?php
include '../../config/db_config.php';

$uid = $_GET['uid'] ?? '';

if (!$uid) {
    echo json_encode(["status" => "error", "message" => "UID is required"]);
    exit;
}

// Search in the 'admin' table
$sql = "SELECT * FROM admin WHERE firebase_uid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $uid);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    echo json_encode(["status" => "success", "user" => $user]);
} else {
    echo json_encode(["status" => "error", "message" => "Admin profile not found"]);
}

$stmt->close();
$conn->close();
?>