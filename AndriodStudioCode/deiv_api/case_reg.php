<?php
session_start();
include "db_connect.php";
header("Content-Type: application/json");

// Get POST data
$case_name = isset($_POST['case_name']) ? trim($_POST['case_name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : 'Pending';
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

// Validation
if (empty($case_name) || empty($description) || $user_id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "All fields are required"
    ]);
    exit;
}

// Validate status
$allowed_status = ['In Progress', 'Complete', 'Closed', 'Pending'];
if (!in_array($status, $allowed_status)) {
    $status = 'Pending';
}

try {
    // Insert new case
    $stmt = $conn->prepare("INSERT INTO `case` (case_name, description, status, User_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $case_name, $description, $status, $user_id);
    
    if ($stmt->execute()) {
        $new_case_id = $stmt->insert_id;
        
        echo json_encode([
            "status" => "success",
            "message" => "Case created successfully",
            "case_id" => $new_case_id,
            "case_name" => $case_name
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to create case"
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}

$conn->close();
?>