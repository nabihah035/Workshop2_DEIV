<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

// Include DB connection
require_once __DIR__ . '/../../admin/config/db.php';

if (!isset($_GET['email']) || empty($_GET['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email required']);
    exit;
}

$email = trim($_GET['email']);

try {
    $stmt = $pdo->prepare("SELECT User_id FROM user WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode(['success' => true, 'id' => (int)$row['User_id']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
}

exit;
