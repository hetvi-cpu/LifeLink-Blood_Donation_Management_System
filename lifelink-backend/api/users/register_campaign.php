<?php
include '../../config/db_config.php';

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f_uid   = $data['firebase_uid'] ?? null;
    $name    = $data['full_name'] ?? '';
    $phone   = $data['phone_number'] ?? '';
    $bg      = $data['blood_group'] ?? '';
    $age     = $data['age'] ?? 0;
    $camp    = $data['campaign_name'] ?? 'General Campaign';

    if (!$f_uid) {
        echo json_encode(["status" => "error", "message" => "User not authenticated"]);
        exit;
    }

    $sql = "INSERT INTO campaign_registrations (firebase_uid, campaign_name, full_name, phone_number, blood_group, age) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("sssssi", $f_uid, $camp, $name, $phone, $bg, $age);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Successfully registered for the campaign"]);
        } else {
            echo json_encode(["status" => "error", "message" => $stmt->error]);
        }
    }
}
$conn->close();
?>