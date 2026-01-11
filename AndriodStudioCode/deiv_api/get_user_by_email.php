<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

// Require admin session
//if (!isset($_SESSION['User_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
//    http_response_code(403);
//    echo json_encode(['success' => false, 'message' => 'Not authorized']);
//    exit;
//}

require_once __DIR__ . '/../../admin/config/db.php';

if (!isset($_GET['email']) || empty($_GET['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email required']);
    exit;
}

$email = trim($_GET['email']);

try {
    $stmt = $pdo->prepare("SELECT User_id, username, email, first_name, last_name, role, organization FROM user WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode(['success' => true, 'user' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
}

exit;
