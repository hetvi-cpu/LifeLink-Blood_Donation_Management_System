<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$data        = json_decode(file_get_contents("php://input"), true);
$hospital_id = isset($data['hospital_id']) ? (int)$data['hospital_id'] : 0;

if (!$hospital_id) {
    echo json_encode(["status" => "error", "message" => "hospital_id is required"]);
    exit;
}

// Build dynamic update — only update provided fields
$allowed_string_fields = ['hospital_name', 'address', 'phone_number', 'email'];
$allowed_enum_fields   = [
    'hospital_type' => ['Govt', 'Private', 'Trust'],
    'blood_stock'   => ['High', 'Moderate', 'Low', 'Critical'],
];

$fields = []; $types = ""; $vals = [];

foreach ($allowed_string_fields as $col) {
    if (isset($data[$col])) {
        $fields[] = "$col = ?";
        $types   .= "s";
        $vals[]   = trim($data[$col]);
    }
}

foreach ($allowed_enum_fields as $col => $allowed) {
    if (isset($data[$col]) && in_array($data[$col], $allowed)) {
        $fields[] = "$col = ?";
        $types   .= "s";
        $vals[]   = $data[$col];
    }
}

if (empty($fields)) {
    echo json_encode(["status" => "error", "message" => "No valid fields to update"]);
    exit;
}

$types .= "i";
$vals[] = $hospital_id;

$stmt = $conn->prepare("UPDATE hospital_details SET " . implode(", ", $fields) . " WHERE hospital_id = ?");
$stmt->bind_param($types, ...$vals);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(["status" => "success", "message" => "Hospital updated"]);
} else {
    echo json_encode(["status" => "error", "message" => $conn->error ?: "Hospital not found or no changes made"]);
}

$stmt->close();
$conn->close();
?>
