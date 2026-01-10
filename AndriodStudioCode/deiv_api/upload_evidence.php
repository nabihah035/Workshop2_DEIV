<?php
session_start();
include "db_connect.php";
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

// For Android app, we should primarily get user_id from POST
// Session is more for web-based login
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

// For web compatibility, you can also check session
if ($user_id <= 0 && isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
}

// Final validation
if ($user_id <= 0) {
    echo json_encode([
        "status" => "error", 
        "message" => "User not authenticated. Please login again."
    ]);
    exit;
}

// Validate other required fields
$file_name = $_POST['file_name'] ?? '';
$upload_date = $_POST['upload_date'] ?? date('Y-m-d'); // Changed to date format
$status = $_POST['status'] ?? 'Pending';
$hash_value = $_POST['hash_value'] ?? '';
$case_id = intval($_POST['case_id'] ?? 0);

// Additional validation
if (empty($file_name) || $case_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Missing required fields: file_name or case_id"]);
    exit;
}

// Validate status is one of the allowed values
$allowed_statuses = ['Verified', 'Tampered', 'Pending'];
if (!in_array($status, $allowed_statuses)) {
    $status = 'Pending'; // Default to Pending
}

// Validate hash_value format and length (max 200 chars in DB)
if (!empty($hash_value)) {
    // Ensure it's a valid hex string
    if (!preg_match('/^[a-f0-9]+$/i', $hash_value)) {
        echo json_encode(["status" => "error", "message" => "Invalid hash format"]);
        exit;
    }
    // Truncate if too long for database
    if (strlen($hash_value) > 200) {
        $hash_value = substr($hash_value, 0, 200);
    }
}

// Get client IP address - handle IPv6 addresses properly
$ip_address = $_SERVER['REMOTE_ADDR'];
// Truncate IP address if too long for your database column
if (strlen($ip_address) > 45) {
    $ip_address = substr($ip_address, 0, 45);
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // First, verify the user exists and is active (optional security check)
    $user_check = $conn->prepare("SELECT User_id, username FROM user WHERE User_id = ? AND status = 'Active'");
    $user_check->bind_param("i", $user_id);
    $user_check->execute();
    $user_result = $user_check->get_result();
    
    if ($user_result->num_rows == 0) {
        throw new Exception("User not found or inactive");
    }
    $user = $user_result->fetch_assoc();
    $username = $user['username'];
    $user_check->close();
    
    // Verify the case exists and get case name
    $case_check = $conn->prepare("SELECT Case_id, case_name FROM case_table WHERE Case_id = ?");
    $case_check->bind_param("i", $case_id);
    $case_check->execute();
    $case_result = $case_check->get_result();
    
    if ($case_result->num_rows == 0) {
        throw new Exception("Case not found");
    }
    $case = $case_result->fetch_assoc();
    $case_name = $case['case_name'];
    $case_check->close();
    
    // Insert into evidence table - upload_date should be date format
    $stmt = $conn->prepare("INSERT INTO evidence (file_name, upload_date, status, hash_value, Case_id) VALUES (?, ?, ?, ?, ?)");
    
    // Convert upload_date to proper date format if needed
    $upload_date_formatted = date('Y-m-d', strtotime($upload_date));
    
    $stmt->bind_param("ssssi", $file_name, $upload_date_formatted, $status, $hash_value, $case_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert evidence: " . $conn->error);
    }
    
    $evidence_id = $stmt->insert_id;
    $stmt->close();
    
    // Create audit trail - action must be one of: Upload, Verify, Delete, View
    $action = "Upload";
    $audit_stmt = $conn->prepare("INSERT INTO audit_trail (action, date_time, ip_address, User_id, Evidence_id, Case_id) VALUES (?, NOW(), ?, ?, ?, ?)");
    $audit_stmt->bind_param("ssiii", $action, $ip_address, $user_id, $evidence_id, $case_id);

    if (!$audit_stmt->execute()) {
        error_log("Audit trail error: " . $conn->error);
        // Continue even if audit fails
    }
    
    $audit_stmt->close();
    
    // ============================================
    // ENHANCED NOTIFICATION SYSTEM
    // ============================================
    
    // 1. Create notification for the user who uploaded the evidence
    $message_to_uploader = "You have successfully uploaded evidence: '$file_name' for case '$case_name'";
    $notification_stmt = $conn->prepare("INSERT INTO notification (message, status, date, User_id, Evidence_id, Case_id) VALUES (?, 'Unread', CURDATE(), ?, ?, ?)");
    $notification_stmt->bind_param("siii", $message_to_uploader, $user_id, $evidence_id, $case_id);
    
    if (!$notification_stmt->execute()) {
        error_log("Notification creation error: " . $conn->error);
        // Continue even if notification fails
    }
    $notification_stmt->close();
    $conn->commit();
    
    // Return success response
    echo json_encode([
        "status" => "success",
        "message" => "Evidence uploaded successfully",
        "evidence_id" => $evidence_id,
        "file_name" => $file_name,
        "upload_date" => $upload_date_formatted,
        "case_id" => $case_id,
        "user_id" => $user_id,
        "case_name" => $case_name,
        "notifications_created" => true
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn) {
        $conn->rollback();
    }
    
    error_log("Evidence upload error: " . $e->getMessage());
    
    echo json_encode([
        "status" => "error", 
        "message" => "Upload failed: " . $e->getMessage()
    ]);
}

if (isset($conn) && $conn) {
    $conn->close();
}
?>