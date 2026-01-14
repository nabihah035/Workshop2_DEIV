<?php
session_start();
include "db_connect.php";
header("Content-Type: application/json");

error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("POST data: " . file_get_contents('php://input'));

// Read JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$case_id = isset($data['case_id']) ? intval($data['case_id']) : 0;
$new_status = isset($data['new_status']) ? $conn->real_escape_string($data['new_status']) : '';
$user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;

// For compatibility, also check session
if ($user_id <= 0 && isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
}

if ($case_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid case ID"]);
    exit;
}

// Validate status
$valid_statuses = ['In Progress', 'Complete', 'Closed', 'Pending'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(["status" => "error", "message" => "Invalid status value"]);
    exit;
}

// Validate user_id
if ($user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "User not authenticated. Please login again."]);
    exit;
}

try {
    // Get old status first for audit trail
    $stmt = $conn->prepare("SELECT status FROM case_table WHERE Case_id = ?");
    $stmt->bind_param("i", $case_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $old_status = $row ? $row['status'] : 'Unknown';
    $stmt->close();
    
    // Update case status
    $stmt = $conn->prepare("UPDATE case_table SET status = ? WHERE Case_id = ?");
    $stmt->bind_param("si", $new_status, $case_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Add to audit trail - Use the correct ENUM value
            $action = "Change Case Status";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            
            $audit_stmt = $conn->prepare(
                "INSERT INTO audit_trail (action, date_time, ip_address, User_id, Case_id) 
                 VALUES (?, NOW(), ?, ?, ?)"
            );
            $audit_stmt->bind_param("ssii", $action, $ip_address, $user_id, $case_id);
            
            if ($audit_stmt->execute()) {
                echo json_encode([
                    "status" => "success", 
                    "message" => "Status updated successfully",
                    "old_status" => $old_status,
                    "new_status" => $new_status
                ]);
            } else {
                // Log the audit trail error but still return success for status update
                error_log("Audit trail error: " . $audit_stmt->error);
                echo json_encode([
                    "status" => "warning", 
                    "message" => "Status updated but audit trail failed",
                    "old_status" => $old_status,
                    "new_status" => $new_status
                ]);
            }
            $audit_stmt->close();
        } else {
            echo json_encode(["status" => "error", "message" => "No case found with that ID or status unchanged"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
}

$conn->close();
?>