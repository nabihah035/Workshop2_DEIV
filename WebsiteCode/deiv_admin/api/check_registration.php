<?php
// your_project_root/api/check_registration.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database config (go up 1 level from api/ to config/)
require_once __DIR__ . '/../config/db.php';

// Get input based on request method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
} else {
    $input = $_GET; // Allow GET for testing
}

$username = $input['username'] ?? '';
$email = $input['email'] ?? '';
$userId = $input['user_id'] ?? '';

if (empty($username) && empty($email) && empty($userId)) {
    echo json_encode(['error' => 'Username, email or user_id required']);
    exit;
}

try {
    $sql = "SELECT User_id, username, email, status, role, first_name, last_name, organization, created_at FROM user WHERE ";
    $params = [];
    
    if (!empty($userId)) {
        $sql .= "User_id = ?";
        $params = [$userId];
    } elseif (!empty($username) && !empty($email)) {
        $sql .= "(username = ? OR email = ?)";
        $params = [$username, $email];
    } elseif (!empty($username)) {
        $sql .= "username = ?";
        $params = [$username];
    } else {
        $sql .= "email = ?";
        $params = [$email];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo json_encode([
            'found' => true,
            'user' => [
                'user_id' => $user['User_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'status' => $user['status'],
                'role' => $user['role'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'organization' => $user['organization'],
                'created_at' => $user['created_at']
            ]
        ]);
    } else {
        echo json_encode(['found' => false]);
    }
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>