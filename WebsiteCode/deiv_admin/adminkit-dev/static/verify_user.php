<?php
// verify_user.php
session_start();
require_once dirname(dirname(__DIR__)) . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method");
}

if (!isset($_SESSION['User_id'])) {
    die("Not authenticated");
}

$action = $_POST['action'] ?? '';
$user_id = $_POST['user_id'] ?? '';
$email = $_POST['email'] ?? '';
$description = $_POST['description'] ?? 'User account verification';

if ($action !== 'verify') {
    die("Invalid action");
}

try {
    // Get admin ID
    $admin_id = $_SESSION['User_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Insert into audit_trail
    $stmt = $conn->prepare("INSERT INTO audit_trail (action, User_id, ip_address) VALUES (?, ?, ?)");
    $action_type = 'Verify'; // Using the exact enum value
    $stmt->bind_param("sis", $action_type, $admin_id, $ip_address);
    
    if ($stmt->execute()) {
        // Also update MySQL users table if it exists
        $update_stmt = $conn->prepare("UPDATE users SET status = 'verified', verified_at = NOW() WHERE email = ?");
        $update_stmt->bind_param("s", $email);
        $update_stmt->execute();
        
        echo "success: Verification logged to audit trail and user status updated.";
    } else {
        echo "error: " . $stmt->error;
    }
} catch (Exception $e) {
    echo "error: " . $e->getMessage();
}
?>