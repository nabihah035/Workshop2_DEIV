<?php
session_start();
$config_path = dirname(dirname(__DIR__)) . '/config/db.php';
include $config_path;

if (!isset($_POST['email'])) {
    echo "error: Missing email";
    exit;
}

$email = $_POST['email'];
$action = $_POST['action'] ?? 'Verify';

// Validate action
$allowed_actions = ['Upload', 'Verify', 'Delete'];
if (!in_array($action, $allowed_actions)) {
    $action = 'Verify';
}

try {
    $admin_id = $_SESSION['User_id'] ?? 1;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $pdo->prepare("
        INSERT INTO audit_trail (action, User_id, ip_address, date_time) 
        VALUES (:action, :user_id, :ip_address, NOW())
    ");
    $stmt->execute(['action' => $action, 'user_id' => $admin_id, 'ip_address' => $ip_address]);
    
    echo "success: Logged";
} catch (PDOException $e) {
    echo "error: " . $e->getMessage();
}
?>