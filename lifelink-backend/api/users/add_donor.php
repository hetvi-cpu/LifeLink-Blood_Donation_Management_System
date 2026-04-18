<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include '../../config/db_config.php';

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Using firebase_uid to match your current table schema
    $f_uid = $data['firebase_uid'] ?? null;
    // user_id is still needed specifically for the activity_history table
    $user_id = $data['user_id'] ?? null; 
    $weight = $data['weight'] ?? 0;

    // Mapping Yes/No or Boolean to 1/0
    $donated_recent  = ($data['donated_recent'] ?? false) ? 1 : 0;
    $has_anemia      = ($data['has_anemia'] ?? false) ? 1 : 0;
    $recent_infection = ($data['recent_infection'] ?? false) ? 1 : 0;
    $is_smoker       = ($data['is_smoker'] ?? false) ? 1 : 0;
    $consumes_alcohol = ($data['consumes_alcohol'] ?? false) ? 1 : 0;
    $recent_tattoo    = ($data['recent_tattoo'] ?? false) ? 1 : 0;
    $recent_surgery   = ($data['recent_surgery'] ?? false) ? 1 : 0;

    if (!$f_uid) {
        echo json_encode(["status" => "error", "message" => "Firebase UID missing"]);
        exit;
    }

    // 1. Check if this user already has a donor record
    $checkStmt = $conn->prepare("SELECT donor_id FROM donors WHERE firebase_uid = ? LIMIT 1");
    $checkStmt->bind_param("s", $f_uid);
    $checkStmt->execute();
    $checkStmt->bind_result($existing_donor_id);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($existing_donor_id) {
        // UPDATE the existing row for this user
        $sql = "UPDATE donors SET
                    weight            = ?,
                    donated_recent    = ?,
                    has_anemia        = ?,
                    recent_infection  = ?,
                    is_smoker         = ?,
                    consumes_alcohol  = ?,
                    recent_tattoo     = ?,
                    recent_surgery    = ?,
                    registration_date = NOW()
                WHERE donor_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "iiiiiiiii",
            $weight, $donated_recent, $has_anemia,
            $recent_infection, $is_smoker, $consumes_alcohol,
            $recent_tattoo, $recent_surgery,
            $existing_donor_id
        );
    } else {
        // INSERT a new row for this user
        $sql = "INSERT INTO donors (
                    firebase_uid, weight, donated_recent, has_anemia,
                    recent_infection, is_smoker, consumes_alcohol,
                    recent_tattoo, recent_surgery, registration_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "siiiiiiii",
            $f_uid, $weight, $donated_recent, $has_anemia,
            $recent_infection, $is_smoker, $consumes_alcohol,
            $recent_tattoo, $recent_surgery
        );
    }

    if ($stmt->execute()) {
        // 2. Log activity in history (Requires numeric user_id)
        if ($user_id) {
            $history_sql = "INSERT INTO activity_history (user_id, action_type, details) 
                            VALUES (?, 'Donor Registration', 'Successfully registered as an active donor.')";
            $hist_stmt = $conn->prepare($history_sql);
            if ($hist_stmt) {
                $hist_stmt->bind_param("i", $user_id);
                $hist_stmt->execute();
            }
        }
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $stmt->error]);
    }
}
$conn->close();
?>