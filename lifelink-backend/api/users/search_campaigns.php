<?php
include '../../config/db_config.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $area = isset($_GET['area']) ? $_GET['area'] : '';
    $type = isset($_GET['type']) ? $_GET['type'] : 'All';
    $blood_group = isset($_GET['blood_group']) ? $_GET['blood_group'] : '';

    // Base Query
    $query = "SELECT * FROM campaigns WHERE status != 'Completed'";

    // Append Filters
    if ($area !== '') {
        $query .= " AND (venue_info LIKE '%$area%' OR organized_by LIKE '%$area%')";
    }
    if ($type !== 'All') {
        $query .= " AND campaign_type = '$type'";
    }
    if ($blood_group !== '') {
        $query .= " AND blood_group_needed LIKE '%$blood_group%'";
    }

    $query .= " ORDER BY created_at DESC";
    
    $result = $conn->query($query);

    if ($result) {
        $campaigns = [];
        while ($row = $result->fetch_assoc()) {
            $campaigns[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $campaigns]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
}
$conn->close();
?>