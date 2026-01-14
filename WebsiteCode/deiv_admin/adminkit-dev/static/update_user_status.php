<?php
session_start();
$config_path = dirname(dirname(__DIR__)) . '/config/db.php';
include $config_path;

if (!isset($_POST['user_id']) || !isset($_POST['email']) || !isset($_POST['status'])) {
    echo "error: Missing parameters";
    exit;
}

$user_id = $_POST['user_id'];
$email = $_POST['email'];
$status = ucfirst(strtolower($_POST['status']));

try {
    $stmt = $pdo->prepare("UPDATE user SET status = :status WHERE User_id = :user_id OR email = :email");
    $stmt->execute(['status' => $status, 'user_id' => $user_id, 'email' => $email]);
    
    if ($stmt->rowCount() > 0) {
        $admin_id = $_SESSION['User_id'] ?? 1;
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $audit_stmt = $pdo->prepare("
            INSERT INTO audit_trail (action, User_id, ip_address, date_time) 
            VALUES ('Verify', :user_id, :ip_address, NOW())
        ");
        $audit_stmt->execute(['user_id' => $admin_id, 'ip_address' => $ip_address]);
        
        echo "success: Status updated to $status";
    } else {
        echo "warning: No user found";
    }
} catch (PDOException $e) {
    echo "error: " . $e->getMessage();
}
?>