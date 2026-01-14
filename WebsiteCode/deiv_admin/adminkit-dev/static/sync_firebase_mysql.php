<?php
require_once dirname(dirname(__DIR__)) . '/config/db.php';

// This would be called from Firebase Cloud Functions or a cron job
// For now, we'll create a manual sync endpoint

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['email']) || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing email or status']);
        exit();
    }
    
    $email = $data['email'];
    $status = $data['status']; // 'Active', 'Rejected', etc.
    
    // Check if user exists in MySQL
    $check_stmt = $pdo->prepare("SELECT User_id FROM user WHERE email = ?");
    $check_stmt->execute([$email]);
    $user = $check_stmt->fetch();
    
    if ($user) {
        // Update existing user
        $update_stmt = $pdo->prepare("UPDATE user SET status = ? WHERE email = ?");
        $update_stmt->execute([$status, $email]);
        echo json_encode(['success' => true, 'message' => 'User status updated']);
    } else {
        // Create new user (with minimal data)
        $username = strstr($email, '@', true);
        $insert_stmt = $pdo->prepare("
            INSERT INTO user (username, email, status, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $insert_stmt->execute([$username, $email, $status]);
        echo json_encode(['success' => true, 'message' => 'User created with status']);
    }
}
?>