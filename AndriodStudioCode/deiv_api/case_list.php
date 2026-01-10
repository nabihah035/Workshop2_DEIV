<?php
session_start();
include "db_connect.php";
header("Content-Type: application/json");

// Get user_id from GET parameter
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid user ID"
    ]);
    exit;
}

// Turn off error reporting for production
error_reporting(0);
ini_set('display_errors', 0);

try {
    /* GET ALL CASES FOR USER */
    $stmt = $conn->prepare("SELECT Case_id, case_name, description, status, created_at FROM `case_table` WHERE User_id = ? ORDER BY Case_id DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $cases = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Format created_at date
            $date = new DateTime($row['created_at']);
            $row['created_at'] = $date->format('m/d/Y');
            
            // Set status color
            $statusColor = "#777777"; // default gray
            switch ($row['status']) {
                case 'In Progress':
                    $statusColor = "#f9a825"; // yellow/orange
                    break;
                case 'Complete':
                    $statusColor = "#2e7d32"; // green
                    break;
                case 'Closed':
                    $statusColor = "#c62828"; // red
                    break;
                case 'Pending':
                    $statusColor = "#ef6c00"; // orange
                    break;
            }
            
            $row['status_color'] = $statusColor;
            $cases[] = $row;
        }
    }

    /* FINAL JSON */
    echo json_encode([
        "status" => "success",
        "user_id" => $user_id,
        "cases" => $cases
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}

$conn->close();
?>