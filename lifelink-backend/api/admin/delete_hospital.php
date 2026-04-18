<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$hospital_id = isset($data['hospital_id']) ? (int)$data['hospital_id'] : 0;

if (!$hospital_id) {
    echo json_encode(["status" => "error", "message" => "hospital_id is required"]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM hospital_details WHERE hospital_id = ?");
$stmt->bind_param("i", $hospital_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(["status" => "success", "message" => "Hospital deleted"]);
} else {
    echo json_encode(["status" => "error", "message" => "Hospital not found"]);
}

$stmt->close();
$conn->close();
?>
