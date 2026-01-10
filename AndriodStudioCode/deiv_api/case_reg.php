<?php
session_start();
include "db_connect.php";
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

// Get user ID from session OR from POST (for debugging)
$user_id = $_SESSION['user_id'] ?? 0;

// DEBUG: Check if session has user_id
if ($user_id <= 0) {
    // If no session, try to get from POST (for debugging purposes)
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    // If still no user_id, return error
    if ($user_id <= 0) {
        echo json_encode(["status" => "error", "message" => "User not authenticated. Please login again."]);
        exit;
    }
}

// Get POST data
$case_name = isset($_POST['case_name']) ? trim($_POST['case_name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : 'Pending';

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

// Get client IP address
$ip_address = $_SERVER['REMOTE_ADDR'];

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Insert new case
    $stmt = $conn->prepare("INSERT INTO `case_table` (case_name, description, status, User_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $case_name, $description, $status, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create case");
    }
    
    $new_case_id = $stmt->insert_id;
    $stmt->close();
    
    // Insert into audit trail with 'Upload' action for case creation
    $audit_stmt = $conn->prepare("INSERT INTO audit_trail (action, date_time, ip_address, User_id, Case_id) VALUES ('Upload', NOW(), ?, ?, ?)");
    $audit_stmt->bind_param("sii", $ip_address, $user_id, $new_case_id);

    if (!$audit_stmt->execute()) {
        error_log("Audit trail error: " . $conn->error); // Log MySQL error
        throw new Exception("Failed to create audit trail: " . $conn->error);
    }
    
    $audit_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        "status" => "success",
        "message" => "Case created successfully",
        "case_id" => $new_case_id,
        "case_name" => $case_name
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}

$conn->close();
?>