<?php
session_start();

if (!isset($_SESSION['User_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: user_management.php");
    exit;
}

require_once __DIR__ . '/../../config/db.php';

if (!isset($_GET['id'])) {
    header("Location: user_management.php");
    exit;
}

$userId = $_GET['id'];

try {
    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM user WHERE User_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error'] = "User not found!";
        header("Location: user_management.php");
        exit;
    }
    
    // Update user status
    $updateStmt = $pdo->prepare("UPDATE user SET status = 'Active' WHERE User_id = ?");
    $updateStmt->execute([$userId]);
    
    // Log to audit_trail
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO audit_trail (action, ip_address, User_id) 
            VALUES (?, ?, ?)
        ");
        $logStmt->execute([
            'Upload',  // Using 'Upload' for approval action
            $_SERVER['REMOTE_ADDR'],
            $_SESSION['User_id']
        ]);
    } catch (PDOException $e) {
        // Log error but don't stop approval
        error_log("Audit trail failed: " . $e->getMessage());
    }
    
    // Firebase notification (optional)
    if (file_exists(__DIR__ . '/../../config/firebase.php')) {
        require_once __DIR__ . '/../../config/firebase.php';
        try {
            $firebase = new FirebaseNotification();
            // Send notification code here
        } catch (Exception $e) {
            error_log("Firebase notification failed: " . $e->getMessage());
        }
    }
    
    $_SESSION['success'] = "User '{$user['username']}' approved successfully!";
    
} catch(PDOException $e) {
    $_SESSION['error'] = "Error approving user: " . $e->getMessage();
}

header("Location: user_management.php");
exit;
?>