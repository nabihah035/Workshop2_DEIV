<?php
session_start();
include "db_connect.php";
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

// Get user ID from session OR from POST
$user_id = $_SESSION['user_id'] ?? 0;

// If no session, get from POST
if ($user_id <= 0) {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
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
if (empty($case_name) || empty($description)) {
    echo json_encode([
        "status" => "error",
        "message" => "Case name and description are required"
    ]);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Insert new case
    $case_stmt = $conn->prepare("INSERT INTO case_table (case_name, description, status, User_id) VALUES (?, ?, ?, ?)");
    $case_stmt->bind_param("sssi", $case_name, $description, $status, $user_id);
    
    if (!$case_stmt->execute()) {
        throw new Exception("Failed to create case: " . $conn->error);
    }
    
    $case_id = $conn->insert_id;
    $case_stmt->close();
    
    // Get client IP address for audit trail
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Insert into audit trail with 'Upload' action for case creation
    $audit_stmt = $conn->prepare("INSERT INTO audit_trail (action, date_time, ip_address, User_id, Case_id) VALUES ('Upload', NOW(), ?, ?, ?)");
    $audit_stmt->bind_param("sii", $ip_address, $user_id, $case_id);
    
    if (!$audit_stmt->execute()) {
        error_log("Audit trail error: " . $conn->error);
        throw new Exception("Failed to create audit trail");
    }
    
    $audit_stmt->close();
    
    // Insert notification for case creation
    $message = "New case created: " . $case_name;
    
    $notification_stmt = $conn->prepare("INSERT INTO notification (message, status, date, User_id, Case_id) VALUES (?, 'Unread', CURDATE(), ?, ?)");
    $notification_stmt->bind_param("sii", $message, $user_id, $case_id);
    
    if (!$notification_stmt->execute()) {
        error_log("Notification insert error: " . $conn->error);
        // Don't throw exception for notification failure, just log it
    }
    
    $notification_stmt->close();
     $conn->commit();
    
    echo json_encode([
        "status" => "success",
        "message" => "Case created successfully",
        "case_id" => $case_id,
        "notification_message" => $message
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