<?php
include '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "No data received"]);
    exit;
}

$f_uid     = $data['firebase_uid']     ?? null;
$p_name    = $data['patient_name']     ?? '';
$p_age     = (int)($data['patient_age']    ?? 0);
$gender    = $data['gender']           ?? '';
$bg        = $data['blood_group']      ?? '';
$units     = (int)($data['units_required'] ?? 1);
$d_type    = $data['donation_type']    ?? 'Whole Blood';
$urgency   = $data['urgency_level']    ?? 'Normal';
$h_name    = $data['hospital_name']    ?? '';
$h_address = $data['hospital_address'] ?? '';
$city      = $data['city']             ?? '';
$pincode   = $data['pincode']          ?? '';
$c_person  = $data['contact_person']   ?? '';
$rel       = $data['relationship']     ?? '';
$mobile    = $data['contact_mobile']   ?? '';
$email     = $data['contact_email']    ?? '';
$req_date  = $data['required_date']    ?? date('Y-m-d');

if (!$f_uid || !$bg || !$h_name) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

// Bug #12 fix: fully prepared statement
$sql = "INSERT INTO blood_requests (
            firebase_uid, patient_name, patient_age, gender, blood_group,
            units_required, donation_type, urgency_level, hospital_name,
            hospital_address, city, pincode, contact_person, relationship,
            contact_mobile, contact_email, required_date, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
    exit;
}

$stmt->bind_param(
    "ssississsssssssss",
    $f_uid, $p_name, $p_age, $gender, $bg,
    $units, $d_type, $urgency, $h_name,
    $h_address, $city, $pincode, $c_person, $rel,
    $mobile, $email, $req_date
);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Request created!", "id" => $conn->insert_id]);
} else {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>