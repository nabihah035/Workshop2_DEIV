<?php
// your_project_root/api/register.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/db.php';

// Initialize Firebase if needed
$firebaseEnabled = false;
if (file_exists(__DIR__ . '/../config/firebase.php')) {
    require_once __DIR__ . '/../config/firebase.php';
    $firebaseEnabled = true;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$required = ['username', 'password', 'email', 'first_name', 'last_name', 'role', 'organization'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

try {
    // Check if username or email exists
    $checkStmt = $pdo->prepare("SELECT User_id FROM user WHERE username = ? OR email = ?");
    $checkStmt->execute([$input['username'], $input['email']]);
    
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Username or email already exists']);
        exit;
    }
    
    // Hash password
    $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
    
    // Insert user with Pending status
    $sql = "INSERT INTO user (username, password, email, first_name, last_name, role, organization, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $input['username'],
        $hashedPassword,
        $input['email'],
        $input['first_name'],
        $input['last_name'],
        $input['role'],
        $input['organization']
    ]);
    
    $userId = $pdo->lastInsertId();
    
    // Send notification to admin if Firebase is configured
    if ($firebaseEnabled) {
        try {
            $firebase = new FirebaseNotification();
            
            // Send to admin topic
            $firebase->sendToTopic(
                'admin_notifications',
                '📋 New User Registration',
                "User: {$input['username']}\nEmail: {$input['email']}\nRole: {$input['role']}",
                [
                    'type' => 'new_registration',
                    'user_id' => $userId,
                    'username' => $input['username'],
                    'email' => $input['email'],
                    'role' => $input['role']
                ]
            );
            
            // Also send to specific user if they have FCM token
            if (!empty($input['fcm_token'])) {
                $firebase->sendToDevice(
                    $input['fcm_token'],
                    'Registration Submitted',
                    'Your registration is pending admin approval.',
                    [
                        'type' => 'registration_submitted',
                        'user_id' => $userId,
                        'status' => 'pending'
                    ]
                );
            }
            
        } catch (Exception $e) {
            // Don't fail registration if notification fails
            error_log("Firebase notification failed: " . $e->getMessage());
        }
    }
    
    // Return success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Registration submitted successfully. Waiting for admin approval.',
        'user_id' => $userId,
        'status' => 'Pending'
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed', 'message' => $e->getMessage()]);
}
?>