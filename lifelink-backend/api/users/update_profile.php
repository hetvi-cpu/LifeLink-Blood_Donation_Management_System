<?php
/**
 * update_profile.php
 * Updates editable user profile fields in the users table.
 * Accepts: firebase_uid, name, phone_number, gender, date_of_birth, location, pincode
 */

include '../../config/db_config.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "POST required"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$uid      = trim($data['firebase_uid'] ?? '');
$name     = trim($data['name']         ?? '');
$phone    = trim($data['phone_number'] ?? '');
$gender   = trim($data['gender']       ?? '');
$dob      = trim($data['date_of_birth'] ?? '');
$location = trim($data['location']     ?? '');
$pincode  = trim($data['pincode']      ?? '');

if (!$uid) {
    echo json_encode(["status" => "error", "message" => "firebase_uid is required"]);
    exit;
}

// Validate phone
if ($phone && !preg_match('/^\d{10}$/', $phone)) {
    echo json_encode(["status" => "error", "message" => "Phone must be 10 digits"]);
    exit;
}

// Validate pincode
if ($pincode && !preg_match('/^\d{6}$/', $pincode)) {
    echo json_encode(["status" => "error", "message" => "Pincode must be 6 digits"]);
    exit;
}

$sql = "UPDATE users SET
    name          = ?,
    phone_number  = ?,
    gender        = ?,
    date_of_birth = ?,
    location      = ?,
    pincode       = ?
WHERE firebase_uid = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "DB prepare error: " . $conn->error]);
    exit;
}

$dobVal = $dob ?: null;
$stmt->bind_param("sssssss", $name, $phone, $gender, $dobVal, $location, $pincode, $uid);

if ($stmt->execute()) {
    if ($stmt->affected_rows >= 0) {
        echo json_encode(["status" => "success", "message" => "Profile updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "User not found"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Update failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
