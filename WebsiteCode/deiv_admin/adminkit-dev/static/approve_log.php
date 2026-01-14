<?php
// approve_log.php
session_start();
require_once dirname(dirname(__DIR__)) . '/config/db.php';

$email = $_POST['email'] ?? '';
$action = $_POST['action'] ?? 'Verify';
$admin_id = $_SESSION['User_id'] ?? 1;
$ip_address = $_SERVER['REMOTE_ADDR'];

try {
    $stmt = $conn->prepare("INSERT INTO audit_trail (action, User_id, ip_address) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $action, $admin_id, $ip_address);
    
    if ($stmt->execute()) {
        echo "success: Approval logged for " . htmlspecialchars($email);
    } else {
        echo "error: " . $stmt->error;
    }
} catch (Exception $e) {
    echo "error: " . $e->getMessage();
}
?>