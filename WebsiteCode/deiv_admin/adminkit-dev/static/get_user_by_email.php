<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'user' => null, 'message' => '', 'found' => false];

try {
    $email = $_GET['email'] ?? '';
    if (!$email) throw new Exception('Email required');
    
    require_once dirname(dirname(__DIR__)) . '/config/db.php';
    
    // Debug: Log the search
    error_log("Searching MySQL for email: " . $email);
    
    $stmt = $pdo->prepare("SELECT * FROM user WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $response['success'] = true;
        $response['found'] = true;
        $response['user'] = $user;
        $response['message'] = 'User found in MySQL';
        
        // Debug: Log user data
        error_log("MySQL user found: " . print_r($user, true));
    } else {
        $response['success'] = false;
        $response['found'] = false;
        $response['message'] = 'User not found in MySQL database';
        
        // Debug: Log not found
        error_log("User NOT found in MySQL for email: " . $email);
        
        // Don't return empty user - let frontend handle it
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log("Error in get_user_by_email.php: " . $e->getMessage());
}

echo json_encode($response);
?>