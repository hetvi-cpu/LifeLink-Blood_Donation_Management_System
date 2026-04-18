<?php
// Include database connection (which handles CORS and Port 3307)
include '../../config/db_config.php';

// Get the raw POST data from React
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mapping the data sent from your Registration.jsx state
    $f_uid   = $data['firebase_uid'] ?? null;
    $name    = $data['name'] ?? '';
    $gender  = $data['gender'] ?? ''; 
    $bg      = $data['blood_group'] ?? ''; 
    $email   = $data['email'] ?? '';
    $phone   = $data['phone_number'] ?? '';
    $dob     = $data['date_of_birth'] ?? '';
    $loc     = $data['location'] ?? '';
    $pincode = $data['pincode'] ?? ''; // NEW: Capturing pincode from React
    $aadhaar = $data['aadhaar_number'] ?? '';
    $role    = isset($data['role_id']) ? intval($data['role_id']) : 2;

    // Basic validation
    if (!$f_uid || !$email) {
        echo json_encode(["status" => "error", "message" => "Missing UID or Email from Firebase"]);
        exit;
    }

    // SQL statement updated to include the 'pincode' column
    $sql = "INSERT INTO users (
                firebase_uid, 
                role_id, 
                name, 
                gender, 
                blood_group, 
                email, 
                phone_number, 
                date_of_birth, 
                aadhaar_number, 
                user_status, 
                location,
                pincode
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?)";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        /**
         * Updated bind_param:
         * "sisssssssss" indicates: 
         * string (uid), int (role), string (name), string (gender), 
         * string (bg), string (email), string (phone), string (dob), 
         * string (aadhaar), string (location), string (pincode)
         */
        $stmt->bind_param(
            "sisssssssss", 
            $f_uid, 
            $role, 
            $name, 
            $gender, 
            $bg, 
            $email, 
            $phone, 
            $dob, 
            $aadhaar, 
            $loc,
            $pincode
        );

        if ($stmt->execute()) {
            echo json_encode([
                "status" => "success", 
                "message" => "User record created successfully in MySQL with Pincode"
            ]);
        } else {
            echo json_encode([
                "status" => "error", 
                "message" => "Database Execution Error: " . $stmt->error
            ]);
        }
        $stmt->close();
    } else {
        echo json_encode([
            "status" => "error", 
            "message" => "SQL Preparation Failed: " . $conn->error
        ]);
    }
} else {
    echo json_encode([
        "status" => "error", 
        "message" => "Invalid Request Method"
    ]);
}

$conn->close();
?>