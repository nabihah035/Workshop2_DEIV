<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

// Basic admin check (optional) - only allow if admin session is present
if (!isset($_SESSION['User_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

require_once __DIR__ . '/../../admin/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$id = $_POST['id'] ?? null;
if (empty($id) || !is_numeric($id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid id']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM user WHERE User_id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error']);
}

exit;
