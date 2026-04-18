<?php
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

// Total users (role_id = 2)
$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role_id = 2");
$total_users = $result->fetch_assoc()['total'];

// Total registered donors
$result = $conn->query("SELECT COUNT(*) AS total FROM donors");
$total_donors = $result->fetch_assoc()['total'];

// Active campaigns (Upcoming or Active)
$result = $conn->query("SELECT COUNT(*) AS total FROM campaigns WHERE status IN ('Upcoming', 'Active')");
$active_campaigns = $result->fetch_assoc()['total'];

// Active emergency alerts (not fulfilled)
$result = $conn->query("SELECT COUNT(*) AS total FROM emergency_alerts WHERE is_fulfilled = 0");
$emergency_alerts = $result->fetch_assoc()['total'];

// Pending blood requests
$result = $conn->query("SELECT COUNT(*) AS total FROM blood_requests WHERE status = 'Pending'");
$pending_requests = $result->fetch_assoc()['total'];

// Fulfilled donations this month
$result = $conn->query("SELECT COUNT(*) AS total FROM blood_requests WHERE status = 'Fulfilled' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$monthly_donations = $result->fetch_assoc()['total'];

echo json_encode([
    "status" => "success",
    "data" => [
        "total_users"       => (int)$total_users,
        "total_donors"      => (int)$total_donors,
        "active_campaigns"  => (int)$active_campaigns,
        "emergency_alerts"  => (int)$emergency_alerts,
        "pending_requests"  => (int)$pending_requests,
        "monthly_donations" => (int)$monthly_donations
    ]
]);

$conn->close();
?>
