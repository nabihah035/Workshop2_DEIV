<?php
session_start();

if (!isset($_SESSION['User_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: user_management.php");
    exit;
}

// FIXED: Same path
require_once __DIR__ . '/../../config/db.php';

$firebaseEnabled = false;
if (file_exists(__DIR__ . '/../../config/firebase.php')) {
    require_once __DIR__ . '/../../config/firebase.php';
    $firebaseEnabled = true;
}

if (!isset($_GET['id'])) {
    header("Location: user_management.php");
    exit;
}



$userId = $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM user WHERE User_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error'] = "User not found!";
        header("Location: user_management.php");
        exit;
    }
    
    $updateStmt = $pdo->prepare("UPDATE user SET status = 'Rejected' WHERE User_id = ?");
    $updateStmt->execute([$userId]);
    
    // Log the action
    $logStmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $logStmt->execute([
        $_SESSION['User_id'],
        'USER_REJECTED',
        "Rejected user: {$user['username']} ({$user['email']})",
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
    // Send FCM notification to user
    if ($firebaseEnabled) {
        try {
            $firebase = new FirebaseNotification();
            
            $firebase->sendToTopic(
                'user_' . $userId,
                '❌ Registration Rejected',
                "Your registration has been rejected. Contact admin for details.",
                [
                    'type' => 'registration_rejected',
                    'user_id' => $userId,
                    'status' => 'rejected'
                ]
            );
            
        } catch (Exception $e) {
            error_log("Firebase notification failed: " . $e->getMessage());
        }
    }
    
    $_SESSION['success'] = "User rejected successfully!";
    
} catch(PDOException $e) {
    $_SESSION['error'] = "Error rejecting user: " . $e->getMessage();
}

header("Location: user_management.php");
exit;
?>