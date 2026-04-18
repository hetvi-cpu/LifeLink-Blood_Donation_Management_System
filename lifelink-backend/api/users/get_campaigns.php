<?php
include '../../config/db_config.php';

// Set headers for JSON response and CORS
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // We only fetch 'Active' and 'Upcoming' campaigns to show to users
    $sql = "SELECT * FROM campaigns WHERE status != 'Completed' ORDER BY created_at DESC";
    
    $result = $conn->query($sql);

    if ($result) {
        $campaigns = [];
        
        while ($row = $result->fetch_assoc()) {
            // Optional: If you want to convert the facilities string into an array 
            // for easier mapping in React, you can do it here or in the frontend.
            $campaigns[] = $row;
        }

        echo json_encode([
            "status" => "success",
            "count" => count($campaigns),
            "data" => $campaigns
        ]);
    } else {
        echo json_encode([
            "status" => "error", 
            "message" => "Database query failed: " . $conn->error
        ]);
    }
} else {
    echo json_encode([
        "status" => "error", 
        "message" => "Invalid request method"
    ]);
}

$conn->close();
?>