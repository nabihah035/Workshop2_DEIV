<?php
session_start();
$config_path = dirname(dirname(__DIR__)) . '/config/db.php';
include $config_path;

if (!isset($_POST['user_id']) && !isset($_POST['email'])) {
    echo "error: Missing parameters";
    exit;
}

$user_id = $_POST['user_id'] ?? null;
$email = $_POST['email'] ?? null;

try {
    $check_stmt = $pdo->prepare("SELECT User_id, email FROM user WHERE User_id = :user_id OR email = :email");
    $check_stmt->execute(['user_id' => $user_id, 'email' => $email]);
    $user = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "not_found: User not found";
        exit;
    }
    
    // Log BEFORE deleting
    $admin_id = $_SESSION['User_id'] ?? 1;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $audit_stmt = $pdo->prepare("
        INSERT INTO audit_trail (action, User_id, ip_address, date_time) 
        VALUES ('Delete', :user_id, :ip_address, NOW())
    ");
    $audit_stmt->execute(['user_id' => $admin_id, 'ip_address' => $ip_address]);
    
    // Now delete
    $delete_stmt = $pdo->prepare("DELETE FROM user WHERE User_id = :user_id");
    $delete_stmt->execute(['user_id' => $user['User_id']]);
    
    echo "success: User deleted";
} catch (PDOException $e) {
    echo "error: " . $e->getMessage();
}
?>