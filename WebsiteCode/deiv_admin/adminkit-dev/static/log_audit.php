<?php
session_start();
require_once dirname(dirname(__DIR__)) . '/config/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method. Only POST allowed.']);
    exit;
}

// Get POST data
$input = $_POST;
if (empty($_POST) && !empty(file_get_contents('php://input'))) {
    $input = json_decode(file_get_contents('php://input'), true);
}

// Extract parameters based on your table structure
$action = trim($input['action'] ?? '');
$user_id = $input['User_id'] ?? $input['user_id'] ?? null;
$case_id = $input['Case_id'] ?? $input['case_id'] ?? null;
$evidence_id = $input['Evidence_id'] ?? $input['evidence_id'] ?? null;
$admin_id = $input['admin_id'] ?? ($_SESSION['User_id'] ?? 1);

// Validate required fields
if (empty($action)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameter: action']);
    exit;
}

// Map and validate action against your ENUM
$action = trim($action);
$action = ucfirst(strtolower($action));

// Your allowed actions from ENUM
$allowed_actions = ['Upload', 'Verify', 'Delete', 'Reject', 'Approve'];

// Check if action is valid
if (!in_array($action, $allowed_actions)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid action type',
        'received' => $action,
        'allowed' => $allowed_actions,
        'suggestion' => 'Action must be one of: ' . implode(', ', $allowed_actions)
    ]);
    exit;
}

// Get client IP address
function getRealIPAddress() {
    // Check for shared internet/ISP IP
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    // Check for IPs passing through proxies
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ip_list[0]);
    }
    // Check for the remote address
    else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    // Validate IP format
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    
    return 'unknown';
}

$ip_address = getRealIPAddress();

try {
    // Check database connection
    if (!$conn || $conn->connect_error) {
        throw new Exception('Database connection failed: ' . ($conn->connect_error ?? 'Unknown error'));
    }
    
    // Prepare SQL statement matching your table structure
    $sql = "INSERT INTO audit_trail (action, ip_address, User_id, Case_id, Evidence_id) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    // Bind parameters
    // Types: s=string, i=integer
    $stmt->bind_param(
        "ssiii", 
        $action,        // action (ENUM)
        $ip_address,    // ip_address
        $user_id,       // User_id (can be NULL)
        $case_id,       // Case_id (can be NULL)
        $evidence_id    // Evidence_id (can be NULL)
    );
    
    // Execute
    if ($stmt->execute()) {
        $audit_id = $conn->insert_id;
        
        // Log success for debugging
        error_log("[AUDIT SUCCESS] ID: $audit_id | Action: $action | User_ID: $user_id | Case_ID: $case_id | Evidence_ID: $evidence_id | IP: $ip_address");
        
        echo json_encode([
            'success' => true,
            'audit_id' => $audit_id,
            'action' => $action,
            'user_id' => $user_id,
            'case_id' => $case_id,
            'evidence_id' => $evidence_id,
            'ip_address' => $ip_address,
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => 'Audit trail logged successfully'
        ]);
    } else {
        $error = $stmt->error;
        
        // Check for ENUM error
        if (strpos($error, 'enum') !== false || strpos($error, 'ENUM') !== false) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid action value',
                'message' => 'The action "' . $action . '" is not allowed in the audit_trail.action ENUM',
                'allowed_actions' => $allowed_actions,
                'sql_error' => $error,
                'fix' => 'Run: ALTER TABLE audit_trail MODIFY COLUMN action ENUM(\'' . implode('\',\'', $allowed_actions) . '\') NOT NULL;'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'Database insert failed',
                'message' => $error,
                'sql' => $sql
            ]);
        }
        
        error_log("[AUDIT FAILED] Action: $action | Error: " . $error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server exception',
        'message' => $e->getMessage()
    ]);
    
    error_log("[AUDIT EXCEPTION] " . $e->getMessage());
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}