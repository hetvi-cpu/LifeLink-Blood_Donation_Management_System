<?php
require_once '../../config/db_config.php';

// GET /api/admin/get_dashboard_stats.php
// Returns real-time stats for the admin dashboard cards

$stats = [];

// Total registered users (role_id = 2)
$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role_id = 2");
$stats['total_users'] = $result->fetch_assoc()['total'];

// Active campaigns (Upcoming or Active)
$result = $conn->query("SELECT COUNT(*) AS total FROM campaigns WHERE status IN ('Upcoming','Active')");
$stats['active_campaigns'] = $result->fetch_assoc()['total'];

// Registered blood donors
$result = $conn->query("SELECT COUNT(*) AS total FROM donors");
$stats['total_donors'] = $result->fetch_assoc()['total'];

// Active emergency alerts (unfulfilled)
$result = $conn->query("SELECT COUNT(*) AS total FROM emergency_alerts WHERE is_fulfilled = 0");
$stats['active_emergency_alerts'] = $result->fetch_assoc()['total'];

// Pending blood requests
$result = $conn->query("SELECT COUNT(*) AS total FROM blood_requests WHERE status = 'Pending'");
$stats['pending_requests'] = $result->fetch_assoc()['total'];

// Total donations completed
$result = $conn->query("SELECT COUNT(*) AS total FROM blood_requests WHERE status = 'Fulfilled'");
$stats['total_donations'] = $result->fetch_assoc()['total'];

echo json_encode(["status" => "success", "data" => $stats]);
$conn->close();
?>
