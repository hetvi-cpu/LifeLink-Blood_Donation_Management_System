<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

// Required fields
foreach (['hospital_name', 'address', 'phone_number'] as $field) {
    if (empty($data[$field])) {
        echo json_encode(["status" => "error", "message" => "Missing required field: $field"]);
        exit;
    }
}

$hospital_name = trim($data['hospital_name']);
$address       = trim($data['address']);
$phone_number  = trim($data['phone_number']);
$email         = isset($data['email'])         ? trim($data['email'])         : null;
$hospital_type = isset($data['hospital_type']) && in_array($data['hospital_type'], ['Govt','Private','Trust'])
                 ? $data['hospital_type'] : 'Govt';
$blood_stock   = isset($data['blood_stock'])   && in_array($data['blood_stock'], ['High','Moderate','Low','Critical'])
                 ? $data['blood_stock']   : 'Moderate';

$stmt = $conn->prepare("
    INSERT INTO hospital_details (hospital_name, address, phone_number, email, hospital_type, blood_stock)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("ssssss", $hospital_name, $address, $phone_number, $email, $hospital_type, $blood_stock);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Hospital added", "hospital_id" => $conn->insert_id]);
} else {
    if ($conn->errno === 1062) {
        echo json_encode(["status" => "error", "message" => "Hospital name or phone already exists"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
}

$stmt->close();
$conn->close();
?>
