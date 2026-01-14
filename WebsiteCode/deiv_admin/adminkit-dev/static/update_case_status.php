<?php
// update_case_status.php
session_start();

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['User_id']) || $_SESSION['role'] !== 'Admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get the absolute path to config folder
$config_path = dirname(dirname(__DIR__)) . '/config/db.php';
include $config_path;

header('Content-Type: application/json');

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        if (!isset($_POST['case_id']) || !isset($_POST['status'])) {
            throw new Exception('Missing required parameters');
        }

        $case_id = (int)$_POST['case_id'];
        $status = trim($_POST['status']);
        
        // Validate status value
        $valid_statuses = ['In Progress', 'Complete', 'Closed', 'Pending'];
        if (!in_array($status, $valid_statuses)) {
            throw new Exception('Invalid status value');
        }

        // Update the case status
        $stmt = $pdo->prepare("UPDATE case_table SET status = ?, updated_at = NOW() WHERE Case_id = ?");
        $stmt->execute([$status, $case_id]);

        if ($stmt->rowCount() > 0) {
            // Log the action in audit logs if you have that table
            $user_id = $_SESSION['User_id'];
            $action = "Updated case #$case_id status to $status";
            
            // Log to audit_logs table if it exists
            try {
                $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
                $log_stmt->execute([$user_id, $action]);
            } catch (Exception $e) {
                // Ignore if audit_logs table doesn't exist
                error_log("Audit log error: " . $e->getMessage());
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Case status updated successfully',
                'new_status' => $status
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Case not found or no changes made']);
        }
    } catch (Exception $e) {
        error_log("Case status update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}